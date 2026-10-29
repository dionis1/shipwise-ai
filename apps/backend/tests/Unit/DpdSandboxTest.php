<?php

namespace App\Tests\Unit;

use App\CarrierIntegration\Domain\IntegrationFailure;
use App\CarrierIntegration\Domain\LocationSearch;
use App\CarrierIntegration\Infrastructure\DpdAuthentication;
use App\CarrierIntegration\Infrastructure\DpdLocationProvider;
use App\CarrierIntegration\Infrastructure\DpdXml;
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

final class DpdSandboxTest extends TestCase
{
    private function provider(MockHttpClient $client): DpdLocationProvider
    {
        $configuration = new SandboxConfiguration('dev', ['DPD_SANDBOX_ENABLED' => '1', 'DPD_DELIS_ID' => 'synthetic', 'DPD_PASSWORD' => 'synthetic<&password']);
        $transport = new SandboxTransport($client);
        $xml = new DpdXml();
        $auth = new DpdAuthentication($configuration, $transport, new SandboxTokens(new ArrayAdapter(), new LockFactory(new InMemoryStore())), $xml);

        return new DpdLocationProvider($configuration, $transport, $auth, $xml);
    }

    public static function envelope(string $body): string
    {
        return '<s:Envelope xmlns:s="'.DpdXml::SOAP.'"><s:Body>'.$body.'</s:Body></s:Envelope>';
    }

    private function login(): MockResponse
    {
        return new MockResponse(self::envelope('<l:getAuthResponse xmlns:l="'.DpdXml::LOGIN.'"><return><authToken>synthetic-token</authToken></return></l:getAuthResponse>'));
    }

    private function shops(string $body): MockResponse
    {
        return new MockResponse(self::envelope('<p:findParcelShopsResponse xmlns:p="'.DpdXml::SHOPS.'">'.$body.'</p:findParcelShopsResponse>'));
    }

    private function fault(): MockResponse
    {
        return new MockResponse(self::envelope('<s:Fault><faultstring>private provider message</faultstring><detail><a:authenticationFault xmlns:a="'.DpdXml::AUTH.'"><errorCode>LOGIN_8</errorCode></a:authenticationFault></detail></s:Fault>'), ['http_code' => 500]);
    }

    public function testDocumentedSoapAuthenticationAndSparseLocationMapping(): void
    {
        $calls = 0;
        $provider = $this->provider(new MockHttpClient(function ($method, $url, $options) use (&$calls) {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertStringNotContainsString('storeOrders', $options['body']);
            if (1 === $calls) {
                self::assertStringEndsWith('/LoginService/V2_0', $url);
                self::assertStringContainsString('synthetic&lt;&amp;password', $options['body']);

                return $this->login();
            }
            self::assertStringEndsWith('/ParcelShopFinderService/V5_0', $url);
            self::assertStringContainsString('<authToken>synthetic-token</authToken>', $options['body']);
            self::assertStringContainsString('<limit>2</limit>', $options['body']);
            self::assertStringNotContainsString('<street>', $options['body']);

            return $this->shops('<parcelShop><parcelShopId>101</parcelShopId><company>Synthetic Test Shop</company><street>Test Street</street><houseNo>2</houseNo><country>DE</country><zipCode>10115</zipCode><city>Berlin</city><latitude>52.5</latitude><longitude>13.4</longitude><services><service><code>100</code><available>true</available></service><service><code>200</code><available>false</available></service></services></parcelShop><parcelShop><pudoId>synthetic-102</pudoId></parcelShop>');
        }));
        $shops = $provider->locations(new LocationSearch('DE', '10115', '', 2));
        self::assertCount(2, $shops);
        self::assertSame('101', $shops[0]->id);
        self::assertSame('Test Street 2', $shops[0]->address);
        self::assertSame(52.5, $shops[0]->latitude);
        self::assertSame(['100'], $shops[0]->services);
        self::assertSame('dpd_sandbox', $shops[0]->source);
        self::assertNull($shops[1]->latitude);
        self::assertNull($shops[1]->name);
        self::assertSame([], $shops[1]->services);
    }

    public function testTypedFaultReauthenticatesOnce(): void
    {
        $http = new MockHttpClient([$this->login(), $this->fault(), $this->login(), $this->shops('')]);
        self::assertSame([], $this->provider($http)->locations(new LocationSearch('DE', '10115')));
        self::assertSame(4, $http->getRequestsCount());
    }

    public function testRepeatedAuthFaultStopsWithoutLeakingMessage(): void
    {
        $this->expectExceptionMessage('authentication_failed');
        $this->provider(new MockHttpClient([$this->login(), $this->fault(), $this->login(), $this->fault()]))->locations(new LocationSearch('DE', '10115'));
    }

    public static function unsafeXml(): iterable
    {
        yield ['<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>'];
        yield ['<!DOCTYPE foo SYSTEM "https://example.invalid/private"><foo/>'];
        yield ['<unclosed>'];
        yield [self::envelope('<p:findParcelShopsResponse xmlns:p="urn:wrong"/>')];
        yield ['<Envelope><Body><findParcelShopsResponse/></Body></Envelope>'];
    }

    #[DataProvider('unsafeXml')]
    public function testRejectMalformedAndExternalEntityResponses(string $xml): void
    {
        $this->expectExceptionMessage('response_invalid');
        (new DpdXml())->response($xml, DpdXml::SHOPS, 'findParcelShopsResponse', 200);
    }

    public function testMissingIdAndInvalidCoordinateFailClosed(): void
    {
        $this->expectException(IntegrationFailure::class);
        $this->provider(new MockHttpClient([$this->login(), $this->shops('<parcelShop><parcelShopId>1</parcelShopId><latitude>900</latitude></parcelShop>')]))->locations(new LocationSearch('DE', '10115'));
    }

    public function testUnconfiguredDoesNotMakeHttpRequests(): void
    {
        $http = new MockHttpClient();
        $config = new SandboxConfiguration('dev', []);
        $xml = new DpdXml();
        $transport = new SandboxTransport($http);
        $provider = new DpdLocationProvider($config, $transport, new DpdAuthentication($config, $transport, new SandboxTokens(new ArrayAdapter(), new LockFactory(new InMemoryStore())), $xml), $xml);
        try {
            $provider->locations(new LocationSearch('DE', '10115'));
            self::fail('Expected missing configuration.');
        } catch (IntegrationFailure $failure) {
            self::assertSame('not_configured', $failure->reason);
            self::assertSame(0, $http->getRequestsCount());
        }
    }
}
