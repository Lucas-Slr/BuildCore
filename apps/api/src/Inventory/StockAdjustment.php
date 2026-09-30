<?php

declare(strict_types=1);

namespace App\Inventory;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_adjustment')]
#[ORM\Index(name:'idx_stock_variant_date', columns:['variant_id','created_at'])]
class StockAdjustment
{
    #[ORM\Id, ORM\Column(length: 36)] public string $id;
    #[ORM\Column(length: 36)] public string $variantId;
    #[ORM\Column] public int $beforeQuantity;
    #[ORM\Column] public int $afterQuantity;
    #[ORM\Column(length: 280)] public string $reason;
    #[ORM\Column(length: 36)] public string $actor;
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    public function __construct(string $variantId, int $before, int $after, string $reason, string $actor)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->variantId = $variantId;
        $this->beforeQuantity = $before;
        $this->afterQuantity = $after;
        $this->reason = $reason;
        $this->actor = $actor;
        $this->createdAt = new \DateTimeImmutable();
    }
}
