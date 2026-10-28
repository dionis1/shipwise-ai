<?php

namespace App\AccessControl\Domain;

use App\Shared\Domain\RuleViolation;

final class RolePolicy
{
    /** @param list<string> $roles */
    public static function validateStaffRoles(array $roles): void
    {
        if (!$roles || count($roles) !== count(array_unique($roles)) || in_array('CUSTOMER', $roles, true)) {
            throw new RuleViolation('Assign one or more distinct staff roles.');
        }
    }

    public static function protectLastAdmin(bool $wasAdmin, bool $willBeAdmin, int $activeAdmins): void
    {
        if ($wasAdmin && !$willBeAdmin && $activeAdmins <= 1) {
            throw new RuleViolation('The last active Super Administrator must remain active.');
        }
    }
}
