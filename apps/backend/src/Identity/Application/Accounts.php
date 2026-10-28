<?php

namespace App\Identity\Application;

use App\AccessControl\Application\AccessRepository;
use App\Audit\Application\AuditRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Transactions;
use App\Shared\Domain\RuleViolation;

final class Accounts
{
    public function __construct(private UserRepository $users, private TokenRepository $tokens, private AccountMailer $mailer, private AccessRepository $access, private AuditRepository $audit, private Transactions $transactions, private Passwords $hasher)
    {
    }

    public function register(string $email, string $name, string $password, string $preferredLocale = 'en'): void
    {
        User::validateLocale($preferredLocale);
        User::validatePassword($password);
        $this->transactions->run(function () use ($email, $name, $password, $preferredLocale) {
            if ($this->users->byEmail($email)) {
                return;
            }
            $user = new User($email, $name, '');
            $user->setPreferredLocale($preferredLocale);
            $user->changePassword($this->hasher->hash($user, $password));
            $this->users->save($user);
            $this->access->assign($user->id(), ['CUSTOMER']);
            $this->mailer->send($user->email(), 'verify', $this->tokens->issue($user->id(), 'verify', 86400), $user->preferredLocale());
            $this->audit->record($user->id(), 'account.registered', $user->id());
        });
    }

    public function consume(string $raw, string $purpose, ?string $password = null): void
    {
        $this->transactions->run(function () use ($raw, $purpose, $password) {
            $id = $this->tokens->consume($raw, $purpose);
            $user = $id ? $this->users->byId($id) : null;
            if (!$user) {
                throw new RuleViolation('This link is invalid or expired.');
            }
            if ($purpose === 'verify') {
                if ($user->status() !== 'pending_verification') {
                    throw new RuleViolation('Account cannot be verified.');
                } $user->activate();
            } else {
                if ($purpose === 'invite' && $user->status() !== 'invited') {
                    throw new RuleViolation('Invitation cannot be accepted.');
                }
                if ($purpose === 'reset' && $user->status() !== 'active') {
                    throw new RuleViolation('Account unavailable.');
                }
                User::validatePassword($password ?? '');
                $user->changePassword($this->hasher->hash($user, $password ?? ''));
                if ($purpose === 'invite') {
                    $user->activate();
                }
            }
            $this->users->save($user);
            $this->audit->record($id, 'account.'.$purpose, $id);
        });
    }

    public function forgot(string $email): void
    {
        $this->transactions->run(function () use ($email) {
            $user = $this->users->byEmail($email);
            if ($user && $user->status() === 'active') {
                $this->mailer->send($user->email(), 'reset', $this->tokens->issue($user->id(), 'reset', 3600), $user->preferredLocale());
                $this->audit->record(null, 'account.reset_requested', $user->id());
            }
        });
    }

    public function profile(User $user, ?string $name, ?string $preferredLocale = null): void
    {
        $this->transactions->run(function () use ($user, $name, $preferredLocale) {
            if ($name !== null) {
                $user->rename($name);
            }
            if ($preferredLocale !== null) {
                $user->setPreferredLocale($preferredLocale);
            }
            $this->users->save($user);
            $this->audit->record($user->id(), 'account.profile_updated', $user->id());
        });
    }

    public function password(User $user, string $current, string $password): void
    {
        if (!$this->hasher->valid($user, $current)) {
            throw new RuleViolation('Current password is incorrect.');
        }
        User::validatePassword($password);
        $this->transactions->run(function () use ($user, $password) {
            $user->changePassword($this->hasher->hash($user, $password));
            $this->users->save($user);
            $this->audit->record($user->id(), 'account.password_changed', $user->id());
        });
    }
}
