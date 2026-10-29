<?php

namespace App\CarrierIntegration\Domain;

final class IntegrationFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
