<?php

declare(strict_types=1);

namespace App\Order;

use App\Shared\DomainError;
use Symfony\Component\Workflow\WorkflowInterface;

final class OrderWorkflow
{
    public function __construct(private WorkflowInterface $purchaseStateMachine)
    {
    }
    public function apply(Purchase $order, string $transition, string $actor): void
    {
        if (!$this->purchaseStateMachine->can($order, $transition)) {
            throw new DomainError('TRANSITION_INVALID', 'Cette transition est impossible depuis l’état actuel.', 409);
        }
        $this->purchaseStateMachine->apply($order, $transition);
        $order->record($order->status, $actor);
    }
}
