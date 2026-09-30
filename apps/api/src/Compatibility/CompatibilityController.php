<?php

declare(strict_types=1);

namespace App\Compatibility;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CompatibilityController extends AbstractController
{
    #[Route('/api/v1/configurations/options', methods: ['POST'])]
    public function options(Request $r, ConfigurationService $service): JsonResponse
    {
        return $this->json($service->options($r->toArray()['components'] ?? []));
    }
    #[Route('/api/v1/configurations/check', methods: ['POST'])]
    public function check(Request $r, ConfigurationService $service): JsonResponse
    {
        $data = $r->toArray();
        return $this->json($service->check($data['components'] ?? [], (int) ($data['budget'] ?? 0)));
    }
}
