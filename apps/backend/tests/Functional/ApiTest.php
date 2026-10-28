<?php

namespace App\Tests\Functional;

use App\Identity\Application\TokenRepository;
use App\Shared\Domain\Identifier;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        if (!str_ends_with((string) $this->db()->getDatabase(), '_test')) {
            throw new \LogicException('Tests require a dedicated _test database.');
        }
        $this->db()->executeStatement('TRUNCATE users, audit_events CASCADE');
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function fixture(string $role = 'CUSTOMER', string $status = 'active'): string
    {
        $id = Identifier::new();
        $this->db()->insert('users', ['id' => $id, 'email' => strtolower($role).'@example.test', 'name' => 'Test Person', 'password_hash' => password_hash('Testing-password-123!', PASSWORD_BCRYPT, ['cost' => 4]), 'kind' => $role === 'CUSTOMER' ? 'customer' : 'staff', 'status' => $status, 'session_version' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $this->db()->insert('user_roles', ['user_id' => $id, 'role_code' => $role]);

        return $id;
    }

    /** @return array<string,mixed> */
    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $data */
    private function send(string $method, string $path, array $data = []): void
    {
        $this->client->request('GET', '/api/v1/auth/csrf');
        $csrf = $this->body()['token'];
        $this->client->request($method, '/api/v1'.$path, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function login(string $role): void
    {
        $this->send('POST', '/auth/login', ['email' => strtolower($role).'@example.test', 'password' => 'Testing-password-123!']);
        self::assertResponseIsSuccessful();
    }

    public function testRegistrationVerificationAndProfile(): void
    {
        $this->send('POST', '/auth/register', ['email' => 'buyer@example.test', 'name' => 'Buyer Person', 'password' => 'Testing-password-123!']);
        self::assertResponseStatusCodeSame(202);
        $id = $this->db()->fetchOne("SELECT id FROM users WHERE email='buyer@example.test'");
        self::assertIsString($id);
        $token = static::getContainer()->get(TokenRepository::class)->issue($id, 'verify', 3600);
        $this->send('POST', '/auth/login', ['email' => 'buyer@example.test', 'password' => 'Testing-password-123!']);
        self::assertResponseStatusCodeSame(401);
        $this->send('POST', '/auth/verify-email', ['token' => $token]);
        self::assertResponseIsSuccessful();
        $this->send('POST', '/auth/verify-email', ['token' => $token]);
        self::assertResponseStatusCodeSame(422);
        $this->send('POST', '/auth/login', ['email' => 'buyer@example.test', 'password' => 'Testing-password-123!']);
        self::assertResponseIsSuccessful();
        $this->send('PATCH', '/me', ['name' => 'New Name']);
        self::assertResponseIsSuccessful();
        self::assertSame('New Name', $this->body()['name']);
        $this->client->request('GET', '/api/v1/admin/staff');
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', '/auth/logout');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testCsrfAndUnknownFieldsAreRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/auth/register', ['email' => 'a@example.test']);
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', '/auth/register', ['email' => 'buyer@example.test', 'name' => 'Buyer', 'password' => 'Testing-password-123!', 'roles' => ['SUPER_ADMIN']]);
        self::assertResponseStatusCodeSame(400);
    }

    /** @return iterable<string,array{string,string,int}> */
    public static function matrix(): iterable
    {
        yield 'finance allowed' => ['FINANCE_MANAGER', 'finance', 200];
        yield 'finance denied marketing' => ['FINANCE_MANAGER', 'marketing', 403];
        yield 'marketing denied finance' => ['MARKETING_MANAGER', 'finance', 403];
        yield 'qa allowed' => ['QA_ENGINEER', 'qa', 200];
        yield 'operations allowed' => ['OPERATIONS_MANAGER', 'operations', 200];
        yield 'support allowed' => ['CUSTOMER_SUPPORT', 'support', 200];
        yield 'carrier allowed' => ['CARRIER_MANAGER', 'carriers', 200];
        yield 'customer denied' => ['CUSTOMER', 'finance', 403];
        yield 'auditor denied finance' => ['AUDITOR', 'finance', 403];
        yield 'super allowed' => ['SUPER_ADMIN', 'finance', 200];
    }

    #[DataProvider('matrix')]
    public function testAuthorizationMatrix(string $role, string $department, int $status): void
    {
        $this->fixture($role);
        $this->login($role);
        $this->client->request('GET', '/api/v1/admin/departments/'.$department);
        self::assertResponseStatusCodeSame($status);
    }

    public function testUnauthenticatedAdministrationReturns401(): void
    {
        $this->client->request('GET', '/api/v1/admin/staff');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLastAdministratorCannotBeSuspendedOrDemoted(): void
    {
        $id = $this->fixture('SUPER_ADMIN');
        $this->login('SUPER_ADMIN');
        $this->send('PATCH', '/admin/staff/'.$id, ['status' => 'suspended']);
        self::assertResponseStatusCodeSame(422);
        $this->send('PUT', '/admin/staff/'.$id.'/roles', ['roles' => ['AUDITOR']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('active', $this->db()->fetchOne('SELECT status FROM users WHERE id=?', [$id]));
    }

    public function testInvitationAcceptanceRolesAndAuditArePersisted(): void
    {
        $this->fixture('SUPER_ADMIN');
        $this->login('SUPER_ADMIN');
        $this->send('POST', '/admin/staff/invitations', ['email' => 'invited@example.test', 'name' => 'Invited Person', 'roles' => ['FINANCE_MANAGER']]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->body()['id'];
        self::assertSame('staff.invited', $this->db()->fetchOne("SELECT action FROM audit_events WHERE target_id=? AND action='staff.invited'", [$id]));
        $token = static::getContainer()->get(TokenRepository::class)->issue($id, 'invite', 3600);
        $this->send('POST', '/auth/logout');
        $this->send('POST', '/auth/invitations/accept', ['token' => $token, 'password' => 'Testing-password-123!']);
        self::assertResponseIsSuccessful();
        $this->send('POST', '/auth/login', ['email' => 'invited@example.test', 'password' => 'Testing-password-123!']);
        self::assertResponseIsSuccessful();
        self::assertContains('finance.dashboard.view', $this->body()['permissions']);
    }

    public function testSuspensionRevokesExistingSession(): void
    {
        $id = $this->fixture('FINANCE_MANAGER');
        $this->login('FINANCE_MANAGER');
        $this->db()->executeStatement("UPDATE users SET status='suspended',session_version=session_version+1 WHERE id=?", [$id]);
        $this->client->request('GET', '/api/v1/admin/departments/finance');
        self::assertResponseStatusCodeSame(401);
    }

    public function testPasswordResetIsSingleUseAndRevokesSessions(): void
    {
        $id = $this->fixture();
        $this->login('CUSTOMER');
        $token = static::getContainer()->get(TokenRepository::class)->issue($id, 'reset', 3600);
        $this->send('POST', '/auth/password/reset', ['token' => $token, 'password' => 'New-testing-password-123!']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/me');
        self::assertResponseStatusCodeSame(401);
        $this->send('POST', '/auth/password/reset', ['token' => $token, 'password' => 'New-testing-password-123!']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testExpiredTokensCannotBeConsumed(): void
    {
        $id = $this->fixture();
        $token = static::getContainer()->get(TokenRepository::class)->issue($id, 'reset', -1);
        $this->send('POST', '/auth/password/reset', ['token' => $token, 'password' => 'New-testing-password-123!']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPermissionEscalationAndRoleMutationAreDenied(): void
    {
        $this->fixture('PLATFORM_ADMIN');
        $this->login('PLATFORM_ADMIN');
        $this->send('POST', '/admin/staff/invitations', ['email' => 'bad@example.test', 'name' => 'Bad Actor', 'roles' => ['SUPER_ADMIN']]);
        self::assertResponseStatusCodeSame(403);
        $this->send('PUT', '/admin/roles/FINANCE_MANAGER/permissions', ['permissions' => ['permissions.manage']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAuditListingAndRealDashboardCounts(): void
    {
        $this->fixture('SUPER_ADMIN');
        $this->fixture();
        $this->login('SUPER_ADMIN');
        $this->client->request('GET', '/api/v1/admin/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->body()['customerCount']);
        $this->client->request('GET', '/api/v1/admin/audit-events?action=auth.login');
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $this->body()['total']);
    }

    public function testDuplicateRegistrationAndUnknownResetUseGenericResponses(): void
    {
        $this->fixture();
        $data = ['email' => 'customer@example.test', 'name' => 'Test Person', 'password' => 'Testing-password-123!'];
        $this->send('POST', '/auth/register', $data);
        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM users'));
        $this->send('POST', '/auth/password/forgot', ['email' => 'unknown@example.test']);
        self::assertResponseStatusCodeSame(202);
        $unknown = $this->body();
        $this->send('POST', '/auth/password/forgot', ['email' => 'customer@example.test']);
        self::assertSame($unknown, $this->body());
    }

    public function testProfileOwnershipAndPasswordChange(): void
    {
        $this->fixture();
        $this->login('CUSTOMER');
        $this->send('PATCH', '/me', ['name' => 'Changed', 'status' => 'active']);
        self::assertResponseStatusCodeSame(400);
        $this->send('POST', '/me/password', ['currentPassword' => 'wrong', 'password' => 'New-testing-password-123!']);
        self::assertResponseStatusCodeSame(422);
        $this->send('POST', '/me/password', ['currentPassword' => 'Testing-password-123!', 'password' => 'New-testing-password-123!']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRoleUpdatesRevokeSessionsAndSystemRolesAreImmutable(): void
    {
        $this->fixture('SUPER_ADMIN');
        $this->login('SUPER_ADMIN');
        $this->send('PUT', '/admin/roles/SUPER_ADMIN/permissions', ['permissions' => []]);
        self::assertResponseStatusCodeSame(422);
        $this->send('PUT', '/admin/roles/FINANCE_MANAGER/permissions', ['permissions' => ['unknown.permission']]);
        self::assertResponseStatusCodeSame(422);
        $this->send('PUT', '/admin/roles/FINANCE_MANAGER/permissions', ['permissions' => ['finance.dashboard.view']]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_events WHERE action='role.permissions_changed'"));
    }

    public function testAuditFiltersAndPaginationRemainBounded(): void
    {
        $this->fixture('AUDITOR');
        $this->login('AUDITOR');
        $this->client->request('GET', '/api/v1/admin/audit-events?from=invalid');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/api/v1/admin/audit-events?from=2026-02-31');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/api/v1/admin/audit-events?action=auth.login&page=1');
        self::assertResponseIsSuccessful();
        self::assertSame(20, $this->body()['pageSize']);
        self::assertArrayNotHasKey('password_hash', $this->body()['items'][0]);
        self::assertMatchesRegularExpression('/T.*[+]00:00$/', $this->body()['items'][0]['created_at']);
    }

    public function testStatusAndRoleFilteringAndInvalidInvitationRollback(): void
    {
        $this->fixture('SUPER_ADMIN');
        $finance = $this->fixture('FINANCE_MANAGER');
        $this->login('SUPER_ADMIN');
        $this->send('POST', '/admin/staff/invitations', ['email' => 'invalid@example.test', 'name' => 'Invalid Person', 'roles' => ['NOT_A_ROLE']]);
        self::assertResponseStatusCodeSame(422);
        self::assertFalse($this->db()->fetchOne("SELECT id FROM users WHERE email='invalid@example.test'"));
        $this->send('PUT', '/admin/staff/'.$finance.'/roles', ['roles' => ['FINANCE_MANAGER', 'QA_ENGINEER']]);
        self::assertResponseIsSuccessful();
        $this->send('PATCH', '/admin/staff/'.$finance, ['status' => 'suspended']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/admin/staff?role=QA_ENGINEER&status=suspended');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->body()['total']);
        $this->send('PATCH', '/admin/staff/'.$finance, ['status' => 'active']);
        self::assertResponseIsSuccessful();
    }

    public function testTokenStorageAndFailedConsumptionAreAtomic(): void
    {
        $id = $this->fixture();
        $token = static::getContainer()->get(TokenRepository::class)->issue($id, 'reset', 3600);
        self::assertSame(hash('sha256', $token), $this->db()->fetchOne('SELECT token_hash FROM password_reset_tokens WHERE user_id = ?', [$id]));
        $this->send('POST', '/auth/password/reset', ['token' => $token, 'password' => 'short']);
        self::assertResponseStatusCodeSame(400);
        $this->send('POST', '/auth/password/reset', ['token' => $token, 'password' => 'A-valid-new-password!']);
        self::assertResponseIsSuccessful();
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM password_reset_tokens'));
    }

    public function testChangingOwnRolesRevokesCurrentSession(): void
    {
        $id = $this->fixture('SUPER_ADMIN');
        $other = $this->fixture('PLATFORM_ADMIN');
        $this->db()->insert('user_roles', ['user_id' => $other, 'role_code' => 'SUPER_ADMIN']);
        $this->login('SUPER_ADMIN');
        $this->send('PUT', '/admin/staff/'.$id.'/roles', ['roles' => ['AUDITOR']]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/admin/audit-events');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDemoAccountsAreRefusedOutsideDevelopment(): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:seed-demo-users'));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('only in the dev environment', $tester->getDisplay());
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testPreferredLocalePersistsWithoutRevokingTheSession(): void
    {
        $id = $this->fixture();
        $this->login('CUSTOMER');
        $version = $this->db()->fetchOne('SELECT session_version FROM users WHERE id = ?', [$id]);
        $this->send('PATCH', '/me', ['preferredLocale' => 'fr']);
        self::assertResponseIsSuccessful();
        self::assertSame('fr', $this->body()['preferredLocale']);
        $this->client->request('GET', '/api/v1/me');
        self::assertResponseIsSuccessful();
        self::assertSame('fr', $this->body()['preferredLocale']);
        self::assertSame($version, $this->db()->fetchOne('SELECT session_version FROM users WHERE id = ?', [$id]));
        $this->send('PATCH', '/me', ['preferredLocale' => 'xx']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('locale_invalid', $this->body()['code']);
        self::assertSame('fr', $this->db()->fetchOne('SELECT preferred_locale FROM users WHERE id = ?', [$id]));
    }

    public function testRegistrationAndInvitationStoreRequestedEmailLocale(): void
    {
        $this->send('POST', '/auth/register', ['email' => 'localized@example.test', 'name' => 'Localized Person', 'password' => 'Testing-password-123!', 'preferredLocale' => 'de']);
        self::assertResponseStatusCodeSame(202);
        self::assertSame('de', $this->db()->fetchOne("SELECT preferred_locale FROM users WHERE email='localized@example.test'"));
        $this->fixture('SUPER_ADMIN');
        $this->login('SUPER_ADMIN');
        $this->send('POST', '/admin/staff/invitations', ['email' => 'french@example.test', 'name' => 'Invited Person', 'roles' => ['QA_ENGINEER'], 'preferredLocale' => 'fr']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('fr', $this->body()['preferredLocale']);
    }

    public function testStableProblemCodesDoNotExposeAccountExistence(): void
    {
        $this->send('POST', '/auth/login', ['email' => 'missing@example.test', 'password' => 'unknown-password']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('credentials_invalid', $this->body()['code']);
        $this->send('POST', '/auth/register', ['email' => 'buyer@example.test', 'name' => 'Buyer', 'password' => 'Testing-password-123!', 'roles' => ['SUPER_ADMIN']]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('fields_invalid',$this->body()['code']);
    }
}
