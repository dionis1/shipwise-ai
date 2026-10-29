<?php

namespace App\CarrierCatalog\Domain;

use App\Quoting\Domain\ShipmentRequest;

final class Eligibility
{
    /**
     * @param array<string,mixed> $service
     *
     * @return list<string> */
    public function reasons(ShipmentRequest $request, array $service): array
    {
        $r = $request->data;
        $rules = $service['rules'];
        $reasons = [];
        if (!$service['enabled'] || !$service['carrierEnabled']) {
            $reasons[] = 'disabled';
        }
        if (!in_array($r['originCountry'].':'.$r['destinationCountry'], $rules['routes'], true)) {
            $reasons[] = 'route_unsupported';
        }
        if ($r['weightGrams'] > $rules['maxWeightGrams']) {
            $reasons[] = 'weight_exceeded';
        }
        $dimensions = [$r['lengthMm'], $r['widthMm'], $r['heightMm']];
        rsort($dimensions);
        $limits = $rules['maxDimensionsMm'];
        rsort($limits);
        foreach ($dimensions as $i => $dimension) {
            if ($dimension > $limits[$i]) {
                $reasons[] = 'dimensions_exceeded';
                break;
            }
        }
        if ($r['handover'] !== 'any' && !in_array($r['handover'], $rules['handover'], true)) {
            $reasons[] = 'handover_unsupported';
        }
        if ($r['speed'] !== 'any' && $r['speed'] !== $rules['speed']) {
            $reasons[] = 'speed_unsupported';
        }

        return $reasons;
    }
}
