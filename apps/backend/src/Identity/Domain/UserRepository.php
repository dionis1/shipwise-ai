<?php

namespace App\Identity\Domain;

interface UserRepository
{
    public function byEmail(string $email): ?User;

    public function byId(string $id): ?User;

    public function save(User $user): void;
}
