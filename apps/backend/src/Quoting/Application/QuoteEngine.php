<?php

namespace App\Quoting\Application;

use App\CarrierCatalog\Application\Catalog;
use App\CarrierCatalog\Domain\Eligibility;
use App\CarrierIntegration\Application\ProviderRegistry;
use App\Pricing\Domain\PriceCalculator;
use App\Quoting\Domain\QuoteViolation;
use App\Quoting\Domain\ShipmentRequest;
use App\Shared\Domain\Identifier;

final class QuoteEngine
{
    public function __construct(private Catalog $catalog, private Eligibility $eligibility, private ProviderRegistry $providers, private PriceCalculator $pricing, private QuoteStore $store)
    {
    }

    /**
     * @return array<string,mixed> */
    public function create(ShipmentRequest $request, ?string $owner, string $secret, string $key): array
    {
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $key)) {
            throw new QuoteViolation('idempotency_invalid');
        }
        $scope = hash('sha256', $owner ?? $secret);
        $key = hash('sha256', $key);
        $data = $request->data;
        ksort($data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return $this->store->exclusive($scope.':'.$key, function () use ($request, $data, $hash, $scope, $key, $owner, $secret): array {
            $existing = $this->store->duplicate($scope, $key);
            if ($existing) {
                if ($existing['request_hash'] !== $hash) {
                    throw new QuoteViolation('idempotency_conflict');
                }

                return $this->view($existing, $secret);
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $expires = $now->modify('+15 minutes');
            $id = Identifier::new();
            $offers = [];
            $outcomes = [];
            foreach (array_slice($this->catalog->services(), 0, 20) as $service) {
                $reasons = $this->eligibility->reasons($request, $service);
                $outcome = ['carrierCode' => $service['carrier_code'], 'serviceCode' => $service['code'], 'status' => 'ineligible', 'reasons' => $reasons];
                if (!$reasons) {
                    if (!$service['tariff'] || !$service['pricing']) {
                        $outcome['status'] = 'no_rate_source';
                        $outcome['reasons'] = ['no_rate_source'];
                    } else {
                        $result = $this->providers->quote($service['mode'], $request, $service['tariff']);
                        $outcome['status'] = $result->status;
                        $outcome['reasons'] = [];
                        if ($result->status === 'success') {
                            try {
                                $price = $this->pricing->calculate($result->rate, $service['pricing'], $data['originCountry'].':'.$data['destinationCountry']);
                                $offers[] = ['id' => Identifier::new(), 'quoteId' => $id, 'carrierCode' => $service['carrier_code'], 'carrierName' => $service['carrier_name'], 'serviceCode' => $service['code'], 'price' => $price, 'source' => 'development_tariff', 'tariffVersion' => $service['tariff_version'], 'createdAt' => $now->format('c'), 'expiresAt' => $expires->format('c'), 'status' => 'quoted', 'eligible' => true, 'bookable' => false, 'options' => $service['rules']['handover'], 'speed' => $service['rules']['speed'], 'deliveryEstimate' => null];
                                $outcome['status'] = 'quoted';
                            } catch (QuoteViolation $e) {
                                $outcome['status'] = 'pricing_unavailable';
                                $outcome['reasons'] = [$e->reason];
                            }
                        }
                    }
                }
                $outcomes[] = $outcome;
            }
            $quote = ['id' => $id, 'owner_id' => $owner, 'guest_token_hash' => $owner === null ? hash('sha256', hash_hmac('sha256', $id, $secret)) : null, 'scope_hash' => $scope, 'idempotency_key' => $key, 'request_hash' => $hash, 'request' => $data, 'outcomes' => $outcomes, 'created_at' => $now->format('c'), 'expires_at' => $expires->format('c'), 'purge_at' => $now->modify('+24 hours')->format('c'), 'offers' => $offers];
            $this->store->save($quote);

            return $this->view($quote, $secret);
        });
    }

    /**
     * @return array<string,mixed> */
    public function read(string $id, ?string $owner, string $secret, string $token): array
    {
        $quote = $this->store->find($id);
        if (!$quote || new \DateTimeImmutable($quote['purge_at']) <= new \DateTimeImmutable() || ($quote['owner_id'] !== null ? $quote['owner_id'] !== $owner : (!hash_equals($quote['scope_hash'], hash('sha256', $secret)) || !hash_equals($quote['guest_token_hash'], hash('sha256', $token))))) {
            throw new QuoteViolation('quote_not_found');
        }
        $view = $this->view($quote, $secret);
        unset($view['accessToken']);

        return $view;
    }

    /**
     * @return array<string,mixed> */
    public function offer(string $id, string $offerId, ?string $owner, string $secret, string $token): array
    {
        $quote = $this->read($id, $owner, $secret, $token);
        if ($quote['expired']) {
            throw new QuoteViolation('quote_expired');
        }
        foreach ($quote['offers'] as $offer) {
            if ($offer['id'] === $offerId) {
                return ['shipment' => $quote['shipment'], 'offer' => $offer, 'bookingAvailable' => false];
            }
        }
        throw new QuoteViolation('quote_not_found');
    }

    /**
     * @param array<string,mixed> $quote
     *
     * @return array<string,mixed> */
    private function view(array $quote, string $secret): array
    {
        usort($quote['offers'], static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $view = ['id' => $quote['id'], 'shipment' => $quote['request'], 'createdAt' => (new \DateTimeImmutable($quote['created_at']))->format('c'), 'expiresAt' => (new \DateTimeImmutable($quote['expires_at']))->format('c'), 'expired' => new \DateTimeImmutable($quote['expires_at']) <= new \DateTimeImmutable(), 'temporary' => $quote['owner_id'] === null, 'offers' => $quote['offers'], 'outcomes' => $quote['outcomes']];
        if ($quote['owner_id'] === null) {
            $view['accessToken'] = hash_hmac('sha256', $quote['id'], $secret);
        }

        return $view;
    }
}
