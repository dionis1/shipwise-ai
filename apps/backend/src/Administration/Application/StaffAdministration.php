<?php

namespace App\Administration\Application;

use App\AccessControl\Application\AccessRepository;
use App\AccessControl\Domain\RolePolicy;
use App\Audit\Application\AuditRepository;
use App\Identity\Application\AccountMailer;
use App\Identity\Application\TokenRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Transactions;
use App\Shared\Domain\RuleViolation;

final class StaffAdministration
{
    public function __construct(private UserRepository $users, private TokenRepository $tokens, private AccountMailer $mailer, private AccessRepository $access, private AuditRepository $audit, private Transactions $transactions)
    {
    }

    /** @param list<string> $roles */
    public function invite(User $actor, string $email, string $name, array $roles): User
    {
        $this->requireSuperAdmin($actor);
        RolePolicy::validateStaffRoles($roles);

        return $this->transactions->run(function () use ($actor, $email, $name, $roles) {
            if ($this->users->byEmail($email)) {
                throw new RuleViolation('An account with this email already exists.');
            }
            $user = new User($email, $name, '', 'staff');
            $this->users->save($user);
            $this->access->assign($user->id(), $roles);
            $this->mailer->send($email, 'invite', $this->tokens->issue($user->id(), 'invite', 172800));
            $this->audit->record($actor->id(), 'staff.invited', $user->id(), ['roles' => implode(',', $roles)]);

            return $user;
        });
    }

    /** @param list<string> $roles */
    public function roles(User $actor, User $target, array $roles): void
    {
        $this->requireSuperAdmin($actor);
        RolePolicy::validateStaffRoles($roles);
        $this->staff($target);
        $this->transactions->run(function () use ($actor, $target, $roles) {
            RolePolicy::protectLastAdmin($target->status() === 'active' && in_array('SUPER_ADMIN', $this->access->roles($target->id()), true), $target->status() === 'active' && in_array('SUPER_ADMIN', $roles, true), $this->access->activeAdminCount());
            $this->access->assign($target->id(), $roles);
            $target->revokeSessions();
            $this->users->save($target);
            $this->audit->record($actor->id(), 'staff.roles_changed', $target->id(), ['roles' => implode(',', $roles)]);
        });
    }

    public function status(User $actor, User $target, string $status): void
    {
        $this->staff($target);
        if (in_array('SUPER_ADMIN', $this->access->roles($target->id()), true)) {
            $this->requireSuperAdmin($actor);
        }
        $this->transactions->run(function () use ($actor, $target, $status) {
            RolePolicy::protectLastAdmin($target->status() === 'active' && in_array('SUPER_ADMIN', $this->access->roles($target->id()), true), $status === 'active', $this->access->activeAdminCount());
            if ($status === 'active' && $target->status() === 'suspended') {
                $target->activate();
            } elseif ($status === 'suspended') {
                $target->suspend();
            } else {
                throw new RuleViolation('Only active and suspended staff accounts can change status.');
            }
            $this->users->save($target);
            $this->audit->record($actor->id(), 'staff.status_changed', $target->id(), ['status' => $status]);
        });
    }

    private function requireSuperAdmin(User $actor): void
    {
        if (!in_array('SUPER_ADMIN', $this->access->roles($actor->id()), true)) {
            throw new RuleViolation('Only a Super Administrator can manage staff invitations and roles.');
        }
    }

    private function staff(User $target): void
    {
        if ($target->kind() !== 'staff') {
            throw new RuleViolation('A staff account is required.');
        }
    }
}
