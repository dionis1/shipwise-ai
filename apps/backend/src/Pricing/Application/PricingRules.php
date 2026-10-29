<?php

namespace App\Pricing\Application;

interface PricingRules
{
    /** @return list<array<string,mixed>> */
    public function pricing(): array;

    /** @param array<string,mixed> $data */
    public function addPricing(array $data, string $actor): string;
}
