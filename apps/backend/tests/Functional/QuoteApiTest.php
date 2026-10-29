<?php

namespace App\Tests\Functional;

use App\Shared\Domain\Identifier;
use App\Tests\Unit\QuotingTest;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class QuoteApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        if (!str_ends_with((string) $this->db()->getDatabase(), '_test')) {
            throw new \LogicException('Isolated database required.');
        }
        $this->db()->executeStatement('TRUNCATE shipping_quotes,users,audit_events CASCADE');
        $this->db()->executeStatement("UPDATE carriers SET enabled=(code='DEV_POST')");
        $this->db()->executeStatement('UPDATE carrier_services SET enabled=true');
        $this->db()->executeStatement("DELETE FROM carrier_tariffs WHERE version<>'dev-tariff-1'");
        $this->db()->executeStatement('UPDATE carrier_tariffs SET active=true');
        $this->db()->executeStatement("DELETE FROM pricing_rules WHERE version<>'dev-pricing-1'");
        $this->db()->executeStatement('UPDATE pricing_rules SET active=true');
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function send(string $method, string $path, array $data = [], string $key = 'test-idempotency-key-0001'): array
    {
        $this->client->request('GET', '/api/v1/auth/csrf');
        $token = $this->body()['token'];
        $this->client->request($method, '/api/v1'.$path, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token, 'HTTP_IDEMPOTENCY_KEY' => $key], json_encode($data, JSON_THROW_ON_ERROR));

        return $this->body();
    }

    private function login(string $role): string
    {
        $id = Identifier::new();
        $this->db()->insert('users', ['id' => $id, 'email' => strtolower($role).'@quotes.test', 'name' => 'Quote Tester', 'password_hash' => password_hash('Testing-password-123!', PASSWORD_BCRYPT, ['cost' => 4]), 'kind' => $role === 'CUSTOMER' ? 'customer' : 'staff', 'status' => 'active', 'created_at' => gmdate('c')]);
        $this->db()->insert('user_roles', ['user_id' => $id, 'role_code' => $role]);
        $this->send('POST', '/auth/login', ['email' => strtolower($role).'@quotes.test', 'password' => 'Testing-password-123!']);
        self::assertResponseIsSuccessful();

        return $id;
    }

    public function testGuestPricesScopedAccessAndIdempotency(): void
    {
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        self::assertResponseStatusCodeSame(201);
        self::assertCount(2, $quote['offers']);
        $standard = array_values(array_filter($quote['offers'], fn ($o) => $o['serviceCode'] === 'DEV_STANDARD'))[0];
        self::assertSame(803, $standard['price']['totalMinor']);
        self::assertFalse($standard['bookable']);
        $again = $this->send('POST', '/quotes', QuotingTest::shipment());
        self::assertSame($quote['id'], $again['id']);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM shipping_quotes'));
        $data = QuotingTest::shipment();
        $data['weightGrams'] = 1002;
        $this->send('POST', '/quotes', $data);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id']);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id'], [], [], ['HTTP_X_QUOTE_TOKEN' => $quote['accessToken']]);
        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('accessToken', $this->body());
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id'].'/offers/'.$standard['id'], [], [], ['HTTP_X_QUOTE_TOKEN' => $quote['accessToken']]);
        self::assertResponseIsSuccessful();
        $this->client->restart();
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id'], [], [], ['HTTP_X_QUOTE_TOKEN' => $quote['accessToken']]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerIsolationAndCsrf(): void
    {
        $this->login('CUSTOMER');
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        self::assertArrayNotHasKey('accessToken', $quote);
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id']);
        self::assertResponseIsSuccessful();
        $this->send('POST', '/auth/logout');
        $this->login('SUPER_ADMIN');
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id']);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/api/v1/quotes', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(QuotingTest::shipment()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidParcelAndUnsupportedRoute(): void
    {
        $data = QuotingTest::shipment();
        $data['weightGrams'] = 0;
        $this->send('POST', '/quotes', $data);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('parcel_invalid', $this->body()['code']);
        $data = QuotingTest::shipment();
        $data['originCountry'] = 'FR';
        $quote = $this->send('POST', '/quotes', $data);
        self::assertSame([], $quote['offers']);
        self::assertContains('route_unsupported', $quote['outcomes'][0]['reasons']);
    }

    public function testLeastPrivilegeCatalogConfigurationAndAuditing(): void
    {
        $this->login('FINANCE_MANAGER');
        $this->client->request('GET', '/api/v1/admin/carriers');
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', '/auth/logout');
        $this->login('CARRIER_MANAGER');
        $this->client->request('GET', '/api/v1/admin/carriers');
        self::assertResponseIsSuccessful();
        $this->send('PATCH', '/admin/carriers/DHL', ['enabled' => true]);
        self::assertResponseStatusCodeSame(422);
        $this->send('PATCH', '/admin/carriers/DEV_POST', ['enabled' => false, 'serviceCode' => 'DEV_STANDARD']);
        self::assertResponseIsSuccessful();
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        self::assertCount(1, $quote['offers']);
        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_events WHERE action='carrier.configuration_changed'"));
        $this->send('POST', '/admin/pricing-rules', []);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTariffVersionsPartialFailureAndImmutableOffers(): void
    {
        $this->login('SUPER_ADMIN');
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        $original = $quote['offers'];
        $new = ['serviceCode' => 'DEV_STANDARD', 'version' => 'expensive-v2', 'baseMinor' => 1000000, 'perKgMinor' => 1000000, 'surchargeMinor' => 0, 'currency' => 'EUR', 'effectiveFrom' => '2026-01-01T00:00:00Z'];
        $this->send('POST', '/admin/tariffs', $new);
        self::assertResponseStatusCodeSame(201);
        $this->send('POST', '/admin/tariffs', $new);
        self::assertResponseStatusCodeSame(422);
        $data = QuotingTest::shipment();
        $data['weightGrams'] = 15000;
        $partial = $this->send('POST', '/quotes', $data, 'different-idempotency-02');
        self::assertCount(1, $partial['offers']);
        self::assertContains('invalid_response', array_column($partial['outcomes'], 'status'));
        $this->client->request('GET', '/api/v1/quotes/'.$quote['id']);
        self::assertEquals($original, $this->body()['offers']);
        try {
            $this->db()->executeStatement("UPDATE shipping_quote_offers SET snapshot='{}'");
            self::fail('Snapshot update must fail.');
        } catch (\Doctrine\DBAL\Exception $e) {
            self::assertStringContainsString('immutable', $e->getMessage());
        }
    }

    public function testServicePricingSupersedesDefaultAndUndefinedTaxFailsClosed(): void
    {
        $this->login('SUPER_ADMIN');
        $rule = ['serviceCode' => 'DEV_STANDARD', 'version' => 'service-v2', 'currency' => 'EUR', 'fixedMarginMinor' => 0, 'minimumMarginMinor' => 0, 'feeMinor' => 0, 'marginBasisPoints' => 0, 'taxBasisPoints' => 0];
        $this->send('POST', '/admin/pricing-rules', $rule);
        self::assertResponseStatusCodeSame(201);
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        $standard = array_values(array_filter($quote['offers'], fn ($o) => $o['serviceCode'] === 'DEV_STANDARD'))[0];
        self::assertSame(500, $standard['price']['totalMinor']);
        self::assertSame('service-v2', $standard['price']['pricingVersion']);
        $this->db()->executeStatement('UPDATE pricing_rules SET active=false');
        $empty = $this->send('POST', '/quotes', QuotingTest::shipment(), 'another-idempotency-003');
        self::assertSame([], $empty['offers']);
        self::assertSame('no_rate_source', $empty['outcomes'][0]['status']);
    }

    public function testExpiredSelectionAndRetention(): void
    {
        $this->login('CUSTOMER');
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        $row = $this->db()->fetchAssociative('SELECT * FROM shipping_quotes WHERE id=?', [$quote['id']]);
        $id = Identifier::new();
        $row['id'] = $id;
        $row['idempotency_key'] = 'expired';
        $row['created_at'] = gmdate('c', time() - 3600);
        $row['expires_at'] = gmdate('c', time() - 60);
        $this->db()->insert('shipping_quotes', $row);
        $offer = $quote['offers'][0];
        $offer['id'] = Identifier::new();
        $offer['quoteId'] = $id;
        $this->db()->insert('shipping_quote_offers', ['id' => $offer['id'], 'quote_id' => $id, 'snapshot' => json_encode($offer), 'selling_minor' => $offer['price']['totalMinor'], 'currency' => $offer['price']['currency']]);
        $this->client->request('GET', '/api/v1/quotes/'.$id);
        self::assertTrue($this->body()['expired']);
        $this->client->request('GET', '/api/v1/quotes/'.$id.'/offers/'.$offer['id']);
        self::assertResponseStatusCodeSame(410);
    }

    public function testMoneyColumnsAndForeignKeysRejectInconsistentSnapshots(): void
    {
        $this->login('CUSTOMER');
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        $offer = $quote['offers'][0];
        try {
            $this->db()->insert('shipping_quote_offers', ['id' => Identifier::new(), 'quote_id' => $quote['id'], 'snapshot' => json_encode($offer), 'selling_minor' => $offer['price']['totalMinor'] + 1, 'currency' => 'EUR']);
            self::fail('Mismatched price must fail.');
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            self::assertSame('23514', $e->getSQLState());
        }
        try {
            $this->db()->insert('shipping_quote_offers', ['id' => Identifier::new(), 'quote_id' => Identifier::new(), 'snapshot' => json_encode($offer), 'selling_minor' => $offer['price']['totalMinor'], 'currency' => 'EUR']);
            self::fail('Missing quote FK must fail.');
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            self::assertSame('23503', $e->getSQLState());
        }
    }

    public function testRetentionPurgeAndLimiterConfiguration(): void
    {
        $this->login('CUSTOMER');
        $quote = $this->send('POST', '/quotes', QuotingTest::shipment());
        $row = $this->db()->fetchAssociative('SELECT * FROM shipping_quotes WHERE id=?', [$quote['id']]);
        $row['id'] = Identifier::new();
        $row['idempotency_key'] = hash('sha256', 'retained-expired-key-01');
        $row['created_at'] = gmdate('c', time() - 172800);
        $row['expires_at'] = gmdate('c', time() - 171900);
        $row['purge_at'] = gmdate('c', time() - 86400);
        $this->db()->insert('shipping_quotes', $row);
        $this->send('POST', '/quotes', QuotingTest::shipment(), 'retained-expired-key-01');
        self::assertResponseStatusCodeSame(404);
        $command = new \Symfony\Component\Console\Tester\CommandTester(static::getContainer()->get(\App\Quoting\Infrastructure\PurgeQuotesCommand::class));
        $command->execute([]);
        self::assertStringContainsString('Purged quotes: 1', $command->getDisplay());
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM shipping_quotes'));
        $limiter = static::getContainer()->get('limiter.quotes')->create('isolated-limit-boundary');
        self::assertTrue($limiter->consume(20)->isAccepted());
        self::assertFalse($limiter->consume()->isAccepted());
    }

    public function testHistoricalTariffConfigurationCannotBeEdited(): void
    {
        $original = $this->db()->fetchOne("SELECT configuration FROM carrier_tariffs WHERE service_code='DEV_STANDARD' AND version='dev-tariff-1'");
        try {
            $this->db()->executeStatement("UPDATE carrier_tariffs SET configuration='{}' WHERE service_code='DEV_STANDARD' AND version='dev-tariff-1'");
            self::fail('Tariff versions must be immutable.');
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            self::assertSame('P0001', $e->getSQLState());
        }
        self::assertSame($original, $this->db()->fetchOne("SELECT configuration FROM carrier_tariffs WHERE service_code='DEV_STANDARD' AND version='dev-tariff-1'"));
    }
}
