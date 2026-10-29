<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Domain\IntegrationFailure;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Deliberately outside the profiler and request/response logging decorators. */
final class SandboxTransport
{
    private const ENDPOINTS = [
        'dhl_token' => ['POST', 'https://api-sandbox.dhl.com/parcel/de/account/auth/ropc/v1/token'],
        'dhl_hello' => ['GET', 'https://api-sandbox.dhl.com/parcel/de/account/auth/ropc/v1/hello'],
        'dhl_version' => ['GET', 'https://api-sandbox.dhl.com/parcel/de/shipping/v2/'],
        'dhl_validate' => ['POST', 'https://api-sandbox.dhl.com/parcel/de/shipping/v2/orders?validate=true'],
        'dpd_login' => ['POST', 'https://public-ws-stage.dpd.com/services/LoginService/V2_0'],
        'dpd_locations' => ['POST', 'https://public-ws-stage.dpd.com/services/ParcelShopFinderService/V5_0'],
    ];

    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null, private ?RequestStack $requests = null)
    {
        $this->client = $client ?? HttpClient::create();
    }

    /** @param array<string,string> $headers
     * @return array{status:int,body:string} */
    public function request(string $operation, #[\SensitiveParameter] array $headers = [], #[\SensitiveParameter] string $body = ''): array
    {
        [$method, $url] = self::ENDPOINTS[$operation] ?? throw new IntegrationFailure('operation_unsupported');
        $response = null;
        try {
            $response = $this->client->request($method, $url, [
                'headers' => ['X-Request-ID' => (string) ($this->requests?->getCurrentRequest()?->attributes->get('correlationId') ?? bin2hex(random_bytes(16)))] + $headers,
                'body' => $body,
                'timeout' => 4.0,
                'max_duration' => 8.0,
                'max_redirects' => 0,
                'buffer' => false,
                'verify_peer' => true,
                'verify_host' => true,
                'extra' => ['trace_content' => false],
            ]);
            $status = $response->getStatusCode();
            if (in_array($status, [401, 403, 429], true)) {
                throw new IntegrationFailure(match ($status) {
                    401 => 'authentication_failed', 403 => 'pending_approval', 429 => 'rate_limited'
                });
            }
            $content = '';
            foreach ($this->client->stream($response) as $chunk) {
                $content .= $chunk->getContent();
                if (strlen($content) > 262144) {
                    throw new IntegrationFailure('response_invalid');
                }
            }
            // DPD returns typed SOAP faults with HTTP 500; the XML layer must classify those.
            if ($status >= 500 && !(500 === $status && str_starts_with($operation, 'dpd_'))) {
                throw new IntegrationFailure('temporarily_unavailable');
            }
            if (!in_array($status, [200, 201, 400, 500], true)) {
                throw new IntegrationFailure('response_invalid');
            }

            return ['status' => $status, 'body' => $content];
        } catch (TransportExceptionInterface) {
            // Never chain transport exceptions: they can contain URLs, tokens and response bodies.
            throw new IntegrationFailure('temporarily_unavailable');
        } finally {
            $response?->cancel();
        }
    }

    /** @return array<string,mixed> */
    public function json(#[\SensitiveParameter] string $body): array
    {
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new IntegrationFailure('response_invalid');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new IntegrationFailure('response_invalid');
        }

        return $data;
    }
}
