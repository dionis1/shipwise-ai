<?php

namespace App\CarrierIntegration\Presentation;

use App\CarrierIntegration\Domain\IntegrationFailure;
use App\CarrierIntegration\Domain\LocationSearch;
use App\CarrierIntegration\Infrastructure\SandboxManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ParcelShopController extends AbstractController
{
    public function __construct(private SandboxManager $sandboxes, private RateLimiterFactory $parcelShopsLimiter)
    {
    }

    #[Route('/api/v1/parcel-shops', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        if (!$this->parcelShopsLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(900, 'Too many requests. Try again later.');
        }
        $data = $request->query->all();
        if (array_diff(array_keys($data), ['country', 'postalCode', 'city', 'carrier', 'limit']) || array_any($data, static fn (mixed $value): bool => !is_string($value))) {
            throw new IntegrationFailure('search_invalid');
        }
        $carrier = $data['carrier'] ?? 'all';
        $limit = $data['limit'] ?? '10';
        if (!in_array($carrier, ['all', 'DPD', 'GLS'], true) || !preg_match('/^[0-9]{1,2}$/D', $limit) || !in_array($data['country'] ?? '', ['DE', 'FR'], true)) {
            throw new IntegrationFailure('search_invalid');
        }
        $search = new LocationSearch($data['country'], $data['postalCode'] ?? '', $data['city'] ?? '', (int) $limit);

        return $this->json($this->sandboxes->locations($search, $carrier));
    }
}
