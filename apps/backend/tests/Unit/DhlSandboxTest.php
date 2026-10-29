<?php

namespace App\Tests\Unit;

use App\CarrierIntegration\Infrastructure\DhlAuthentication;
use App\CarrierIntegration\Infrastructure\DhlSandboxClient;
use App\CarrierIntegration\Infrastructure\SandboxConfiguration;
use App\CarrierIntegration\Infrastructure\SandboxTokens;
use App\CarrierIntegration\Infrastructure\SandboxTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class DhlSandboxTest extends TestCase
{
    private function client(MockHttpClient $http): DhlSandboxClient
    {
        $configuration = new SandboxConfiguration('dev', ['DHL_SANDBOX_ENABLED' => '1', 'DHL_CLIENT_ID' => 'synthetic-client', 'DHL_CLIENT_SECRET' => 'synthetic-secret', 'DHL_USERNAME' => 'synthetic-user', 'DHL_PASSWORD' => 'synthetic-password', 'DHL_BILLING_NUMBER' => '00000000000100', 'DHL_PROFILE' => 'SYNTHETIC']);
        $transport = new SandboxTransport($http);
        $tokens = new SandboxTokens(new ArrayAdapter(), new LockFactory(new InMemoryStore()));

        return new DhlSandboxClient($configuration, $transport, new DhlAuthentication($configuration, $transport, $tokens));
    }

    private function token(): MockResponse
    {
        return new MockResponse('{"access_token":"synthetic-token","token_type":"Bearer","expires_in":1799}');
    }

    public function testDocumentedPasswordGrantAndOneRefreshOnHelloRejection(): void
    {
        $calls = [];
        $responses = [new MockResponse('{"amp":{"version":"2.4.0"}}'), $this->token(), new MockResponse('', ['http_code' => 401]), $this->token(), new MockResponse('{"accesstoken-info":{"expires_in_s":1700}}')];
        $client = $this->client(new MockHttpClient(function ($method, $url, $options) use (&$calls, &$responses) {
            $calls[] = $url;
            if (str_ends_with($url, '/token')) {
                parse_str($options['body'], $form);
                self::assertSame('password', $form['grant_type']);
                self::assertSame('synthetic-client', $form['client_id']);
                self::assertArrayNotHasKey('refresh_token', $form);
            }

            return array_shift($responses);
        }));
        self::assertSame('2.4.0', $client->check());
        self::assertCount(5, $calls);
    }

    public function testValidationUsesOnlyFixedDryRunFixture(): void
    {
        $count = 0;
        $http = new MockHttpClient(function ($method, $url, $options) use (&$count) {
            if (++$count === 1) {
                return $this->token();
            }
            self::assertSame('POST', $method);
            self::assertStringEndsWith('/orders?validate=true', $url);
            $data = json_decode($options['body'], true);
            self::assertCount(1, $data['shipments']);
            self::assertSame('DEU', $data['shipments'][0]['consignee']['country']);
            self::assertArrayNotHasKey('services', $data['shipments'][0]);

            return new MockResponse('{"items":[{"sstatus":{"status":200}}]}');
        });
        self::assertTrue($this->client($http)->validateFixture());
    }

    public function testValidationRejectionDoesNotExposeProviderPayload(): void
    {
        self::assertFalse($this->client(new MockHttpClient([$this->token(), new MockResponse('{"items":[{"sstatus":{"status":400},"validationMessages":[{"validationMessage":"private provider detail"}]}]}', ['http_code' => 400])]))->validateFixture());
    }

    public function testLabelsAreRejectedAndNeverReturned(): void
    {
        $this->expectExceptionMessage('response_invalid');
        $this->client(new MockHttpClient([$this->token(), new MockResponse('{"items":[{"sstatus":{"status":200},"label":{"url":"https://example.invalid/private"}}]}')]))->validateFixture();
    }

    public function testIncorrectCredentialsDoNotRetryValidation(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"private credentials"}', ['http_code' => 401]));
        $this->expectExceptionMessage('authentication_failed');
        $this->client($http)->validateFixture();
    }

    public function testInvalidVersionCannotEstablishConnectivity(): void
    {
        $this->expectExceptionMessage('response_invalid');
        $this->client(new MockHttpClient(new MockResponse('{"amp":"unexpected"}')))->check();
    }
}
