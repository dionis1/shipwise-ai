<?php

namespace App\CarrierIntegration\Application;

interface CarrierShipmentValidator
{
    /** Validate a fixed synthetic sandbox fixture; never accept customer shipment data. */
    public function validateFixture(): bool;
}
