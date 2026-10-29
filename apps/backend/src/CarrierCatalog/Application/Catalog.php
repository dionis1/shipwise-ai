<?php

namespace App\CarrierCatalog\Application;

interface Catalog
{
    /** @return list<array<string,mixed>> */
    public function carriers(): array;

    /** @return list<array<string,mixed>> */
    public function services(): array;

    /** @return list<array<string,mixed>> */
    public function tariffs(): array;

    public function configure(string $code, bool $enabled, ?string $serviceCode, string $actor): void;

    /** @param array<string,mixed> $data */
    public function importTariff(array $data, string $actor): string;
}
