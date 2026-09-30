<?php

declare(strict_types=1);

namespace App\Shared;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Response,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class DocumentationController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }
    #[Route('/api/v1/openapi.json', methods:['GET'])]
    public function specification(): JsonResponse
    {
        return new JsonResponse(file_get_contents($this->projectDir.'/../../docs/openapi.json'), 200, [], true);
    }
    #[Route('/api/docs', methods:['GET'])]
    public function explorer(): Response
    {
        return new Response(file_get_contents($this->projectDir.'/public/api-explorer.html'));
    }
    #[Route('/api/v1/health', methods:['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok','service' => 'BuildCore API']);
    }
}
