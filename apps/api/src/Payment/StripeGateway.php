<?php

declare(strict_types=1);

namespace App\Payment;

use App\Order\Purchase;
use App\Shared\DomainError;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class StripeGateway implements PaymentGateway
{
    public function __construct(#[Autowire('%env(STRIPE_SECRET_KEY)%')] private string $key, #[Autowire('%env(APP_ORIGIN)%')] private string $origin)
    {
    }
    private function client(): StripeClient
    {
        if (!str_starts_with($this->key, 'sk_test_')) {
            throw new DomainError('PAYMENT_UNAVAILABLE', 'Stripe test n’est pas encore configuré.', 503);
        }
        return new StripeClient($this->key);
    }
    public function ready(): void
    {
        $this->client();
    }
    /** @return array{id:string,url:string} */
    public function create(Purchase $order): array
    {
        $lines = [];
        foreach ($order->lines as $line) {
            $lines[] = ['quantity' => $line['quantity'],'price_data' => ['currency' => 'eur','unit_amount' => $line['unitPrice'],'product_data' => ['name' => $line['name']]]];
        }
        if ($order->shipping > 0) {
            $lines[] = ['quantity' => 1,'price_data' => ['currency' => 'eur','unit_amount' => $order->shipping,'product_data' => ['name' => 'Livraison '.$order->delivery]]];
        }
        $s = $this->client()->checkout->sessions->create(['mode' => 'payment','payment_method_types' => ['card'],'customer_email' => $order->email,'line_items' => $lines,'client_reference_id' => $order->id,'metadata' => ['order_id' => $order->id],'payment_intent_data' => ['metadata' => ['order_id' => $order->id]],'expires_at' => $order->expiresAt->getTimestamp(),'success_url' => $this->origin.'/paiement/retour?order='.$order->id,'cancel_url' => $this->origin.'/commandes/'.$order->id], ['idempotency_key' => 'checkout-'.$order->id]);
        return ['id' => $s->id,'url' => (string) $s->url];
    }
    public function refund(Purchase $order): string
    {
        $r = $this->client()->refunds->create(['payment_intent' => $order->paymentIntent,'amount' => $order->total,'metadata' => ['order_id' => $order->id]], ['idempotency_key' => 'refund-'.$order->id]);
        return $r->id;
    }
    public function isExpired(Purchase $order): bool
    {
        // An absent local ID does not prove Stripe never created a session.
        // Reconcile by metadata after a network timeout before releasing stock.
        if (!$order->stripeSession) {
            $matches = $this->client()->checkout->sessions->all(['created' => ['gte' => $order->createdAt->getTimestamp() - 60], 'limit' => 100]);
            foreach ($matches->autoPagingIterator() as $candidate) {
                if (($candidate->metadata['order_id'] ?? null) === $order->id) {
                    $order->stripeSession = $candidate->id;
                    break;
                }
            }
            if (!$order->stripeSession) {
                return true;
            }
        }
        $session = $this->client()->checkout->sessions->retrieve($order->stripeSession);
        if ($session->status === 'open') {
            $session = $this->client()->checkout->sessions->expire($order->stripeSession);
        }
        return $session->status === 'expired';
    }
}
