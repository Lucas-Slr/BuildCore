<?php

declare(strict_types=1);

namespace App\Notification;

use App\Catalog\Variant;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler(fromTransport: 'emails')]
final class LowStockHandler
{
    public function __construct(private EntityManagerInterface $em, private MailerInterface $mailer, #[Autowire('%env(STOCK_ALERT_EMAIL)%')] private string $recipient)
    {
    }
    public function __invoke(LowStock $message): void
    {
        $this->em->wrapInTransaction(function () use ($message): void {
            $variant = $this->em->find(Variant::class, $message->variantId, LockMode::PESSIMISTIC_WRITE);
            if (!$variant || $variant->available() > $variant->lowThreshold || $variant->status !== 'ACTIVE' || $variant->product->status !== 'PUBLISHED') {
                return;
            }
            // Ignore yesterday's queued snapshot; the scheduler will enqueue today's alert.
            if ($message->day !== gmdate('Y-m-d')) {
                return;
            }
            $key = 'stock:'.$message->variantId.':'.$message->day;
            if ($this->em->find(NotificationReceipt::class, $key)) {
                return;
            }
            $this->mailer->send((new Email())->from('stock@buildcore.test')->to($this->recipient)->subject('Stock faible : '.$variant->sku)->text($variant->product->name.' ('.$variant->sku.') : '.$variant->available().' unité(s) disponible(s). Seuil : '.$variant->lowThreshold.'. Consultez le back-office pour ajuster le stock.'));
            $this->em->persist(new NotificationReceipt($key));
        });
    }
}
