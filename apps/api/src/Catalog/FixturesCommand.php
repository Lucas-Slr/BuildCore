<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Customer\Customer;
use App\Order\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name:'app:fixtures', description:'Charge des données fictives sans effacer les données existantes')]
final class FixturesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, #[Autowire('%kernel.environment%')] private string $environment, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->environment, ['dev','test'], true)) {
            $output->writeln('Fixtures interdites en production.');
            return Command::FAILURE;
        }
        $data = json_decode(file_get_contents($this->projectDir.'/../../infrastructure/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($data as $row) {
            if ($this->em->getRepository(Variant::class)->findOneBy(['sku' => $row['sku']])) {
                continue;
            }
            $p = new Product();
            foreach (['name','slug','brand','category','specs','summary'] as $key) {
                $p->$key = $row[$key];
            }
            $p->description = $row['summary'].' Composant fictif conçu pour les parcours de démonstration BuildCore. Consultez les caractéristiques pour vérifier son intégration.';
            $p->status = 'PUBLISHED';
            $p->images = [['url' => '/assets/components/'.$p->category.'.svg','alt' => $p->name.' — illustration originale','primary' => true,'position' => 0]];
            $v = new Variant($p);
            $v->sku = $row['sku'];
            $v->price = $row['price'];
            $v->physical = $row['stock'];
            $this->em->persist($p);
            $this->em->persist($v);
        }
        foreach (['admin','alice','thomas'] as $name) {
            $email = $name.'@buildcore.test';
            if ($this->em->getRepository(Customer::class)->findOneBy(['email' => $email])) {
                continue;
            }
            $u = new Customer();
            $u->email = $email;
            $u->name = ucfirst($name);
            $u->roles = $name === 'admin' ? ['ROLE_ADMIN','ROLE_CUSTOMER'] : ['ROLE_CUSTOMER'];
            $u->password = $this->hasher->hashPassword($u, 'BuildCore-Demo-2026!');
            $u->addresses = [['id' => 'demo','name' => ucfirst($name).' Démo','street' => '12 rue des Ateliers','city' => 'Lyon','postalCode' => '69002','country' => 'FR']];
            $this->em->persist($u);
        }
        $this->em->flush();
        if ($this->environment === 'dev') {
            $alice = $this->em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
            $variant = $this->em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
            if (!$alice || !$variant) {
                throw new \LogicException('Fixtures de base manquantes.');
            }
            foreach (['PAID','PREPARING','SHIPPED','DELIVERED','PAYMENT_FAILED'] as $status) {
                $key = 'demo-order-'.$status;
                if ($this->em->getRepository(Purchase::class)->findOneBy(['idempotencyKey' => $key])) {
                    continue;
                }
                $order = new Purchase($alice, $key);
                $order->number = 'DEMO-'.$status;
                $order->status = $status;
                $order->paymentStatus = $status === 'PAYMENT_FAILED' ? 'FAILED' : 'PAID';
                $order->reservationStatus = $status === 'PAYMENT_FAILED' ? 'RELEASED' : 'SOLD';
                $order->shippingAddress = $order->billingAddress = $alice->addresses[0];
                $order->shipping = 990;
                $order->total = $variant->price + 990;
                $order->lines = [['variantId' => $variant->id,'name' => $variant->product->name,'sku' => $variant->sku,'unitPrice' => $variant->price,'quantity' => 1,'amount' => $variant->price,'specs' => $variant->product->specs]];
                $order->record($status, 'fixture-demo');
                if (in_array($status, ['SHIPPED','DELIVERED'], true)) {
                    $order->tracking = 'DEMO-SUIVI-001';
                }
                $this->em->persist($order);
            }
            $this->em->flush();
        }
        $output->writeln('Catalogue fictif et comptes de développement chargés.');
        return Command::SUCCESS;
    }
}
