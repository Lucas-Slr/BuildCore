<?php

declare(strict_types=1);

namespace App\Notification;

use App\Order\Purchase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\{HubInterface,Update};

#[AsMessageHandler(fromTransport:'realtime')]
final class RealtimeHandler
{
    public function __construct(private DeliveryService $delivery, private HubInterface $hub)
    {
    }
    public function __invoke(OrderChanged $message): void
    {
        $this->delivery->once($message, 'mercure', function (Purchase $order): void {
            $this->hub->publish(new Update('https://buildcore.local/orders/'.$order->id, json_encode(['id' => $order->id,'status' => $order->status], JSON_THROW_ON_ERROR), true));
        });
    }
}
