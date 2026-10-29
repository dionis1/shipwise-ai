<?php

namespace App\CarrierIntegration\Domain;

final readonly class RateResult
{
    public const FAILURES = ['unsupported_service', 'invalid_parcel', 'unavailable', 'missing_credentials', 'timeout', 'rate_limited', 'temporary_failure', 'unsupported_rating', 'invalid_response'];

    /**
     * @param array<string,mixed>|null $rate */
    public function __construct(public string $status, public ?array $rate = null)
    {
        if (!in_array($status, array_merge(['success'], self::FAILURES), true) || ($status === 'success') !== ($rate !== null)) {
            throw new \InvalidArgumentException('Invalid provider result.');
        }
    }
}
