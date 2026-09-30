<?php

declare(strict_types=1);

namespace App\Notification;

use App\Order\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class DeliveryService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    /** @param callable(Purchase):void $send */
    public function once(OrderChanged $message, string $channel, callable $send): void
    {
        $this->em->wrapInTransaction(function () use ($message, $channel, $send): void {
            $order = $this->em->find(Purchase::class, $message->orderId, LockMode::PESSIMISTIC_WRITE);
            if (!$order) {
                return;
            }
            $key = $order->id.':'.$message->status.':'.$channel;
            if ($this->em->find(NotificationReceipt::class, $key)) {
                return;
            }
            $send($order);
            $this->em->persist(new NotificationReceipt($key));
        });
    }
}
