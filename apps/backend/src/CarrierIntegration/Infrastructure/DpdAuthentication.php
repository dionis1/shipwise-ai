<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Domain\IntegrationFailure;

final class DpdAuthentication
{
    public function __construct(private SandboxConfiguration $configuration, private SandboxTransport $transport, private SandboxTokens $tokens, private DpdXml $xml)
    {
    }

    private function key(): string
    {
        return 'dpd:'.hash('sha256', $this->configuration->value('DPD_DELIS_ID').'|'.$this->configuration->value('DPD_PASSWORD'));
    }

    public function token(): string
    {
        $this->configuration->guard('DPD');

        return $this->tokens->get($this->key(), function (): array {
            $body = '<l:getAuth xmlns:l="'.DpdXml::LOGIN.'">'.$this->xml->element('delisId', $this->configuration->value('DPD_DELIS_ID')).$this->xml->element('password', $this->configuration->value('DPD_PASSWORD')).$this->xml->element('messageLanguage', 'en_EN').'</l:getAuth>';
            $response = $this->transport->request('dpd_login', ['Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => 'http://dpd.com/common/service/LoginService/2.0/getAuth'], $this->xml->envelope($body));
            $result = $this->xml->response($response['body'], DpdXml::LOGIN, 'getAuthResponse', $response['status']);
            $return = $result->firstChild;
            if (!$return instanceof \DOMElement || 'return' !== $return->localName) {
                throw new IntegrationFailure('response_invalid');
            }
            $token = $this->xml->text($return, 'authToken');
            if (null === $token) {
                throw new IntegrationFailure('response_invalid');
            }

            // Login 2.0 provides no expiry field. Five minutes is a local cache policy, not a provider claim.
            return ['token' => $token, 'ttl' => 300];
        });
    }

    public function invalidate(): void
    {
        $this->tokens->forget($this->key());
    }
}
