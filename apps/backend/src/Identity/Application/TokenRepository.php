<?php

namespace App\Identity\Application;

interface TokenRepository
{
    public function issue(string $userId, string $purpose, int $lifetime): string;

    public function consume(string $token, string $purpose): ?string;
}
