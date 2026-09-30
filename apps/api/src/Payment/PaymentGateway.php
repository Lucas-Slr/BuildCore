<?php

declare(strict_types=1);

namespace App\Payment;

use App\Order\Purchase;

interface PaymentGateway
{
    public function ready(): void;
    /** @return array{id:string,url:string} */
    public function create(Purchase $order): array;
    public function refund(Purchase $order): string;
    public function isExpired(Purchase $order): bool;
}
