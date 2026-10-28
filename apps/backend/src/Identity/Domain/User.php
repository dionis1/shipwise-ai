<?php

namespace App\Identity\Domain;

use App\Shared\Domain\Identifier;
use App\Shared\Domain\RuleViolation;

class User
{
    private string $id;
    private string $email;
    private string $name;
    private string $passwordHash;
    private string $kind;
    private string $status;
    private int $sessionVersion = 1;
    private string $preferredLocale = 'en';
    private \DateTimeImmutable $createdAt;

    public function __construct(string $email, string $name, string $passwordHash, string $kind = 'customer')
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            throw new RuleViolation('A valid email address is required.');
        }
        self::validateName($name);
        if (!in_array($kind, ['customer', 'staff'], true)) {
            throw new RuleViolation('Invalid account type.');
        }
        $this->id = Identifier::new();
        $this->email = $email;
        $this->name = trim($name);
        $this->passwordHash = $passwordHash;
        $this->kind = $kind;
        $this->status = $kind === 'customer' ? 'pending_verification' : 'invited';
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function validateName(string $name): void
    {
        if (mb_strlen(trim($name)) < 2 || mb_strlen($name) > 100) {
            throw new RuleViolation('Name must contain between 2 and 100 characters.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function sessionVersion(): int
    {
        return $this->sessionVersion;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function preferredLocale(): string
    {
        return $this->preferredLocale;
    }

    public static function validateLocale(string $locale): void
    {
        if (!in_array($locale, ['en', 'de', 'fr'], true)) {
            throw new RuleViolation('Unsupported locale.');
        }
    }

    public function setPreferredLocale(string $locale): void
    {
        self::validateLocale($locale);
        $this->preferredLocale = $locale;
    }

    public function rename(string $name): void
    {
        self::validateName($name);
        $this->name = trim($name);
    }

    public function activate(): void
    {
        if (!in_array($this->status, ['invited', 'pending_verification', 'suspended'], true)) {
            throw new RuleViolation('Account is already active.');
        } $this->status = 'active';
        $this->revokeSessions();
    }

    public function suspend(): void
    {
        if ($this->status !== 'active') {
            throw new RuleViolation('Only active accounts can be suspended.');
        } $this->status = 'suspended';
        $this->revokeSessions();
    }

    public function changePassword(string $hash): void
    {
        $this->passwordHash = $hash;
        $this->revokeSessions();
    }

    public function revokeSessions(): void
    {
        ++$this->sessionVersion;
    }

    public static function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 128) {
            throw new RuleViolation('Password must contain between 12 and 128 bytes.');
        }
    }
}
