<?php

namespace App\AccessControl\Application;

interface AccessRepository
{
    /**
     * @return list<string> */
    public function roles(string $userId): array;

    /**
     * @return list<string> */
    public function permissions(string $userId): array;

    /** @param list<string> $roles */
    public function assign(string $userId, array $roles): void;

    public function activeAdminCount(): int;

    /**
     * @return list<array<string,mixed>> */
    public function matrix(): array;

    /**
     * @return list<string> */
    public function allPermissions(): array;

    /** @param list<string> $permissions */
    public function updatePermissions(string $role, array $permissions): void;
}
