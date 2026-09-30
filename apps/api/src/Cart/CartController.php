<?php

declare(strict_types=1);

namespace App\Cart;

use App\Compatibility\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    #[Route('/api/v1/cart', methods:['GET'])]
    public function show(CartService $cart): JsonResponse
    {
        return $this->json($cart->view());
    }
    #[Route('/api/v1/cart/items/{id}', methods:['PUT','DELETE'])]
    public function change(string $id, Request $r, CartService $cart): JsonResponse
    {
        $cart->change([$id => $r->isMethod('DELETE') ? 0 : ($r->toArray()['quantity'] ?? null)]);
        return $this->show($cart);
    }
    #[Route('/api/v1/cart/configuration', methods:['POST'])]
    public function build(Request $r, CartService $cart, ConfigurationService $build): JsonResponse
    {
        $ids = $r->toArray()['components'] ?? [];
        $build->check($ids, 0, true);
        $cart->configuration($ids, $r->toArray()['name'] ?? 'Ma configuration');
        return $this->show($cart);
    }
    #[Route('/api/v1/cart/configurations/{id}', methods:['PUT','DELETE'])]
    public function changeBuild(string $id, Request $r, CartService $cart, ConfigurationService $build): JsonResponse
    {
        $data = $r->isMethod('DELETE') ? [] : $r->toArray();
        $ids = $data['components'] ?? [];
        if (!$r->isMethod('DELETE')) {
            $build->check($ids, 0, true);
        }
        $cart->configuration($ids, $data['name'] ?? 'Ma configuration', $id);
        return $this->show($cart);
    }
}
