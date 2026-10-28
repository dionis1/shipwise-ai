<?php

namespace App\AccessControl\Infrastructure;

use App\AccessControl\Application\AccessRepository;
use App\Identity\Infrastructure\Principal;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
final class PermissionVoter extends Voter
{
    public function __construct(private AccessRepository $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_contains($attribute, '.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof Principal && $user->account->status() === 'active' && $user->account->kind() === 'staff' && in_array($attribute, $this->access->permissions($user->account->id()), true);
    }
}
