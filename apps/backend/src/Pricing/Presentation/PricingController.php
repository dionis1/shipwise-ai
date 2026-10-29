<?php

namespace App\Pricing\Presentation;

use App\Identity\Infrastructure\Principal;
use App\Pricing\Application\PricingRules;
use App\Quoting\Domain\QuoteViolation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin')]
final class PricingController extends AbstractController
{
    public function __construct(private PricingRules $rules)
    {
    }

    #[Route('/pricing-rules', methods: ['GET'])]
    public function pricing(): JsonResponse
    {
        $this->denyAccessUnlessGranted('pricing.manage');

        return $this->json(['items' => $this->rules->pricing()]);
    }

    #[Route('/pricing-rules', methods: ['POST'])]
    public function price(Request $r): JsonResponse
    {
        $this->denyAccessUnlessGranted('pricing.manage');

        return $this->json(['id' => $this->rules->addPricing($this->body($r), $this->actor())], 201);
    }

    /**
     * @return array<string,mixed> */
    private function body(Request $r): array
    {
        if (!str_contains($r->headers->get('Content-Type', ''), 'application/json') || strlen($r->getContent()) > 8192) {
            throw new QuoteViolation('request_invalid');
        }
        try {
            $data = json_decode($r->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new QuoteViolation('request_invalid');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new QuoteViolation('request_invalid');
        }

        return $data;
    }

    private function actor(): string
    {
        $user = $this->getUser();
        if (!$user instanceof Principal) {
            throw $this->createAccessDeniedException();
        }

        return $user->account->id();
    }
}
