<?php

declare(strict_types=1);

namespace App\Notification;

final readonly class OrderChanged
{
    public function __construct(public string $orderId, public string $status)
    {
    }
}
