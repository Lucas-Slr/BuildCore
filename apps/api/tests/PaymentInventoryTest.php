<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalog\Variant;
use App\Customer\Customer;
use App\Order\{Purchase,OrderWorkflow};
use App\Payment\PaymentService;
use App\Inventory\InventoryService;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaymentInventoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }
    private function order(): Purchase
    {
        $u = $this->em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
        $v = $this->em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
        $v->physical = 10;
        $v->reserved = 0;
        $this->em->flush();
        $o = new Purchase($u, 'test-'.bin2hex(random_bytes(12)));
        $o->lines = [['variantId' => $v->id,'name' => $v->product->name,'sku' => $v->sku,'unitPrice' => $v->price,'quantity' => 1,'amount' => $v->price,'specs' => $v->product->specs]];
        $o->total = $v->price;
        $o->stripeSession = 'cs_test_'.$o->id;
        $this->em->wrapInTransaction(function () use ($o, $v): void {
            self::getContainer()->get(InventoryService::class)->reserve($v, 1);
            $this->em->persist($o);
        });
        return $o;
    }
    private function event(Purchase $o, string $type, string $eventId): array
    {
        return ['id' => $eventId,'livemode' => false,'type' => $type,'data' => ['object' => ['id' => $o->stripeSession,'metadata' => ['order_id' => $o->id],'payment_status' => 'paid','payment_intent' => 'pi_test_'.$o->id,'amount_total' => $o->total,'currency' => 'eur']]];
    }
    public function testSuccessIsIdempotentAndExpiryAfterPaymentIgnored(): void
    {
        $o = $this->order();
        $p = self::getContainer()->get(PaymentService::class);
        $event = $this->event($o, 'checkout.session.completed', 'evt_'.bin2hex(random_bytes(8)));
        $p->process($event);
        $p->process($event);
        $p->process($this->event($o, 'checkout.session.expired', 'evt_'.bin2hex(random_bytes(8))));
        $this->em->clear();
        $saved = $this->em->find(Purchase::class, $o->id);
        self::assertSame('PAID', $saved->status);
        $v = $this->em->find(Variant::class, $saved->lines[0]['variantId']);
        self::assertSame(9, $v->physical);
        self::assertSame(0, $v->reserved);
    }
    public function testFailureReleasesOnlyOnce(): void
    {
        $o = $this->order();
        $p = self::getContainer()->get(PaymentService::class);
        $event = $this->event($o, 'checkout.session.expired', 'evt_'.bin2hex(random_bytes(8)));
        $p->process($event);
        $p->process($event);
        $this->em->clear();
        $v = $this->em->find(Variant::class, $o->lines[0]['variantId']);
        self::assertSame(10, $v->physical);
        self::assertSame(0, $v->reserved);
    }
    public function testWrongAmountRejected(): void
    {
        $o = $this->order();
        $e = $this->event($o, 'checkout.session.completed', 'evt_'.bin2hex(random_bytes(8)));
        $e['data']['object']['amount_total'] = 1;
        $this->expectException(DomainError::class);
        self::getContainer()->get(PaymentService::class)->process($e);
    }
    public function testSnapshotsAreImmutableWhenProductChanges(): void
    {
        $o = $this->order();
        $original = $o->lines[0];
        $v = $this->em->find(Variant::class, $original['variantId']);
        $v->price = 19999;
        $this->em->flush();
        $this->em->clear();
        self::assertSame($original, $this->em->find(Purchase::class, $o->id)->lines[0]);
    }
    public function testInvalidWorkflowTransitionIsRejected(): void
    {
        $o = $this->order();
        $this->expectException(DomainError::class);
        self::getContainer()->get(OrderWorkflow::class)->apply($o, 'ship', 'test');
    }
    public function testLastUnitCannotBeReservedTwice(): void
    {
        $v = $this->em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
        $v->physical = 1;
        $v->reserved = 0;
        $this->em->flush();
        $inventory = self::getContainer()->get(InventoryService::class);
        $this->em->wrapInTransaction(fn () => $inventory->reserve($v, 1));
        $this->expectException(DomainError::class);
        $this->em->wrapInTransaction(fn () => $inventory->reserve($v, 1));
    }
    public function testFullRefundWaitsForStripe(): void
    {
        $o = $this->order();
        $p = self::getContainer()->get(PaymentService::class);
        $p->process($this->event($o, 'checkout.session.completed', 'evt_'.bin2hex(random_bytes(8))));
        self::getContainer()->get(OrderWorkflow::class)->apply($o, 'request_refund', 'admin');
        $this->em->flush();
        self::assertSame('PAID', $o->paymentStatus);
        $event = ['id' => 'evt_'.bin2hex(random_bytes(8)),'livemode' => false,'type' => 'refund.updated','data' => ['object' => ['id' => 're_test','status' => 'succeeded','amount' => $o->total,'payment_intent' => $o->paymentIntent,'metadata' => ['order_id' => $o->id]]]];
        $p->process($event);
        $p->process($event);
        self::assertSame('REFUNDED',$o->status);
    }
}
