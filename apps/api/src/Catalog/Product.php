<?php

declare(strict_types=1);

namespace App\Catalog;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\Index(name:'idx_product_category_status', columns:['category','status'])]
class Product
{
    #[ORM\Id, ORM\Column(length: 36)] public string $id;
    #[ORM\Column(length: 180)] public string $name = '';
    #[ORM\Column(length: 180, unique: true)] public string $slug = '';
    #[ORM\Column(length: 80)] public string $brand = '';
    #[ORM\Column(length: 40)] public string $category = '';
    #[ORM\Column(type: 'text')] public string $description = '';
    #[ORM\Column(length: 280)] public string $summary = '';
    #[ORM\Column(length: 20)] public string $status = 'DRAFT';
    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')] public array $specs = [];
    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')] public array $images = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $updatedAt;
    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }
}
