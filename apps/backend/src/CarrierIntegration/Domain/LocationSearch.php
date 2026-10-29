<?php

namespace App\CarrierIntegration\Domain;

final readonly class LocationSearch
{
    public function __construct(public string $country, public string $postalCode, public string $city = '', public int $limit = 10)
    {
        if (!preg_match('/^[A-Z]{2}$/D', $country) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{1,8}$/D', $postalCode) || mb_strlen($city) > 50 || preg_match('/[\x00-\x1f<>]/u', $city) || $limit < 1 || $limit > 20) {
            throw new IntegrationFailure('search_invalid');
        }
    }
}
