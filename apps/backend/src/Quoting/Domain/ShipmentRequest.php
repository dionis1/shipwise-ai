<?php

namespace App\Quoting\Domain;

final readonly class ShipmentRequest
{
    public const MAX_WEIGHT = 70000;
    public const MAX_DIMENSION = 2000;

    /**
     * @param array<string,mixed> $data */
    public function __construct(public array $data)
    {
        $fields = ['originCountry', 'originPostalCode', 'destinationCountry', 'destinationPostalCode', 'weightGrams', 'lengthMm', 'widthMm', 'heightMm', 'quantity', 'handover', 'speed'];
        if (array_diff(array_keys($data), $fields) || array_diff($fields, array_keys($data))) {
            throw new QuoteViolation('shipment_fields');
        }
        foreach (['originCountry', 'destinationCountry'] as $key) {
            if (!is_string($data[$key]) || !preg_match('/^[A-Z]{2}$/D', $data[$key]) || !in_array($data[$key], self::countries(), true)) {
                throw new QuoteViolation('country_invalid');
            }
        }
        foreach (['originPostalCode' => 'originCountry', 'destinationPostalCode' => 'destinationCountry'] as $key => $country) {
            if (!is_string($data[$key]) || strlen($data[$key]) > 16 || trim($data[$key]) === '' || !preg_match('/^[A-Za-z0-9 -]+$/D', $data[$key])) {
                throw new QuoteViolation('postal_invalid');
            }
            if (in_array($data[$country], ['DE', 'FR'], true) && !preg_match('/^[0-9]{5}$/D', $data[$key])) {
                throw new QuoteViolation('postal_invalid');
            }
        }
        foreach (['weightGrams' => self::MAX_WEIGHT, 'lengthMm' => self::MAX_DIMENSION, 'widthMm' => self::MAX_DIMENSION, 'heightMm' => self::MAX_DIMENSION] as $key => $max) {
            if (!is_int($data[$key]) || $data[$key] < 1 || $data[$key] > $max) {
                throw new QuoteViolation('parcel_invalid');
            }
        }
        if ($data['quantity'] !== 1) {
            throw new QuoteViolation('single_parcel_only');
        }
        if (!in_array($data['handover'], ['any', 'drop_off', 'pickup'], true) || !in_array($data['speed'], ['any', 'standard', 'express'], true)) {
            throw new QuoteViolation('options_invalid');
        }
    }

    /**
     * @return list<string> */
    public static function countries(): array
    {
        // ISO membership, not an invented international postal-code validation algorithm.
        return explode(' ', 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW');
    }
}
