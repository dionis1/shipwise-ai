<?php

namespace App\CarrierIntegration\Presentation;

use App\CarrierIntegration\Domain\IntegrationFailure;
use App\CarrierIntegration\Infrastructure\SandboxManager;
use App\Identity\Infrastructure\Principal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/carrier-sandboxes')]
final class SandboxController extends AbstractController
{
    public function __construct(private SandboxManager $sandboxes, private RateLimiterFactory $carrierChecksLimiter)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('carriers.catalog.read');

        return $this->json(['items' => $this->sandboxes->statuses()]);
    }

    #[Route('/{code}', methods: ['PATCH'])]
    public function configure(Request $request, string $code): JsonResponse
    {
        $this->denyAccessUnlessGranted('carriers.configure');
        $body = $this->body($request);
        if (array_keys($body) !== ['enabled'] || !is_bool($body['enabled'])) {
            throw new IntegrationFailure('search_invalid');
        }
        $this->sandboxes->configure($code, $body['enabled'], $this->actor());

        return $this->json(['updated' => true]);
    }

    #[Route('/{code}/check', methods: ['POST'])]
    public function check(Request $request, string $code): JsonResponse
    {
        $this->denyAccessUnlessGranted('carriers.configure');
        if (!$this->carrierChecksLimiter->create($this->actor())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Too many requests. Try again later.');
        }
        $body = $this->body($request);
        if (array_keys($body) !== ['operation'] || !is_string($body['operation'])) {
            throw new IntegrationFailure('search_invalid');
        }

        return $this->json($this->sandboxes->check($code, $body['operation'], $this->actor()));
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        if (!str_contains($request->headers->get('Content-Type', ''), 'application/json') || strlen($request->getContent()) > 512) {
            throw new IntegrationFailure('search_invalid');
        }
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new IntegrationFailure('search_invalid');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new IntegrationFailure('search_invalid');
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
