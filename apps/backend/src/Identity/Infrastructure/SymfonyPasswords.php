<?php

namespace App\Identity\Infrastructure;

use App\Identity\Application\Passwords;
use App\Identity\Domain\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SymfonyPasswords implements Passwords
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function hash(User $user, string $password): string
    {
        return $this->hasher->hashPassword(new Principal($user), $password);
    }

    public function valid(User $user, string $password): bool
    {
        return $this->hasher->isPasswordValid(new Principal($user), $password);
    }
}
