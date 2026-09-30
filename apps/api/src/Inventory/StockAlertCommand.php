<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Catalog\Variant;
use App\Notification\LowStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:stock:alert', description: 'Planifie les alertes quotidiennes de stock faible')]
final class StockAlertCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus)
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $variants = $this->em->createQueryBuilder()->select('v')->from(Variant::class, 'v')->join('v.product', 'p')->where('v.physical - v.reserved <= v.lowThreshold')->andWhere('v.status = :active')->andWhere('p.status = :published')->setParameter('active', 'ACTIVE')->setParameter('published', 'PUBLISHED')->getQuery()->toIterable();
        $count = 0;
        foreach ($variants as $variant) {
            $day = gmdate('Y-m-d');
            if ($this->em->find(\App\Notification\NotificationReceipt::class, 'stock:'.$variant->id.':'.$day)) {
                continue;
            }
            $this->bus->dispatch(new LowStock($variant->id, $day));
            ++$count;
        }
        $output->writeln($count.' alerte(s) planifiée(s), dédupliquées par SKU et jour UTC.');
        return Command::SUCCESS;
    }
}
