<?php

namespace App\Identity\Infrastructure;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AccountChecker implements UserCheckerInterface
{
    public function __construct(private string $environment)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Principal || ($user->account->status() !== 'active' || ($this->environment === 'prod' && $user->account->kind() === 'staff'))) {
            throw new CustomUserMessageAccountStatusException('Invalid credentials or unavailable account.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void
    {
        $this->checkPreAuth($user);
    }
}
