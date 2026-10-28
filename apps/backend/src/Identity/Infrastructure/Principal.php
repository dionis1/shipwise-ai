<?php

namespace App\Identity\Infrastructure;

use App\Identity\Domain\User;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class Principal implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    private readonly int $authenticatedVersion;

    public function __construct(public readonly User $account)
    {
        $this->authenticatedVersion = $account->sessionVersion();
    }

    public function getUserIdentifier(): string
    {
        return $this->account->email();
    }

    public function getPassword(): string
    {
        return $this->account->passwordHash();
    }

    public function getRoles(): array
    {
        return $this->account->kind() === 'staff' ? ['ROLE_USER', 'ROLE_STAFF'] : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->account->id() === $this->account->id() && $user->authenticatedVersion === $this->authenticatedVersion && $user->account->status() === 'active';
    }
}
