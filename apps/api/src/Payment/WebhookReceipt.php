<?php

declare(strict_types=1);

namespace App\Payment;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'webhook_receipt')]
class WebhookReceipt
{
    #[ORM\Id, ORM\Column(length: 255)] public string $id;
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $processedAt;
    public function __construct(string $id)
    {
        $this->id = $id;
        $this->processedAt = new \DateTimeImmutable();
    }
}
