<?php

namespace App\CarrierIntegration\Domain;

/** Provider values are nullable when absent; no inferred coordinates or services. */
final readonly class ParcelShop
{
    /** @param list<string> $services */
    public function __construct(public string $carrier, public string $id, public ?string $name, public ?string $address, public ?string $postalCode, public ?string $city, public ?string $country, public ?float $latitude, public ?float $longitude, public array $services, public string $retrievedAt, public string $source)
    {
    }
}
