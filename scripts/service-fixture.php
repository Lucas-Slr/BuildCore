<?php
declare(strict_types=1);
require dirname(__DIR__).'/apps/api/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/apps/api/.env');
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? '') !== 'dev') { throw new RuntimeException('Development only'); }
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
if (isset($argv[1])) {
    $order = $em->find(App\Order\Purchase::class, $argv[1]);
    if (!$order || !str_starts_with($order->number, 'SERVICE-')) { throw new RuntimeException('Service fixture required'); }
    $container->get('messenger.default_bus')->dispatch(new App\Notification\OrderChanged($order->id, $order->status));
    exit;
}
$customer = $em->getRepository(App\Customer\Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
$order = new App\Order\Purchase($customer, 'service-'.bin2hex(random_bytes(12)));
$order->number = 'SERVICE-'.bin2hex(random_bytes(6));
$order->status = 'PAID';
$order->paymentStatus = 'PAID';
$order->reservationStatus = 'SOLD';
$order->history = [['status' => 'PAID', 'actor' => 'fixture-service-test', 'at' => date(DATE_ATOM)]];
$em->persist($order);
$em->flush();
echo json_encode(['id' => $order->id, 'number' => $order->number], JSON_THROW_ON_ERROR);
