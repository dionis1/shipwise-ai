<?php

namespace App\Shared\Application;

interface Transactions
{
    public function run(callable $operation): mixed;
}
