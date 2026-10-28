<?php

namespace App\Tests\Unit;

use App\AccessControl\Domain\RolePolicy;
use App\Identity\Domain\User;
use App\Shared\Domain\RuleViolation;
use PHPUnit\Framework\TestCase;

final class AccountRulesTest extends TestCase
{
    public function testCustomerRequiresVerificationAndSensitiveChangesRevokeSessions(): void
    {
        $user = new User('PERSON@example.com', 'Person', 'hash');
        self::assertSame('person@example.com', $user->email());
        self::assertSame('pending_verification', $user->status());
        $v = $user->sessionVersion();
        $user->activate();
        self::assertSame('active', $user->status());
        $user->changePassword('new-hash');
        self::assertGreaterThan($v, $user->sessionVersion());
        $user->suspend();
        self::assertSame('suspended', $user->status());
    }

    public function testInvalidPasswordIsRejected(): void
    {
        $this->expectException(RuleViolation::class);
        User::validatePassword('short');
    }

    public function testPendingAccountCannotBeSuspended(): void
    {
        $this->expectException(RuleViolation::class);
        (new User('a@example.com', 'Person', 'hash'))->suspend();
    }

    public function testCustomerRoleCannotBeAssignedToStaff(): void
    {
        $this->expectException(RuleViolation::class);
        RolePolicy::validateStaffRoles(['CUSTOMER']);
    }

    public function testLastActiveAdministratorIsProtected(): void
    {
        $this->expectException(RuleViolation::class);
        RolePolicy::protectLastAdmin(true, false, 1);
    }

    public function testAnotherAdministratorAllowsRoleRevocation(): void
    {
        RolePolicy::protectLastAdmin(true, false, 2);
        self::assertTrue(true);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(RuleViolation::class);
        new User('invalid', 'Person', 'hash');
    }
}
