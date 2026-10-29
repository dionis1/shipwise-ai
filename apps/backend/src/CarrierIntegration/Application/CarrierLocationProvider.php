<?php

namespace App\CarrierIntegration\Application;

use App\CarrierIntegration\Domain\LocationSearch;
use App\CarrierIntegration\Domain\ParcelShop;

interface CarrierLocationProvider
{
    /** @return list<ParcelShop> */
    public function locations(LocationSearch $search): array;
}
