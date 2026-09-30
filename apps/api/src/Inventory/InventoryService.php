<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Catalog\Variant;
use App\Order\Purchase;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;

final class InventoryService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function reserve(Variant $v, int $qty): void
    {
        $changed = $this->em->getConnection()->executeStatement('UPDATE variant SET reserved = reserved + :qty WHERE id = :id AND physical - reserved >= :qty AND status = :status', ['qty' => $qty,'id' => $v->id,'status' => 'ACTIVE'], ['qty' => \Doctrine\DBAL\ParameterType::INTEGER]);
        if ($changed !== 1) {
            throw new DomainError('INSUFFICIENT_STOCK', 'Le stock a changé. Vérifiez votre panier.', 409);
        }
        $this->em->refresh($v);
    }
    public function finish(Purchase $order, bool $sold): void
    {
        if ($order->reservationStatus !== 'ACTIVE') {
            return;
        }
        $lines = $order->lines;
        usort($lines, fn ($a, $b) => strcmp($a['variantId'], $b['variantId']));
        foreach ($lines as $line) {
            $sql = $sold ? 'UPDATE variant SET reserved = reserved - :qty, physical = physical - :qty WHERE id = :id AND reserved >= :qty AND physical >= :qty' : 'UPDATE variant SET reserved = reserved - :qty WHERE id = :id AND reserved >= :qty';
            if ($this->em->getConnection()->executeStatement($sql, ['qty' => $line['quantity'],'id' => $line['variantId']], ['qty' => \Doctrine\DBAL\ParameterType::INTEGER]) !== 1) {
                throw new \LogicException('Inventory reservation invariant violated');
            }
        }
        $order->reservationStatus = $sold ? 'SOLD' : 'RELEASED';
    }
}
