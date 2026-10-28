<?php

namespace App\Audit\Application;

interface AuditRepository
{
    /** @param array<string, scalar|null> $context */
    public function record(?string $actor, string $action, ?string $target, array $context = []): void;

    /** @param array<string, string> $filters
     * @return array<string, mixed> */
    public function list(array $filters, int $page): array;
}
