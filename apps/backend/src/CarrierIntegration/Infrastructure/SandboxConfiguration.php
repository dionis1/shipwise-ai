<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Domain\IntegrationFailure;

final readonly class SandboxConfiguration
{
    /** @param array<string,string> $values */
    public function __construct(private string $environment, private array $values)
    {
    }

    public function value(string $name): string
    {
        return $this->values[$name] ?? '';
    }

    public function configured(string $carrier): bool
    {
        $keys = match ($carrier) {
            'DHL' => ['DHL_CLIENT_ID', 'DHL_CLIENT_SECRET', 'DHL_USERNAME', 'DHL_PASSWORD'],
            'DPD' => ['DPD_DELIS_ID', 'DPD_PASSWORD'],
            default => [],
        };

        return [] !== $keys && !array_any($keys, fn (string $key): bool => '' === $this->value($key));
    }

    public function enabled(string $carrier): bool
    {
        // Test and CI may only use injected mock clients, never environment-enabled networking.
        return 'dev' === $this->environment && '1' === $this->value($carrier.'_SANDBOX_ENABLED');
    }

    public function guard(string $carrier): void
    {
        if (!$this->configured($carrier)) {
            throw new IntegrationFailure('not_configured');
        }
        if (!$this->enabled($carrier)) {
            throw new IntegrationFailure('disabled');
        }
    }
}
