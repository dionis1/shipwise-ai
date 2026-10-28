<?php

namespace App\Identity\Presentation;

use App\AccessControl\Application\AccessRepository;
use App\Identity\Domain\User;

final class ProfileView
{
    public function __construct(private AccessRepository $access)
    {
    }

    /**
     * @return array<string,mixed> */
    public function of(User $user): array
    {
        return ['id' => $user->id(), 'email' => $user->email(), 'name' => $user->name(), 'kind' => $user->kind(), 'status' => $user->status(), 'createdAt' => $user->createdAt()->format('c'), 'roles' => $this->access->roles($user->id()), 'permissions' => $this->access->permissions($user->id())];
    }
}
