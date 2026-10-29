<?php

namespace App\Tests\Functional;

use App\CarrierIntegration\Infrastructure\SandboxConfiguration;
use App\CarrierIntegration\Infrastructure\SandboxTransport;
use App\Shared\Domain\Identifier;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SandboxApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        if (!str_ends_with((string) $this->db()->getDatabase(), '_test')) {
            throw new \LogicException('Isolated database required.');
        }
        $this->db()->executeStatement('TRUNCATE users,audit_events CASCADE');
        $this->db()->executeStatement('UPDATE carrier_sandboxes SET enabled=false,checked_at=NULL,result=NULL,operation=NULL,configuration_hash=NULL');
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR);
    }

    private function send(string $method, string $path, array $data): array
    {
        $this->client->request('GET', '/api/v1/auth/csrf');
        $token = $this->body()['token'];
        $this->client->request($method, '/api/v1'.$path, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], json_encode($data, JSON_THROW_ON_ERROR));

        return $this->body();
    }

    private function login(string $role): void
    {
        $id = Identifier::new();
        $this->db()->insert('users', ['id' => $id, 'email' => 'sandbox@test.invalid', 'name' => 'Sandbox Tester', 'password_hash' => password_hash('Testing-password-123!', PASSWORD_BCRYPT, ['cost' => 4]), 'kind' => 'staff', 'status' => 'active', 'created_at' => gmdate('c')]);
        $this->db()->insert('user_roles', ['user_id' => $id, 'role_code' => $role]);
        $this->send('POST', '/auth/login', ['email' => 'sandbox@test.invalid', 'password' => 'Testing-password-123!']);
        self::assertResponseIsSuccessful();
    }

    public function testPublicMissingConfigurationAndGlsSpecificationAreHonest(): void
    {
        $this->client->request('GET', '/api/v1/parcel-shops?country=DE&postalCode=10115');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->body()['items']);
        self::assertSame([['carrier' => 'DPD', 'status' => 'not_configured'], ['carrier' => 'GLS', 'status' => 'specification_unavailable']], $this->body()['outcomes']);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertNotEmpty($this->client->getResponse()->headers->get('X-Request-ID'));
    }

    public function testRejectUnsupportedParametersAndArrays(): void
    {
        foreach (['country=ZZ&postalCode=10115', 'country=DE&postalCode=10115&url=https://example.invalid', 'country=DE&postalCode[]=10115', 'country=DE&postalCode=10115&limit=21', 'country=DE&postalCode=10115&carrier=DHL', 'country=DE&postalCode=10115&city=%3Cscript%3E'] as $query) {
            $this->client->request('GET', '/api/v1/parcel-shops?'.$query);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('search_invalid', $this->body()['code']);
        }
    }

    public function testPublicRateLimit(): void
    {
        // BrowserKit resets cache pools between requests; preserve limiter state with an isolated storage.
        static::getContainer()->set('limiter.parcel_shops', new \Symfony\Component\RateLimiter\RateLimiterFactory(['id' => 'parcel_shops', 'policy' => 'sliding_window', 'limit' => 15, 'interval' => '15 minutes'], new \Symfony\Component\RateLimiter\Storage\InMemoryStorage()));
        for ($i = 0; $i < 16; ++$i) {
            $this->client->request('GET', '/api/v1/parcel-shops?country=DE&postalCode=10115');
        }
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }

    public function testGuestCannotReadDiagnosticsOrWrite(): void
    {
        $this->client->request('GET', '/api/v1/admin/carrier-sandboxes');
        self::assertResponseStatusCodeSame(401);
        $this->send('POST', '/admin/carrier-sandboxes/DHL/check', ['operation' => 'authentication']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testFinanceCannotTestOrConfigure(): void
    {
        $this->login('FINANCE_MANAGER');
        $this->send('PATCH', '/admin/carrier-sandboxes/DPD', ['enabled' => false]);
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', '/admin/carrier-sandboxes/DHL/check', ['operation' => 'validation']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminMissingConfigCsrfAndAuditedDisabledSwitch(): void
    {
        $this->login('CARRIER_MANAGER');
        $this->client->request('GET', '/api/v1/admin/carrier-sandboxes');
        self::assertResponseIsSuccessful();
        $items = $this->body()['items'];
        self::assertSame('not_configured', $items[0]['status']);
        self::assertFalse($items[0]['liveApproved']);
        self::assertSame('specification_unavailable', $items[2]['status']);
        self::assertSame('UPS', $items[3]['carrier']);
        self::assertSame([], $items[3]['capabilities']);
        $this->client->request('PATCH', '/api/v1/admin/carrier-sandboxes/DPD', [], [], ['CONTENT_TYPE' => 'application/json'], '{"enabled":false}');
        self::assertResponseStatusCodeSame(403);
        $this->send('PATCH', '/admin/carrier-sandboxes/DPD', ['enabled' => true]);
        self::assertResponseStatusCodeSame(409);
        $this->send('PATCH', '/admin/carrier-sandboxes/DPD', ['enabled' => false]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_events WHERE action='carrier.sandbox_configuration_changed'"));
        $result = $this->send('POST', '/admin/carrier-sandboxes/DHL/check', ['operation' => 'authentication']);
        self::assertSame('not_configured', $result['status']);
        $this->send('POST', '/admin/carrier-sandboxes/GLS/check', ['operation' => 'authentication']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(0, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM carriers WHERE code IN ('DHL','DPD','GLS','UPS') AND enabled=true"));
    }

    public function testExplicitMockedSandboxCheckDiscoveryAndHealthInvalidation(): void
    {
        $configuration = ['DPD_SANDBOX_ENABLED' => '1', 'DPD_DELIS_ID' => 'synthetic-user', 'DPD_PASSWORD' => 'synthetic-secret'];
        static::getContainer()->set(SandboxConfiguration::class, new SandboxConfiguration('dev', $configuration));
        $http = new MockHttpClient([new MockResponse('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><l:getAuthResponse xmlns:l="http://dpd.com/common/service/types/LoginService/2.0"><return><authToken>synthetic-token</authToken></return></l:getAuthResponse></s:Body></s:Envelope>'), new MockResponse('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><p:findParcelShopsResponse xmlns:p="http://dpd.com/common/service/types/ParcelShopFinderService/5.0"><parcelShop><parcelShopId>101</parcelShopId><company>Synthetic API Shop</company></parcelShop></p:findParcelShopsResponse></s:Body></s:Envelope>')]);
        static::getContainer()->set(SandboxTransport::class, new SandboxTransport($http));
        $this->login('CARRIER_MANAGER');
        $this->send('PATCH', '/admin/carrier-sandboxes/DPD', ['enabled' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $http->getRequestsCount());
        $result = $this->send('POST', '/admin/carrier-sandboxes/DPD/check', ['operation' => 'authentication']);
        self::assertSame('sandbox_connection_verified', $result['status']);
        self::assertSame(1, $http->getRequestsCount());
        $this->client->request('GET', '/api/v1/admin/carrier-sandboxes');
        self::assertSame('sandbox_connection_verified', $this->body()['items'][1]['status']);
        self::assertStringNotContainsString('synthetic-secret', $this->client->getResponse()->getContent());
        $checkedAt = $this->db()->fetchOne("SELECT checked_at FROM carrier_sandboxes WHERE code='DPD'");
        $this->client->request('GET', '/api/v1/parcel-shops?country=DE&postalCode=10115&carrier=DPD');
        self::assertResponseIsSuccessful();
        self::assertSame('101', $this->body()['items'][0]['id']);
        self::assertSame('dpd_sandbox', $this->body()['items'][0]['source']);
        self::assertNull($this->body()['items'][0]['latitude']);
        self::assertSame([], $this->body()['items'][0]['services']);
        self::assertSame([['carrier' => 'DPD', 'status' => 'available']], $this->body()['outcomes']);
        self::assertSame(2, $http->getRequestsCount());
        self::assertSame($checkedAt, $this->db()->fetchOne("SELECT checked_at FROM carrier_sandboxes WHERE code='DPD'"));
        $this->db()->executeStatement("UPDATE carrier_sandboxes SET checked_at=CURRENT_TIMESTAMP - INTERVAL '11 minutes' WHERE code='DPD'");
        $this->client->request('GET', '/api/v1/admin/carrier-sandboxes');
        self::assertSame('credentials_configured', $this->body()['items'][1]['status']);
        self::assertNull($this->body()['items'][1]['checkedAt']);
        self::assertStringNotContainsString('synthetic-secret', (string) $this->db()->fetchOne("SELECT context::text FROM audit_events WHERE action='carrier.sandbox_checked' LIMIT 1"));
    }
}
