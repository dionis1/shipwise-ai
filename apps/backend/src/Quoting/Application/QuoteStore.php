<?php

namespace App\Quoting\Application;

interface QuoteStore
{
    /** @return list<array<string,mixed>> */
    public function diagnostics(): array;

    public function exclusive(string $scope, callable $operation): mixed;

    /**
     * @return array<string,mixed>|null */
    public function find(string $id): ?array;

    /**
     * @return array<string,mixed>|null */
    public function duplicate(string $scope, string $key): ?array;

    /**
     * @param array<string,mixed> $quote */
    public function save(array $quote): void;
}
