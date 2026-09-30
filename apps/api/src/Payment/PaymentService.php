<?php

declare(strict_types=1);

namespace App\Payment;

use App\Inventory\InventoryService;
use App\Order\Purchase;
use App\Order\OrderWorkflow;
use App\Notification\OrderChanged;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Messenger\MessageBusInterface;

final class PaymentService
{
    public function __construct(private EntityManagerInterface $em, private InventoryService $inventory, private OrderWorkflow $workflow, private MessageBusInterface $bus)
    {
    }
    /** @param array<string, mixed> $event */
    public function process(array $event): void
    {
        if (($event['livemode'] ?? true) !== false) {
            throw new DomainError('LIVE_PAYMENT_REJECTED', 'Seuls les événements Stripe test sont acceptés.', 400);
        }
        $object = $event['data']['object'] ?? [];
        $type = $event['type'] ?? '';
        $id = $object['metadata']['order_id'] ?? null;
        if (!$id) {
            return;
        }
        $this->em->wrapInTransaction(function () use ($event, $object, $type, $id): void {
            $order = $this->em->find(Purchase::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$order) {
                throw new DomainError('ORDER_NOT_FOUND', 'Commande introuvable.', 404);
            }
            if ($this->em->find(WebhookReceipt::class, $event['id'])) {
                return;
            }
            $before = $order->status;
            if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
                if (($object['payment_status'] ?? '') !== 'paid') {
                    return;
                }
                if (($object['amount_total'] ?? null) !== $order->total || ($object['currency'] ?? '') !== 'eur' || ($order->stripeSession && $order->stripeSession !== $object['id'])) {
                    throw new DomainError('PAYMENT_MISMATCH', 'Le paiement ne correspond pas à la commande.', 400);
                }
                if ($order->paymentStatus === 'PENDING' && $order->reservationStatus === 'ACTIVE') {
                    $order->stripeSession = $object['id'];
                    $order->paymentIntent = $object['payment_intent'];
                    $this->inventory->finish($order, true);
                    $order->paymentStatus = 'PAID';
                    $this->workflow->apply($order, 'pay', 'stripe');
                } elseif (!in_array($order->paymentStatus, ['PAID','REFUNDED'], true)) {
                    throw new DomainError('PAYMENT_RECONCILIATION_REQUIRED', 'Un rapprochement du paiement est nécessaire.', 409);
                }
            } elseif (in_array($type, ['checkout.session.expired','checkout.session.async_payment_failed'], true) && $order->paymentStatus === 'PENDING') {
                if ($order->stripeSession && $order->stripeSession !== $object['id']) {
                    throw new DomainError('PAYMENT_MISMATCH', 'Session incorrecte.', 400);
                }
                $this->inventory->finish($order, false);
                $order->paymentStatus = 'FAILED';
                $this->workflow->apply($order, 'fail', 'stripe');
            } elseif ($type === 'refund.updated' || $type === 'refund.created') {
                if (($object['status'] ?? '') === 'succeeded' && !in_array($order->status, ['REFUND_PENDING','REFUNDED'], true)) {
                    throw new DomainError('REFUND_RECONCILIATION_REQUIRED', 'Le remboursement nécessite un rapprochement. Réessayez après confirmation du paiement.', 409);
                }
                if (($object['status'] ?? '') === 'succeeded' && ($object['amount'] ?? 0) === $order->total && ($object['payment_intent'] ?? null) === $order->paymentIntent && $order->status === 'REFUND_PENDING') {
                    $order->stripeRefund = $object['id'];
                    $order->paymentStatus = 'REFUNDED';
                    $this->workflow->apply($order, 'refund', 'stripe');
                }
            }
            $this->em->persist(new WebhookReceipt($event['id']));
            if ($before !== $order->status) {
                $this->bus->dispatch(new OrderChanged($order->id,$order->status));
            }
        });
    }
}
