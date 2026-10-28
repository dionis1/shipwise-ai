<?php

namespace App\Identity\Application;

use App\Identity\Domain\User;

interface Passwords
{
    public function hash(User $user, string $password): string;

    public function valid(User $user, string $password): bool;
}
