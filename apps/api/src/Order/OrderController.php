<?php

declare(strict_types=1);

namespace App\Order;

use App\Customer\Customer;
use App\Cart\CartService;
use App\Checkout\CheckoutService;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\MatcherInput;
use Symfony\Component\HttpFoundation\Cookie;

final class OrderController extends AbstractController
{
    #[Route('/api/v1/checkout', methods:['POST'])]
    public function checkout(Request $r, CartService $cart, CheckoutService $checkout): JsonResponse
    {
        $u = $this->getUser();
        if (!$u instanceof Customer) {
            throw $this->createAccessDeniedException();
        }
        $order = $checkout->checkout($u, $cart, $r->toArray(), $r->headers->get('Idempotency-Key', ''));
        return $this->json(['order' => $order->id,'url' => $order->checkoutUrl], 201);
    }
    #[Route('/api/v1/orders', methods:['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $orders = $em->getRepository(Purchase::class)->findBy(['customer' => $this->getUser()], ['createdAt' => 'DESC'], 100);
        return $this->json(['items' => array_map(self::view(...), $orders)]);
    }
    #[Route('/api/v1/orders/{id}', methods:['GET'])]
    public function show(string $id, EntityManagerInterface $em): JsonResponse
    {
        $order = $em->find(Purchase::class, $id);
        if (!$order) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);
        return $this->json(self::view($order));
    }
    #[Route('/api/v1/orders/{id}/subscription', methods:['POST'])]
    public function subscribe(string $id, Request $r, EntityManagerInterface $em, HubInterface $hub): JsonResponse
    {
        $order = $em->find(Purchase::class, $id);
        if (!$order) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);
        $topic = 'https://buildcore.local/orders/'.$id;
        $factory = $hub->getFactory();
        if (!$factory) {
            throw new \LogicException('Mercure token factory is required');
        }
        $expires = new \DateTimeImmutable('+1 hour');
        $token = $factory->create(MatcherInput::normalizeGrants([$topic]), ['exp' => $expires]);
        $response = $this->json(['topic' => $topic]);
        // The frontend proxies the hub on the same origin. Never widen this cookie to sibling hosts.
        $response->headers->setCookie(Cookie::create('mercureAuthorization', $token, $expires, '/.well-known/mercure', null, $r->isSecure() || $this->getParameter('kernel.environment') === 'prod', true, false, Cookie::SAMESITE_STRICT));
        return $response;
    }
    /** @return array<string, mixed> */
    public static function view(Purchase $o): array
    {
        return ['id' => $o->id,'number' => $o->number,'status' => $o->status,'paymentStatus' => $o->paymentStatus,'lines' => $o->lines,'shippingAddress' => $o->shippingAddress,'delivery' => $o->delivery,'shipping' => $o->shipping,'total' => $o->total,'currency' => $o->currency,'tracking' => $o->tracking,'history' => $o->history,'createdAt' => $o->createdAt->format(DATE_ATOM),'expiresAt' => $o->expiresAt->format(DATE_ATOM)];
    }
}
