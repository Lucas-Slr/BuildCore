<?php

declare(strict_types=1);

namespace App\Notification;

final readonly class LowStock
{
    public function __construct(public string $variantId, public string $day)
    {
    }
}
