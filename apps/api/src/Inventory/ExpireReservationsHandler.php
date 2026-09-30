<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Order\Purchase;
use App\Order\OrderWorkflow;
use App\Payment\PaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExpireReservationsHandler
{
    public function __construct(private EntityManagerInterface $em, private InventoryService $inventory, private OrderWorkflow $workflow, private PaymentGateway $stripe)
    {
    }
    public function __invoke(ExpireReservations $message): void
    {
        $orders = $this->em->createQueryBuilder()->select('o')->from(Purchase::class, 'o')->where("o.reservationStatus = 'ACTIVE' AND o.expiresAt < :now")->setParameter('now', new \DateTimeImmutable())->setMaxResults(100)->getQuery()->getResult();
        foreach ($orders as $order) {
            // Ask Stripe before releasing: a delayed success webhook must never oversell stock.
            if (!$this->stripe->isExpired($order)) {
                continue;
            }
            $this->em->wrapInTransaction(function () use ($order): void {
                $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
                if ($order->reservationStatus !== 'ACTIVE' || $order->paymentStatus !== 'PENDING') {
                    return;
                }
                $this->inventory->finish($order, false);
                $order->paymentStatus = 'FAILED';
                $this->workflow->apply($order, 'fail', 'expiration');
            });
        }
    }
}
