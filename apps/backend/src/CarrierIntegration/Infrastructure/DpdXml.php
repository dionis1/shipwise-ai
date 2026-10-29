<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Domain\IntegrationFailure;

final class DpdXml
{
    public const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const AUTH = 'http://dpd.com/common/service/types/Authentication/2.0';
    public const LOGIN = 'http://dpd.com/common/service/types/LoginService/2.0';
    public const SHOPS = 'http://dpd.com/common/service/types/ParcelShopFinderService/5.0';

    public function element(string $name, #[\SensitiveParameter] string $value): string
    {
        return '<'.$name.'>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$name.'>';
    }

    public function envelope(#[\SensitiveParameter] string $body, #[\SensitiveParameter] string $header = ''): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="'.self::SOAP.'"><soap:Header>'.$header.'</soap:Header><soap:Body>'.$body.'</soap:Body></soap:Envelope>';
    }

    public function response(#[\SensitiveParameter] string $xml, string $namespace, string $element, int $status): \DOMElement
    {
        if (strlen($xml) > 262144 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new IntegrationFailure('response_invalid');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            $document->resolveExternals = false;
            $document->substituteEntities = false;
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS) || null === $document->documentElement || self::SOAP !== $document->documentElement->namespaceURI || 'Envelope' !== $document->documentElement->localName) {
                throw new IntegrationFailure('response_invalid');
            }
            $bodies = $document->getElementsByTagNameNS(self::SOAP, 'Body');
            if (1 !== $bodies->length) {
                throw new IntegrationFailure('response_invalid');
            }
            $body = $bodies->item(0);
            if ($body?->parentNode !== $document->documentElement) {
                throw new IntegrationFailure('response_invalid');
            }
            $fault = $document->getElementsByTagNameNS(self::SOAP, 'Fault');
            if ($fault->length > 0) {
                if ($document->getElementsByTagNameNS(self::AUTH, 'authenticationFault')->length > 0) {
                    throw new IntegrationFailure('authentication_failed');
                }
                if ($document->getElementsByTagNameNS($namespace, 'dataFault')->length > 0) {
                    throw new IntegrationFailure('search_rejected');
                }
                throw new IntegrationFailure('temporarily_unavailable');
            }
            $response = $body->firstChild;
            if (200 !== $status || !$response instanceof \DOMElement || $response->nextSibling || $response->localName !== $element || $response->namespaceURI !== $namespace) {
                throw new IntegrationFailure('response_invalid');
            }

            return $response;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function text(\DOMElement $element, string $name): ?string
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $value = trim($child->textContent);
                if (mb_strlen($value) > 256 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value)) {
                    throw new IntegrationFailure('response_invalid');
                }

                return '' === $value ? null : $value;
            }
        }

        return null;
    }
}
