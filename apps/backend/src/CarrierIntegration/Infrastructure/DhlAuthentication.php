<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Domain\IntegrationFailure;

final class DhlAuthentication
{
    public function __construct(private SandboxConfiguration $configuration, private SandboxTransport $transport, private SandboxTokens $tokens)
    {
    }

    public function key(): string
    {
        return 'dhl:'.hash('sha256', implode('|', array_map($this->configuration->value(...), ['DHL_CLIENT_ID', 'DHL_CLIENT_SECRET', 'DHL_USERNAME', 'DHL_PASSWORD'])));
    }

    public function token(): string
    {
        $this->configuration->guard('DHL');

        return $this->tokens->get($this->key(), function (): array {
            $form = ['grant_type' => 'password'];
            foreach (['client_id', 'client_secret', 'username', 'password'] as $field) {
                $form[$field] = $this->configuration->value('DHL_'.strtoupper($field));
            }
            $response = $this->transport->request('dhl_token', ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query($form));
            if (200 !== $response['status']) {
                throw new IntegrationFailure('authentication_failed');
            }
            $data = $this->transport->json($response['body']);
            if (!is_string($data['access_token'] ?? null) || !preg_match('/^[A-Za-z0-9._~+\/-]+=*$/D', $data['access_token']) || 'Bearer' !== ($data['token_type'] ?? null) || !is_int($data['expires_in'] ?? null) || $data['expires_in'] <= 60) {
                throw new IntegrationFailure('response_invalid');
            }

            return ['token' => $data['access_token'], 'ttl' => $data['expires_in'] - 60];
        });
    }

    public function invalidate(): void
    {
        $this->tokens->forget($this->key());
    }
}
