<?php

namespace App\Pricing\Domain;

use App\Quoting\Domain\QuoteViolation;

final readonly class Money
{
    public function __construct(public int $minor, public string $currency)
    {
        if ($minor < 0 || $minor > 100000000 || !in_array($currency, ['EUR', 'GBP', 'USD', 'CHF'], true)) {
            throw new QuoteViolation('money_invalid');
        }
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new QuoteViolation('currency_mismatch');
        }

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function basisPoints(int $points): self
    {
        if ($points < 0 || $points > 10000) {
            throw new QuoteViolation('pricing_invalid');
        }

        return new self(intdiv($this->minor * $points + 5000, 10000), $this->currency);
    }
}
