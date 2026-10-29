<?php

namespace App\Quoting\Domain;

final class QuoteViolation extends \DomainException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
