<?php

namespace App\Identity\Application;

interface AccountMailer
{
    public function send(string $email, string $purpose, string $token): void;
}
