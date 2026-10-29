<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Application\CarrierRateProvider;
use App\CarrierIntegration\Domain\RateResult;
use App\Quoting\Domain\ShipmentRequest;

final class DevelopmentTariffProvider implements CarrierRateProvider
{
    public function __construct(private string $environment)
    {
    }

    public function code(): string
    {
        return 'development_tariff';
    }

    public function quote(ShipmentRequest $request, array $tariff): RateResult
    {
        if (!in_array($this->environment, ['dev', 'test', 'e2e'], true)) {
            return new RateResult('unavailable');
        }

        return new RateResult('success', ['baseMinor' => $tariff['baseMinor'] + intdiv($request->data['weightGrams'] + 999, 1000) * $tariff['perKgMinor'], 'surchargeMinor' => $tariff['surchargeMinor'], 'currency' => $tariff['currency']]);
    }
}
