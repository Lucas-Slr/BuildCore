<?php

declare(strict_types=1);

namespace App\Checkout;

use App\Cart\CartService;
use App\Catalog\CatalogService;
use App\Customer\Customer;
use App\Customer\AddressValidator;
use App\Inventory\InventoryService;
use App\Order\Purchase;
use App\Payment\PaymentGateway;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class CheckoutService
{
    public function __construct(private EntityManagerInterface $em, private CatalogService $catalog, private InventoryService $inventory, private PaymentGateway $stripe)
    {
    }
    /** @param array<string, mixed> $data */
    public function checkout(Customer $customer, CartService $cart, array $data, string $key): Purchase
    {
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/', $key)) {
            throw new DomainError('IDEMPOTENCY_REQUIRED', 'Une clé de tentative valide est requise.');
        }
        $key = $customer->id.':'.$key;
        $existing = $this->em->getRepository(Purchase::class)->findOneBy(['idempotencyKey' => $key]);
        if ($existing) {
            return $this->session($existing);
        }
        $this->stripe->ready();
        $address = AddressValidator::validate($data['address'] ?? []);
        $delivery = $data['delivery'] ?? 'standard';
        if (!in_array($delivery, ['standard','express'], true)) {
            throw new DomainError('DELIVERY_INVALID', 'Mode de livraison invalide.');
        }
        $quantities = $cart->quantities();
        if (!$quantities) {
            throw new DomainError('CART_EMPTY', 'Votre panier est vide.');
        } ksort($quantities);
        $expected = $data['expectedLines'] ?? null;
        if (!is_array($expected) || count($expected) !== count($quantities)) {
            throw new DomainError('QUOTE_CHANGED', 'Le panier a changé. Vérifiez les nouveaux prix et confirmez à nouveau.', 409);
        }
        $quote = [];
        foreach ($expected as $line) {
            if (!is_array($line) || !is_string($line['id'] ?? null) || !is_int($line['price'] ?? null) || !is_int($line['quantity'] ?? null) || isset($quote[$line['id']])) {
                throw new DomainError('QUOTE_INVALID', 'Le récapitulatif de commande est invalide.');
            }
            $quote[$line['id']] = $line;
        }
        $order = $this->em->wrapInTransaction(function () use ($customer, $key, $address, $delivery, $quantities, $quote): Purchase {
            // Lock the account as well: concurrent retries with the same key must converge.
            $this->em->lock($customer, LockMode::PESSIMISTIC_WRITE);
            $existing = $this->em->getRepository(Purchase::class)->findOneBy(['idempotencyKey' => $key]);
            if ($existing) {
                return $existing;
            }
            $o = new Purchase($customer, $key);
            $o->shippingAddress = $o->billingAddress = $address;
            $o->delivery = $delivery;
            foreach ($quantities as $id => $qty) {
                $v = $this->catalog->variant($id);
                $this->em->refresh($v, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($v->product);
                if ($v->product->status !== 'PUBLISHED' || $qty < 1 || $qty > 10) {
                    throw new DomainError('CART_INVALID', 'Vérifiez les produits du panier.', 409);
                }
                if (!isset($quote[$id]) || $quote[$id]['price'] !== $v->price || $quote[$id]['quantity'] !== $qty) {
                    throw new DomainError('QUOTE_CHANGED', 'Le panier a changé. Vérifiez les nouveaux prix et confirmez à nouveau.', 409);
                }
                $this->inventory->reserve($v, $qty);
                $o->lines[] = ['variantId' => $id,'name' => $v->product->name,'sku' => $v->sku,'unitPrice' => $v->price,'quantity' => $qty,'amount' => $v->price * $qty,'specs' => $v->product->specs];
                $o->total += $v->price * $qty;
            }
            $o->shipping = $delivery === 'express' ? 1990 : ($o->total >= 150000 ? 0 : 990);
            $o->total += $o->shipping;
            $this->em->persist($o);
            return $o;
        });
        // No external network request while stock locks are held. Retry uses the order UUID.
        return $this->session($order);
    }
    private function session(Purchase $order): Purchase
    {
        if ($order->status !== 'PENDING_PAYMENT' || $order->expiresAt <= new \DateTimeImmutable()) {
            throw new DomainError('RESERVATION_EXPIRED', 'Cette tentative de paiement a expiré.', 409);
        }
        if (!$order->stripeSession) {
            try {
                $session = $this->stripe->create($order);
                $order->stripeSession = $session['id'];
                $order->checkoutUrl = $session['url'];
                $this->em->flush();
            } catch (\Stripe\Exception\ApiErrorException) {
                throw new DomainError('PAYMENT_UNAVAILABLE', 'Stripe est indisponible. Réessayez la même tentative ; la réservation expirera automatiquement.', 503);
            }
        }
        return $order;
    }
}
