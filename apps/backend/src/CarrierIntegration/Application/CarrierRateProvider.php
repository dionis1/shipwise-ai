<?php

namespace App\CarrierIntegration\Application;

use App\CarrierIntegration\Domain\RateResult;
use App\Quoting\Domain\ShipmentRequest;

interface CarrierRateProvider
{
    public function code(): string;

    /**
     * @param array<string,mixed> $tariff */
    public function quote(ShipmentRequest $request, array $tariff): RateResult;
}
