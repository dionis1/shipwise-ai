<?php

namespace App\Quoting\Presentation;

use App\CarrierCatalog\Application\Catalog;
use App\Identity\Infrastructure\Principal;
use App\Quoting\Application\QuoteEngine;
use App\Quoting\Domain\QuoteViolation;
use App\Quoting\Domain\ShipmentRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class QuoteController extends AbstractController
{
    public function __construct(private \App\Quoting\Application\QuoteStore $store, private QuoteEngine $engine, private Catalog $catalog, private RateLimiterFactory $quotesLimiter, private string $environment)
    {
    }

    #[Route('/api/v1/admin/quote-diagnostics', methods: ['GET'])]
    public function diagnostics(): JsonResponse
    {
        $this->denyAccessUnlessGranted('quotes.diagnostics');

        return $this->json(['items' => $this->store->diagnostics()]);
    }

    #[Route('/api/v1/carriers', methods: ['GET'])]
    public function carriers(): JsonResponse
    {
        return $this->json(['carriers' => array_map(fn (array $c): array => ['code' => $c['code'], 'name' => $c['name'], 'enabled' => (bool) $c['enabled'] && in_array($this->environment, ['dev', 'test', 'e2e'], true), 'mode' => $c['mode']], $this->catalog->carriers()), 'countries' => ['DE', 'FR'], 'singleParcel' => true, 'units' => ['weight' => 'grams', 'dimensions' => 'millimeters']]);
    }

    #[Route('/api/v1/quotes', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        if (!$this->quotesLimiter->create($r->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(900, 'Too many requests. Try again later.');
        }
        if (!str_contains($r->headers->get('Content-Type', ''), 'application/json') || strlen($r->getContent()) > 8192) {
            throw new QuoteViolation('shipment_fields');
        }
        try {
            $data = json_decode($r->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new QuoteViolation('shipment_fields');
        }
        if (!is_array($data)) {
            throw new QuoteViolation('shipment_fields');
        }

        return $this->json($this->engine->create(new ShipmentRequest($data), $this->owner(), $this->secret($r), $r->headers->get('Idempotency-Key', '')), 201);
    }

    #[Route('/api/v1/quotes/{id}', methods: ['GET'])]
    public function read(Request $r, string $id): JsonResponse
    {
        return $this->json($this->engine->read($id, $this->owner(), $this->secret($r), $r->headers->get('X-Quote-Token', '')));
    }

    #[Route('/api/v1/quotes/{id}/offers/{offerId}', methods: ['GET'])]
    public function offer(Request $r, string $id, string $offerId): JsonResponse
    {
        return $this->json($this->engine->offer($id, $offerId, $this->owner(), $this->secret($r), $r->headers->get('X-Quote-Token', '')));
    }

    private function owner(): ?string
    {
        $user = $this->getUser();

        return $user instanceof Principal ? $user->account->id() : null;
    }

    private function secret(Request $r): string
    {
        $session = $r->getSession();
        if (!$session->has('quote_scope')) {
            $session->set('quote_scope', bin2hex(random_bytes(32)));
        }

        return $session->get('quote_scope');
    }
}
