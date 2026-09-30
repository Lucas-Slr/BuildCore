<?php

declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel('test', false);
$kernel->boot();
$connection = $kernel->getContainer()->get('doctrine')->getConnection();
$connection->beginTransaction();
try {
    $count = $connection->executeStatement('UPDATE variant SET reserved = reserved + 1 WHERE id = :id AND physical - reserved >= 1', ['id' => $argv[1]]);
    usleep(200000);
    $connection->commit();
    echo $count;
} catch (Throwable $e) {
    $connection->rollBack();
    throw $e;
}
