<?php

declare(strict_types=1);

namespace App\Notification;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'notification_receipt')]
class NotificationReceipt
{
    #[ORM\Id,ORM\Column(length:180)] public string $id;
    #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $deliveredAt;
    public function __construct(string $id)
    {
        $this->id = $id;
        $this->deliveredAt = new \DateTimeImmutable();
    }
}
