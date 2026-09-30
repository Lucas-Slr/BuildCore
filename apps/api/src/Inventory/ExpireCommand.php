<?php

declare(strict_types=1);

namespace App\Inventory;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name:'app:reservations:expire', description:'Planifie la libération des réservations expirées')]
final class ExpireCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new ExpireReservations());
        $output->writeln('Expiration planifiée.');
        return Command::SUCCESS;
    }
}
