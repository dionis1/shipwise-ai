<?php

namespace App\CarrierIntegration\Infrastructure;

use App\Audit\Application\AuditRepository;
use App\CarrierIntegration\Domain\IntegrationFailure;
use App\CarrierIntegration\Domain\LocationSearch;
use App\CarrierIntegration\Domain\ParcelShop;
use Doctrine\DBAL\Connection;

final class SandboxManager
{
    public function __construct(private Connection $db, private AuditRepository $audit, private SandboxConfiguration $configuration, private DhlSandboxClient $dhl, private DpdLocationProvider $dpd)
    {
    }

    /** @return list<array<string,mixed>> */
    public function statuses(): array
    {
        return array_map($this->status(...), ['DHL', 'DPD', 'GLS', 'UPS']);
    }

    /** @return array<string,mixed> */
    public function status(string $code): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM carrier_sandboxes WHERE code=?', [$code]);
        $configured = $this->configuration->configured($code);
        $enabled = $row && (bool) $row['enabled'] && $this->configuration->enabled($code);
        $fresh = $row && $row['checked_at'] && strtotime($row['checked_at']) > time() - 600 && hash_equals($this->fingerprint($code), $row['configuration_hash'] ?? '');
        $status = 'GLS' === $code ? 'specification_unavailable' : (!$configured ? 'not_configured' : 'credentials_configured');
        if ($fresh && $enabled) {
            $status = $row['result'];
        }

        return ['carrier' => $code, 'status' => $status, 'credentialsConfigured' => $configured, 'environmentEnabled' => $this->configuration->enabled($code), 'enabled' => $row && (bool) $row['enabled'], 'liveApproved' => false, 'capabilities' => match ($code) {
            'DHL' => ['authentication', 'validation'], 'DPD' => ['authentication', 'locations'], default => [],
        }, 'checkedAt' => $fresh ? (new \DateTimeImmutable($row['checked_at']))->format('c') : null, 'operation' => $fresh ? $row['operation'] : null];
    }

    public function configure(string $code, bool $enabled, string $actor): void
    {
        if (!in_array($code, ['DHL', 'DPD'], true)) {
            throw new IntegrationFailure('operation_unsupported');
        }
        if ($enabled) {
            $this->configuration->guard($code);
        }
        $this->db->transactional(function () use ($code, $enabled, $actor): void {
            $this->db->executeStatement('UPDATE carrier_sandboxes SET enabled=?,checked_at=NULL,result=NULL,operation=NULL,configuration_hash=NULL WHERE code=?', [$enabled ? 'true' : 'false', $code]);
            $this->audit->record($actor, 'carrier.sandbox_configuration_changed', null, ['purpose' => $code, 'status' => $enabled ? 'enabled' : 'disabled']);
        });
    }

    /** @return array<string,mixed> */
    public function check(string $code, string $operation, string $actor): array
    {
        if (!in_array($operation, ['authentication', 'validation'], true) || !in_array($code, ['DHL', 'DPD'], true) || ('validation' === $operation && 'DHL' !== $code)) {
            throw new IntegrationFailure('operation_unsupported');
        }
        $result = 'sandbox_connection_verified';
        try {
            $this->guard($code);
            if ('DHL' === $code) {
                if ('validation' === $operation) {
                    $result = $this->dhl->validateFixture() ? 'validation_passed' : 'validation_rejected';
                } else {
                    $this->dhl->check();
                }
            } else {
                $this->dpd->check();
            }
        } catch (IntegrationFailure $error) {
            $result = $error->reason;
        }
        $this->db->transactional(function () use ($code, $result, $operation, $actor): void {
            $this->record($code, $operation, $result);
            $this->audit->record($actor, 'carrier.sandbox_checked', null, ['purpose' => $code.':'.$operation, 'status' => $result]);
        });

        return ['carrier' => $code, 'operation' => $operation, 'status' => $result, 'checkedAt' => gmdate('c')];
    }

    /** @return array{items:list<ParcelShop>,outcomes:list<array{carrier:string,status:string}>} */
    public function locations(LocationSearch $search, string $filter): array
    {
        $items = [];
        $outcomes = [];
        foreach ('all' === $filter ? ['DPD', 'GLS'] : [$filter] as $code) {
            $status = 'available';
            try {
                if ('GLS' === $code) {
                    throw new IntegrationFailure('specification_unavailable');
                }
                $this->guard($code);
                $items = array_merge($items, $this->dpd->locations($search));
            } catch (IntegrationFailure $error) {
                $status = $error->reason;
            }
            // Searches and results are intentionally not persisted.
            $outcomes[] = ['carrier' => $code, 'status' => $status];
        }

        return ['items' => $items, 'outcomes' => $outcomes];
    }

    private function guard(string $code): void
    {
        $this->configuration->guard($code);
        if (!$this->db->fetchOne('SELECT enabled FROM carrier_sandboxes WHERE code=?', [$code])) {
            throw new IntegrationFailure('disabled');
        }
    }

    private function record(string $code, string $operation, string $result): void
    {
        $this->db->executeStatement('UPDATE carrier_sandboxes SET checked_at=?,operation=?,result=?,configuration_hash=? WHERE code=?', [gmdate('c'), $operation, $result, $this->fingerprint($code), $code]);
    }

    private function fingerprint(string $code): string
    {
        $keys = 'DHL' === $code ? ['DHL_CLIENT_ID', 'DHL_CLIENT_SECRET', 'DHL_USERNAME', 'DHL_PASSWORD', 'DHL_PROFILE', 'DHL_BILLING_NUMBER'] : ['DPD_DELIS_ID', 'DPD_PASSWORD'];

        return hash('sha256', implode('|', array_map($this->configuration->value(...), $keys)));
    }
}
