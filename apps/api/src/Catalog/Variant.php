<?php

declare(strict_types=1);

namespace App\Catalog;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'variant')]
class Variant
{
    #[ORM\Id, ORM\Column(length: 36)] public string $id;
    #[ORM\ManyToOne(targetEntity: Product::class), ORM\JoinColumn(nullable: false)] public Product $product;
    #[ORM\Column(length: 80, unique: true)] public string $sku = '';
    #[ORM\Column(length: 120)] public string $name = 'Standard';
    #[ORM\Column] public int $price = 0;
    #[ORM\Column(length: 3)] public string $currency = 'EUR';
    #[ORM\Column] public int $physical = 0;
    #[ORM\Column] public int $reserved = 0;
    #[ORM\Column] public int $lowThreshold = 3;
    #[ORM\Column] public int $weight = 500;
    #[ORM\Column(length: 20)] public string $status = 'ACTIVE';
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $updatedAt;
    public function __construct(Product $product)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->product = $product;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }
    public function available(): int
    {
        return $this->physical - $this->reserved;
    }
}
