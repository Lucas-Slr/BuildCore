<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    #[Route('/api/v1/products', methods: ['GET'])]
    public function index(Request $r, CatalogService $catalog): JsonResponse
    {
        return $this->json($catalog->search($r->query->all()));
    }
    #[Route('/api/v1/products/{id}', methods: ['GET'])]
    public function show(string $id, CatalogService $catalog): JsonResponse
    {
        return $this->json($catalog->view($catalog->variant($id)));
    }
    #[Route('/api/v1/categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->json(['items' => SpecificationSchema::CATEGORIES, 'specifications' => SpecificationSchema::FIELDS]);
    }
}
