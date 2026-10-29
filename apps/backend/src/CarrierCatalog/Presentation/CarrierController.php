<?php

namespace App\CarrierCatalog\Presentation;

use App\CarrierCatalog\Application\Catalog;
use App\Identity\Infrastructure\Principal;
use App\Quoting\Domain\QuoteViolation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin')]
final class CarrierController extends AbstractController
{
    public function __construct(private Catalog $catalog)
    {
    }

    #[Route('/carriers', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('carriers.catalog.read');

        return $this->json(['carriers' => $this->catalog->carriers(), 'services' => array_map(static function (array $service): array {
            unset($service['tariff'],$service['pricing']);

            return $service;
        }, $this->catalog->services())]);
    }

    #[Route('/carriers/{code}', methods: ['PATCH'])]
    public function configure(Request $r, string $code): JsonResponse
    {
        $this->denyAccessUnlessGranted('carriers.configure');
        $data = $this->body($r);
        if (array_diff(array_keys($data), ['enabled', 'serviceCode']) || !is_bool($data['enabled'] ?? null) || (isset($data['serviceCode']) && !is_string($data['serviceCode']))) {
            throw new QuoteViolation('carrier_invalid');
        }
        $this->catalog->configure($code, $data['enabled'], $data['serviceCode'] ?? null, $this->actor());

        return $this->json(['updated' => true]);
    }

    #[Route('/tariffs', methods: ['GET'])]
    public function tariffs(): JsonResponse
    {
        $this->denyAccessUnlessGranted('tariffs.manage');

        return $this->json(['items' => $this->catalog->tariffs()]);
    }

    #[Route('/tariffs', methods: ['POST'])]
    public function import(Request $r): JsonResponse
    {
        $this->denyAccessUnlessGranted('tariffs.manage');

        return $this->json(['id' => $this->catalog->importTariff($this->body($r), $this->actor())], 201);
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
