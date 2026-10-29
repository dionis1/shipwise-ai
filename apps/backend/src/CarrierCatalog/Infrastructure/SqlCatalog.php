<?php

namespace App\CarrierCatalog\Infrastructure;

use App\Audit\Application\AuditRepository;
use App\CarrierCatalog\Application\Catalog;
use App\Quoting\Domain\QuoteViolation;
use App\Shared\Domain\Identifier;
use Doctrine\DBAL\Connection;

final class SqlCatalog implements Catalog
{
    public function __construct(private Connection $db, private AuditRepository $audit, private string $environment)
    {
    }

    public function carriers(): array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM carriers ORDER BY code');
    }

    public function services(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT s.*,c.name AS carrier_name,c.enabled AS carrier_enabled,c.mode,t.version AS tariff_version,t.configuration AS tariff,COALESCE(p.configuration,d.configuration) AS pricing FROM carrier_services s JOIN carriers c ON c.code=s.carrier_code LEFT JOIN carrier_tariffs t ON t.service_code=s.code AND t.active AND t.effective_from <= CURRENT_TIMESTAMP LEFT JOIN pricing_rules p ON p.service_code=s.code AND p.active LEFT JOIN pricing_rules d ON d.service_code IS NULL AND d.active ORDER BY s.code');

        return array_map(static function (array $r): array {
            $r['rules'] = json_decode($r['rules'], true, 32, JSON_THROW_ON_ERROR);
            $r['tariff'] = $r['tariff'] ? json_decode($r['tariff'], true, 32, JSON_THROW_ON_ERROR) : null;
            $r['pricing'] = $r['pricing'] ? json_decode($r['pricing'], true, 32, JSON_THROW_ON_ERROR) : null;
            $r['enabled'] = (bool) $r['enabled'];
            $r['carrierEnabled'] = (bool) $r['carrier_enabled'];

            return $r;
        }, $rows);
    }

    public function tariffs(): array
    {
        return $this->decode($this->db->fetchAllAssociative('SELECT * FROM carrier_tariffs ORDER BY created_at DESC,id'));
    }

    /**
     * @param list<array<string,mixed>> $rows
     *
     * @return list<array<string,mixed>> */
    private function decode(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['configuration'] = json_decode($r['configuration'], true, 32, JSON_THROW_ON_ERROR);
        }

        return $rows;
    }

    public function configure(string $code, bool $enabled, ?string $serviceCode, string $actor): void
    {
        $this->db->transactional(function () use ($code, $enabled, $serviceCode, $actor): void {
            $carrier = $this->db->fetchAssociative('SELECT * FROM carriers WHERE code=? FOR UPDATE', [$code]);
            if (!$carrier) {
                throw new QuoteViolation('carrier_unknown');
            }
            if ($enabled && ($carrier['mode'] !== 'development_tariff' || !in_array($this->environment, ['dev', 'test', 'e2e'], true))) {
                throw new QuoteViolation('carrier_unconfigured');
            }
            if ($serviceCode !== null) {
                $count = $this->db->executeStatement('UPDATE carrier_services SET enabled=? WHERE code=? AND carrier_code=?', [$enabled ? 'true' : 'false', $serviceCode, $code]);
                if (!$count) {
                    throw new QuoteViolation('carrier_unknown');
                }
            } else {
                $this->db->executeStatement('UPDATE carriers SET enabled=? WHERE code=?', [$enabled ? 'true' : 'false', $code]);
            }
            $this->audit->record($actor, 'carrier.configuration_changed', null, ['purpose' => $code.':'.($serviceCode ?? 'carrier'), 'status' => $enabled ? 'enabled' : 'disabled']);
        });
    }

    public function importTariff(array $data, string $actor): string
    {
        $this->developmentOnly();
        if (array_diff(array_keys($data), ['serviceCode', 'version', 'baseMinor', 'perKgMinor', 'surchargeMinor', 'currency', 'effectiveFrom'])) {
            throw new QuoteViolation('tariff_invalid');
        }
        $service = $this->service($data['serviceCode'] ?? null);
        $version = $this->version($data['version'] ?? null);
        $configuration = [];
        foreach (['baseMinor', 'perKgMinor', 'surchargeMinor'] as $key) {
            $configuration[$key] = $this->integer($data[$key] ?? null, 1000000);
        }
        if (($data['currency'] ?? null) !== 'EUR') {
            throw new QuoteViolation('currency_mismatch');
        } $configuration['currency'] = 'EUR';
        $date = $data['effectiveFrom'] ?? '';
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $date)) {
            throw new QuoteViolation('tariff_invalid');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d\TH:i:s\Z') !== $date || $parsed > new \DateTimeImmutable()) {
            throw new QuoteViolation('tariff_invalid');
        }

        return $this->db->transactional(function () use ($service, $version, $configuration, $date, $actor): string {
            $this->db->fetchOne('SELECT code FROM carrier_services WHERE code=? FOR UPDATE', [$service]);
            if ($this->db->fetchOne('SELECT id FROM carrier_tariffs WHERE service_code=? AND version=?', [$service, $version])) {
                throw new QuoteViolation('version_exists');
            }
            $this->db->executeStatement('UPDATE carrier_tariffs SET active=false WHERE service_code=? AND active', [$service]);
            $id = Identifier::new();
            $this->db->insert('carrier_tariffs', ['id' => $id, 'service_code' => $service, 'version' => $version, 'active' => 'true', 'effective_from' => $date, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);
            $this->audit->record($actor, 'tariff.imported', $id, ['purpose' => $version]);

            return $id;
        });
    }

    private function developmentOnly(): void
    {
        if (!in_array($this->environment, ['dev', 'test', 'e2e'], true)) {
            throw new QuoteViolation('development_only');
        }
    }

    private function service(mixed $code): string
    {
        if (!is_string($code) || !$this->db->fetchOne("SELECT s.code FROM carrier_services s JOIN carriers c ON c.code=s.carrier_code WHERE s.code=? AND c.mode='development_tariff'", [$code])) {
            throw new QuoteViolation('carrier_unknown');
        }

        return $code;
    }

    private function version(mixed $version): string
    {
        if (!is_string($version) || !preg_match('/^[a-zA-Z0-9._-]{1,80}$/D', $version)) {
            throw new QuoteViolation('version_invalid');
        }

        return $version;
    }

    private function integer(mixed $value, int $max): int
    {
        if (!is_int($value) || $value < 0 || $value > $max) {
            throw new QuoteViolation('pricing_invalid');
        }

        return $value;
    }
}
