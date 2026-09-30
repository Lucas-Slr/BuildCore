<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalog\{CatalogService,Variant};
use App\Customer\Customer;
use App\Cart\CartService;
use App\Checkout\CheckoutService;
use App\Inventory\{InventoryService,ExpireReservations,ExpireReservationsHandler};
use App\Order\{Purchase,OrderWorkflow};
use App\Payment\PaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request,RequestStack};
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CheckoutTest extends KernelTestCase
{
    public function testServerRepricesAndRetriesDoNotReserveTwice(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
        $variant = $em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
        $variant->physical = 10;
        $variant->reserved = 0;
        $variant->price = 32900;
        $em->flush();
        $stack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack->push($request);
        $cart = new CartService($c->get(CatalogService::class), $c->get(Security::class), $stack, $em);
        $cart->store([$variant->id => 2]);
        $gateway = new TestPaymentGateway();
        $service = new CheckoutService($em, $c->get(CatalogService::class), $c->get(InventoryService::class), $gateway);
        $key = bin2hex(random_bytes(16));
        $data = ['address' => ['name' => 'Alice','street' => '12 rue des Tests','city' => 'Lyon','postalCode' => '69002','country' => 'FR'],'delivery' => 'express','total' => 1];
        $data['expectedLines'] = [['id' => $variant->id, 'price' => 32900, 'quantity' => 2]];
        $order = $service->checkout($customer, $cart, $data, $key);
        $retry = $service->checkout($customer, $cart, $data, $key);
        self::assertSame($order->id, $retry->id);
        self::assertSame(67790, $order->total);
        self::assertSame(1, $gateway->creates);
        $em->refresh($variant);
        self::assertSame(2, $variant->reserved);
        self::assertSame('PENDING_PAYMENT', $order->status);
    }
    public function testChangedPriceRollsBackEveryReservation(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
        $variants = $em->getRepository(Variant::class)->findBy([], ['id' => 'ASC'], 2);
        $quantities = [];
        $quote = [];
        foreach ($variants as $v) {
            $v->physical = 10;
            $v->reserved = 0;
            $quantities[$v->id] = 1;
            $quote[] = ['id' => $v->id, 'price' => $v->price, 'quantity' => 1];
        }
        $em->flush();
        $quote[1]['price']++;
        $stack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack->push($request);
        $cart = new CartService($c->get(CatalogService::class), $c->get(Security::class), $stack, $em);
        $cart->store($quantities);
        $gateway = new TestPaymentGateway();
        $service = new CheckoutService($em, $c->get(CatalogService::class), $c->get(InventoryService::class), $gateway);
        try {
            $service->checkout($customer, $cart, ['address' => ['name' => 'Alice', 'street' => '12 rue des Tests', 'city' => 'Lyon', 'postalCode' => '69002', 'country' => 'FR'], 'expectedLines' => $quote], bin2hex(random_bytes(16)));
            self::fail('Un changement de prix doit être confirmé.');
        } catch (\App\Shared\DomainError $error) {
            self::assertSame('QUOTE_CHANGED', $error->errorCode);
        }
        self::assertSame(0, $gateway->creates);
        foreach ($variants as $v) {
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT reserved FROM variant WHERE id = ?', [$v->id]));
        }
    }
    public function testExpirationIsIdempotentWithConfirmedGatewayExpiration(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
        $v = $em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
        $v->physical = 3;
        $v->reserved = 1;
        $order = new Purchase($customer, 'expiry-'.bin2hex(random_bytes(10)));
        $order->expiresAt = new \DateTimeImmutable('-1 minute');
        $order->lines = [['variantId' => $v->id,'quantity' => 1]];
        $em->persist($order);
        $em->flush();
        $gateway = new TestPaymentGateway();
        $gateway->expiryId = $order->id;
        $handler = new ExpireReservationsHandler($em, $c->get(InventoryService::class), $c->get(OrderWorkflow::class), $gateway);
        $handler(new ExpireReservations());
        $handler(new ExpireReservations());
        $em->refresh($v);
        self::assertSame(0, $v->reserved);
        self::assertSame(3, $v->physical);
        self::assertSame('PAYMENT_FAILED', $order->status);
    }
}
final class TestPaymentGateway implements PaymentGateway
{
    public int $creates = 0;
    public ?string $expiryId = null;
    public function ready(): void
    {
    }
    public function create(Purchase $order): array
    {
        $this->creates++;
        return ['id' => 'cs_test_'.$order->id,'url' => 'https://checkout.stripe.com/test/'.$order->id];
    }
    public function refund(Purchase $order): string
    {
        return 're_test_'.$order->id;
    }
    public function isExpired(Purchase $order): bool
    {
        return $order->id === $this->expiryId;
    }
}
