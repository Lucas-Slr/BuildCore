<?php

declare(strict_types=1);

namespace App\Notification;

use App\Order\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsMessageHandler(fromTransport:'emails')]
final class OrderChangedHandler
{
    public function __construct(private DeliveryService $delivery, private MailerInterface $mailer)
    {
    }
    public function __invoke(OrderChanged $message): void
    {
        $this->delivery->once($message, 'email', function (Purchase $order) use ($message): void {
            $this->mailer->send((new Email())->from('orders@buildcore.test')->to($order->email)->subject('Commande '.$order->number)->text('Le statut de votre commande est : '.$message->status.'. Consultez votre espace client.'));
        });
    }
}
