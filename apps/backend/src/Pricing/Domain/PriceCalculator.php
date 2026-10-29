<?php

namespace App\Pricing\Domain;

use App\Quoting\Domain\QuoteViolation;

final class PriceCalculator
{
    /**
     * @param array<string,mixed> $rate
     * @param array<string,mixed> $rule
     *
     * @return array<string,mixed> */
    public function calculate(array $rate, array $rule, string $route): array
    {
        if (!isset($rule['taxRoutes'][$route])) {
            throw new QuoteViolation('tax_undefined');
        }
        $taxRule = $rule['taxRoutes'][$route];
        $base = new Money($rate['baseMinor'], $rate['currency']);
        $surcharge = new Money($rate['surchargeMinor'], $rate['currency']);
        if ($rule['currency'] !== $base->currency) {
            throw new QuoteViolation('currency_mismatch');
        }
        $purchase = $base->add($surcharge);
        $margin = new Money(max($rule['minimumMarginMinor'], $rule['fixedMarginMinor'] + $purchase->basisPoints($rule['marginBasisPoints'])->minor), $base->currency);
        $fees = new Money($rule['feeMinor'], $base->currency);
        $net = $purchase->add($margin)->add($fees);
        $tax = $net->basisPoints($taxRule['basisPoints']);

        return ['baseMinor' => $base->minor, 'surchargeMinor' => $surcharge->minor, 'marginMinor' => $margin->minor, 'feeMinor' => $fees->minor, 'netMinor' => $net->minor, 'taxMinor' => $tax->minor, 'totalMinor' => $net->add($tax)->minor, 'currency' => $base->currency, 'taxIncluded' => true, 'taxScenario' => $taxRule['version'], 'taxBasisPoints' => $taxRule['basisPoints'], 'pricingVersion' => $rule['version'], 'development' => true];
    }
}
