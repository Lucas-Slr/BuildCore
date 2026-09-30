<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalog\Variant;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class PostgreSqlConcurrencyTest extends KernelTestCase
{
    public function testTwoProcessesCompeteForTheLastUnit(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('Nécessite PostgreSQL : exécuté en CI, SQLite ne prouve pas la concurrence.');
        }
        $v = $em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
        $v->physical = 1;
        $v->reserved = 0;
        $em->flush();
        $processes = [];
        for ($i = 0;$i < 2;$i++) {
            $p = new Process([PHP_BINARY,__DIR__.'/support/reserve.php',$v->id], dirname(__DIR__), ['APP_ENV' => 'test']);
            $p->start();
            $processes[] = $p;
        }
        $success = 0;
        foreach ($processes as $p) {
            $p->wait();
            self::assertTrue($p->isSuccessful(), $p->getErrorOutput());
            $success += (int)$p->getOutput();
        }
        self::assertSame(1, $success);
        $em->refresh($v);
        self::assertSame(1, $v->reserved);
        self::assertSame(0, $v->available());
    }
}
