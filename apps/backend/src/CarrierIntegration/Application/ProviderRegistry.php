<?php

namespace App\CarrierIntegration\Application;

use App\CarrierIntegration\Domain\RateResult;
use App\Quoting\Domain\ShipmentRequest;

final class ProviderRegistry
{
    /** @var array<string,CarrierRateProvider> */
    private array $providers = [];

    /**
     * @param iterable<CarrierRateProvider> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            if (isset($this->providers[$provider->code()]) || count($this->providers) >= 5) {
                throw new \LogicException('Invalid provider registry.');
            } $this->providers[$provider->code()] = $provider;
        }
    }

    /**
     * @param array<string,mixed> $tariff */
    public function quote(string $source, ShipmentRequest $request, array $tariff): RateResult
    {
        if (!isset($this->providers[$source])) {
            return new RateResult('unsupported_rating');
        }
        try {
            $result = $this->providers[$source]->quote($request, $tariff);
        } catch (\Throwable) {
            return new RateResult('temporary_failure');
        }
        if ($result->status === 'success') {
            $rate = $result->rate;
            foreach (['baseMinor', 'surchargeMinor'] as $field) {
                if (!isset($rate[$field]) || !is_int($rate[$field]) || $rate[$field] < 0 || $rate[$field] > 10000000) {
                    return new RateResult('invalid_response');
                }
            }
            if (!isset($rate['currency']) || $rate['currency'] !== $tariff['currency']) {
                return new RateResult('invalid_response');
            }
        }

        return $result;
    }
}
