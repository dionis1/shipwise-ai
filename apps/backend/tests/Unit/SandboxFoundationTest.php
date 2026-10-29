<?php

namespace App\Tests\Unit;

use App\CarrierIntegration\Domain\IntegrationFailure;
use App\CarrierIntegration\Domain\LocationSearch;
use App\CarrierIntegration\Infrastructure\SandboxConfiguration;
use App\CarrierIntegration\Infrastructure\SandboxTokens;
use App\CarrierIntegration\Infrastructure\SandboxTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class SandboxFoundationTest extends TestCase
{
    public function testNoProductionOrCiNetworkingEvenWithCredentials(): void
    {
        foreach (['prod', 'test', 'e2e'] as $environment) {
            $config = new SandboxConfiguration($environment, ['DPD_SANDBOX_ENABLED' => '1', 'DPD_DELIS_ID' => 'synthetic', 'DPD_PASSWORD' => 'synthetic']);
            self::assertTrue($config->configured('DPD'));
            self::assertFalse($config->enabled('DPD'));
        }
        self::assertFalse((new SandboxConfiguration('dev', []))->configured('UPS'));
        $this->expectExceptionMessage('not_configured');
        (new SandboxConfiguration('dev', []))->guard('DHL');
    }

    public function testFixedValidationUrlAndTransportBoundaries(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api-sandbox.dhl.com/parcel/de/shipping/v2/orders?validate=true', $url);
            self::assertTrue($options['verify_peer']);
            self::assertTrue($options['verify_host']);
            self::assertSame(0, $options['max_redirects']);
            self::assertSame(8.0, $options['max_duration']);

            return new MockResponse('{}');
        });
        (new SandboxTransport($client))->request('dhl_validate');
        $this->expectExceptionMessage('operation_unsupported');
        (new SandboxTransport($client))->request('https://localhost');
    }

    public static function failures(): iterable
    {
        yield [401, '{}', 'authentication_failed'];
        yield [403, '{}', 'pending_approval'];
        yield [429, '{}', 'rate_limited'];
        yield [503, 'secret upstream text', 'temporarily_unavailable'];
        yield [302, '', 'response_invalid'];
        yield [200, str_repeat('x', 262145), 'response_invalid'];
    }

    #[DataProvider('failures')]
    public function testSanitizedHttpFailures(int $status, string $body, string $reason): void
    {
        $this->expectExceptionMessage($reason);
        (new SandboxTransport(new MockHttpClient(new MockResponse($body, ['http_code' => $status]))))->request('dhl_version');
    }

    public function testTokensReuseExpireAndInvalidate(): void
    {
        $cache = new ArrayAdapter();
        $tokens = new SandboxTokens($cache, new LockFactory(new InMemoryStore()));
        $calls = 0;
        $authenticate = function () use (&$calls): array {
            ++$calls;

            return ['token' => 'synthetic-token-'.$calls, 'ttl' => 1];
        };
        self::assertSame('synthetic-token-1', $tokens->get('dpd', $authenticate));
        self::assertSame('synthetic-token-1', $tokens->get('dpd', $authenticate));
        $cache->clear();
        self::assertSame('synthetic-token-2', $tokens->get('dpd', $authenticate));
        $tokens->forget('dpd');
        self::assertSame('synthetic-token-3', $tokens->get('dpd', $authenticate));
    }

    public function testRejectInvalidSearchBeforeTransport(): void
    {
        $this->expectException(IntegrationFailure::class);
        new LocationSearch('DE', '10115', '<script>', 21);
    }

    public function testRejectMalformedJson(): void
    {
        $this->expectExceptionMessage('response_invalid');
        (new SandboxTransport(new MockHttpClient()))->json('{secret');
    }
}
