<?php

namespace App\Shared\Presentation;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/api/v1/health/live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'alive']);
    }

    #[Route('/api/v1/health/ready', methods: ['GET'])]
    public function ready(Connection $db): JsonResponse
    {
        try {
            $db->fetchOne('SELECT COUNT(*) FROM roles');

            return new JsonResponse(['status' => 'ready']);
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'unavailable'], 503);
        }
    }
}
