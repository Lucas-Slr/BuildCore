<?php

declare(strict_types=1);

namespace App\Administration;

use App\Catalog\{Variant,CatalogService};
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class VariantController extends AbstractController
{
    #[Route('/api/v1/admin/products/{id}/variants', methods:['POST'])]
    public function create(string $id, Request $r, EntityManagerInterface $em, CatalogService $catalog): JsonResponse
    {
        $source = $em->find(Variant::class, $id);
        if (!$source) {
            throw $this->createNotFoundException();
        }$d = $r->toArray();
        if (!is_string($d['sku'] ?? null) || !preg_match('/^[A-Z0-9-]{3,80}$/', $d['sku']) || !is_string($d['name'] ?? null) || trim($d['name']) === '' || strlen($d['name']) > 120 || !is_int($d['price'] ?? null) || $d['price'] < 1 || $d['price'] > 10000000) {
            throw new DomainError('VARIANT_INVALID', 'Nom, SKU unique et prix en centimes requis.');
        }
        $v = new Variant($source->product);
        $v->sku = $d['sku'];
        $v->name = $d['name'];
        $v->price = $d['price'];
        $em->persist($v);
        $em->flush();
        return $this->json($catalog->view($v), 201);
    }
}
