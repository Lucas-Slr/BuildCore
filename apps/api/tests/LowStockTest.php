<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalog\{Product, Variant};
use App\Notification\{LowStock, LowStockHandler};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;

final class LowStockTest extends KernelTestCase
{
    public function testAlertIsDeduplicatedAndRestockedVariantIsIgnored(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $product = new Product();
        $product->name = 'Stock test';
        $product->slug = 'stock-'.bin2hex(random_bytes(8));
        $product->status = 'PUBLISHED';
        $variant = new Variant($product);
        $variant->sku = strtoupper($product->slug);
        $variant->physical = 2;
        $em->persist($product);
        $em->persist($variant);
        $em->flush();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        $handler = new LowStockHandler($em, $mailer, 'admin@buildcore.test');
        $message = new LowStock($variant->id, gmdate('Y-m-d'));
        $handler($message);
        $handler($message);
        $variant->physical = 20;
        $em->flush();
        $handler($message);
    }
}
