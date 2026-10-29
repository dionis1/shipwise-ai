<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Application\CarrierShipmentValidator;
use App\CarrierIntegration\Domain\IntegrationFailure;

final class DhlSandboxClient implements CarrierShipmentValidator
{
    public function __construct(private SandboxConfiguration $configuration, private SandboxTransport $transport, private DhlAuthentication $authentication)
    {
    }

    /** Public version plus authenticated hello; raw token metadata is discarded. */
    public function check(): string
    {
        $this->configuration->guard('DHL');
        $versionResponse = $this->transport->request('dhl_version');
        if (200 !== $versionResponse['status']) {
            throw new IntegrationFailure('response_invalid');
        }
        $version = $this->transport->json($versionResponse['body'])['amp']['version'] ?? null;
        if (!is_string($version) || !preg_match('/^[A-Za-z0-9._-]{1,40}$/D', $version)) {
            throw new IntegrationFailure('response_invalid');
        }
        $this->hello();

        return $version;
    }

    private function hello(bool $retry = true): void
    {
        try {
            $response = $this->transport->request('dhl_hello', ['Authorization' => 'Bearer '.$this->authentication->token()]);
            if (200 !== $response['status']) {
                throw new IntegrationFailure('response_invalid');
            }
            $this->transport->json($response['body']);
        } catch (IntegrationFailure $error) {
            if ('authentication_failed' !== $error->reason || !$retry) {
                throw $error;
            }
            $this->authentication->invalidate();
            $this->hello(false);
        }
    }

    public function validateFixture(): bool
    {
        $this->configuration->guard('DHL');
        $billing = $this->configuration->value('DHL_BILLING_NUMBER');
        $profile = $this->configuration->value('DHL_PROFILE');
        if (!preg_match('/^[A-Za-z0-9]{10}01[A-Za-z0-9]{2}$/D', $billing) || '' === $profile || strlen($profile) > 35) {
            throw new IntegrationFailure('not_configured');
        }
        // Synthetic fixture only. The transport pins validate=true and never retries this operation.
        $address = ['name1' => 'ShipWise Sandbox Test', 'addressStreet' => 'Musterstrasse', 'addressHouse' => '1', 'postalCode' => '53113', 'city' => 'Bonn', 'country' => 'DEU'];
        $body = json_encode(['profile' => $profile, 'shipments' => [['product' => 'V01PAK', 'billingNumber' => $billing, 'shipper' => $address, 'consignee' => $address, 'details' => ['weight' => ['uom' => 'g', 'value' => 1000]]]]], JSON_THROW_ON_ERROR);
        $response = $this->transport->request('dhl_validate', ['Authorization' => 'Bearer '.$this->authentication->token(), 'Content-Type' => 'application/json', 'Accept' => 'application/json'], $body);
        $data = $this->transport->json($response['body']);
        $item = $data['items'][0] ?? null;
        $status = $item['sstatus']['status'] ?? $item['sstatus']['statusCode'] ?? null;
        if (!is_array($item) || !in_array($status, [200, 400], true) || count($data['items']) !== 1 || array_intersect(array_keys($item), ['label', 'returnLabel', 'customsDoc', 'codLabel', 'shipmentNo', 'returnShipmentNo'])) {
            throw new IntegrationFailure('response_invalid');
        }

        $messages = $item['validationMessages'] ?? [];
        if (!is_array($messages) || !array_is_list($messages) || array_any($messages, static fn (mixed $message): bool => !is_array($message) || !is_string($message['validationState'] ?? null))) {
            throw new IntegrationFailure('response_invalid');
        }

        return 200 === $response['status'] && 200 === $status && !array_any($messages, static fn (array $message): bool => 'Error' === $message['validationState']);
    }
}
