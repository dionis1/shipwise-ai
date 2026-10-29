<?php

namespace App\CarrierIntegration\Infrastructure;

use App\CarrierIntegration\Application\CarrierLocationProvider;
use App\CarrierIntegration\Domain\IntegrationFailure;
use App\CarrierIntegration\Domain\LocationSearch;
use App\CarrierIntegration\Domain\ParcelShop;

final class DpdLocationProvider implements CarrierLocationProvider
{
    public function __construct(private SandboxConfiguration $configuration, private SandboxTransport $transport, private DpdAuthentication $authentication, private DpdXml $xml)
    {
    }

    public function check(): void
    {
        $this->authentication->invalidate();
        $this->authentication->token();
    }

    public function locations(LocationSearch $search): array
    {
        $this->configuration->guard('DPD');

        return $this->lookup($search, true);
    }

    /** @return list<ParcelShop> */
    private function lookup(LocationSearch $search, bool $retry): array
    {
        try {
            $token = $this->authentication->token();
            $header = '<a:authentication xmlns:a="'.DpdXml::AUTH.'">'.$this->xml->element('delisId', $this->configuration->value('DPD_DELIS_ID')).$this->xml->element('authToken', $token).$this->xml->element('messageLanguage', 'en_EN').'</a:authentication>';
            $body = '<p:findParcelShops xmlns:p="'.DpdXml::SHOPS.'">'.$this->xml->element('country', $search->country).$this->xml->element('zipCode', $search->postalCode).('' !== $search->city ? $this->xml->element('city', $search->city) : '').$this->xml->element('limit', (string) $search->limit).'</p:findParcelShops>';
            $response = $this->transport->request('dpd_locations', ['Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => 'http://dpd.com/common/service/ParcelShopFinderService/5.0/findParcelShops'], $this->xml->envelope($body, $header));
            $result = $this->xml->response($response['body'], DpdXml::SHOPS, 'findParcelShopsResponse', $response['status']);
            $shops = [];
            $retrieved = gmdate('c');
            foreach ($result->childNodes as $item) {
                if (!$item instanceof \DOMElement || 'parcelShop' !== $item->localName) {
                    continue;
                }
                if (count($shops) >= $search->limit) {
                    break;
                }
                $id = $this->xml->text($item, 'parcelShopId') ?? $this->xml->text($item, 'pudoId');
                if (null === $id) {
                    throw new IntegrationFailure('response_invalid');
                }
                $services = [];
                foreach ($item->getElementsByTagName('service') as $service) {
                    $code = $this->xml->text($service, 'code');
                    if (null !== $code && in_array($this->xml->text($service, 'available'), ['true', '1'], true)) {
                        $services[] = $code;
                    }
                }
                $street = $this->xml->text($item, 'street');
                $house = $this->xml->text($item, 'houseNo');
                $shops[] = new ParcelShop('DPD', $id, $this->xml->text($item, 'company'), null !== $street ? trim($street.' '.($house ?? '')) : null, $this->xml->text($item, 'zipCode'), $this->xml->text($item, 'city'), $this->xml->text($item, 'country'), $this->coordinate($item, 'latitude', 90), $this->coordinate($item, 'longitude', 180), array_values(array_unique($services)), $retrieved, 'dpd_sandbox');
            }

            return $shops;
        } catch (IntegrationFailure $error) {
            if (!$retry || 'authentication_failed' !== $error->reason) {
                throw $error;
            }
            $this->authentication->invalidate();

            return $this->lookup($search, false);
        }
    }

    private function coordinate(\DOMElement $item, string $key, int $bound): ?float
    {
        $value = $this->xml->text($item, $key);
        if (null === $value) {
            return null;
        }
        if (!is_numeric($value) || !is_finite((float) $value) || abs((float) $value) > $bound) {
            throw new IntegrationFailure('response_invalid');
        }

        return (float) $value;
    }
}
