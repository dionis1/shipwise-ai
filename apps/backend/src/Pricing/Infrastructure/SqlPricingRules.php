<?php

namespace App\Pricing\Infrastructure;

use App\Audit\Application\AuditRepository;
use App\Pricing\Application\PricingRules;
use App\Quoting\Domain\QuoteViolation;
use App\Shared\Domain\Identifier;
use Doctrine\DBAL\Connection;

final class SqlPricingRules implements PricingRules
{
    public function __construct(private Connection $db, private AuditRepository $audit, private string $environment)
    {
    }

    public function pricing(): array
    {
        return array_map(static function (array $row): array {
            $row['configuration'] = json_decode($row['configuration'], true, 32, JSON_THROW_ON_ERROR);

            return $row;
        }, $this->db->fetchAllAssociative('SELECT * FROM pricing_rules ORDER BY created_at DESC,id'));
    }

    public function addPricing(array $data, string $actor): string
    {
        $this->developmentOnly();
        if (array_diff(array_keys($data), ['serviceCode', 'version', 'currency', 'fixedMarginMinor', 'marginBasisPoints', 'minimumMarginMinor', 'feeMinor', 'taxBasisPoints'])) {
            throw new QuoteViolation('pricing_invalid');
        }
        $service = isset($data['serviceCode']) ? $this->service($data['serviceCode']) : null;
        $rule = ['version' => $this->version($data['version'] ?? null), 'currency' => 'EUR'];
        if (($data['currency'] ?? null) !== 'EUR') {
            throw new QuoteViolation('currency_mismatch');
        }
        foreach (['fixedMarginMinor', 'minimumMarginMinor', 'feeMinor', 'marginBasisPoints'] as $key) {
            $rule[$key] = $this->integer($data[$key] ?? null, $key === 'marginBasisPoints' ? 10000 : 1000000);
        }
        $tax = $this->integer($data['taxBasisPoints'] ?? null, 10000);
        $rule['taxRoutes'] = ['DE:DE' => ['basisPoints' => $tax, 'version' => 'synthetic-'.$rule['version']], 'DE:FR' => ['basisPoints' => $tax, 'version' => 'synthetic-'.$rule['version']]];

        return $this->db->transactional(function () use ($service, $rule, $actor): string {
            // Serialize default and service-specific activation to avoid conflicting current versions.
            $this->db->executeQuery("SELECT pg_advisory_xact_lock(hashtext('pricing-rule-activation'))");
            if ($this->db->fetchOne('SELECT id FROM pricing_rules WHERE version=?', [$rule['version']])) {
                throw new QuoteViolation('version_exists');
            }
            $this->db->executeStatement('UPDATE pricing_rules SET active=false WHERE service_code IS NOT DISTINCT FROM ? AND active', [$service]);
            $id = Identifier::new();
            $this->db->insert('pricing_rules', ['id' => $id, 'service_code' => $service, 'version' => $rule['version'], 'active' => 'true', 'configuration' => json_encode($rule, JSON_THROW_ON_ERROR)]);
            $this->audit->record($actor, 'pricing.rule_created', $id, ['purpose' => $rule['version']]);

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
