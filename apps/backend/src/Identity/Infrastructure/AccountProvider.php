<?php

namespace App\Identity\Infrastructure;

use App\Identity\Domain\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<Principal> */
final class AccountProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $users, private string $environment)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->byEmail($identifier);
        if (!$user) {
            throw new UserNotFoundException();
        }

        return new Principal($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Principal) {
            throw new UnsupportedUserException();
        } $account = $this->users->byId($user->account->id());
        if (!$account || ($this->environment === 'prod' && $account->kind() === 'staff')) {
            throw new UserNotFoundException();
        }

        return new Principal($account);
    }

    public function supportsClass(string $class): bool
    {
        return $class === Principal::class;
    }
}
