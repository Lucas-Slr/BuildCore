<?php

declare(strict_types=1);

namespace App\Payment;

use App\Shared\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentController extends AbstractController
{
    #[Route('/api/v1/payments/webhook', methods:['POST'])]
    public function webhook(Request $r, PaymentService $payments, #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')] string $secret): JsonResponse
    {
        if (!$secret) {
            throw new DomainError('WEBHOOK_UNAVAILABLE', 'Webhook non configuré.', 503);
        }
        try {
            $event = \Stripe\Webhook::constructEvent($r->getContent(), $r->headers->get('Stripe-Signature', ''), $secret);
        } catch (\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException) {
            throw new DomainError('WEBHOOK_SIGNATURE_INVALID', 'Signature invalide.', 400);
        }
        $payments->process($event->toArray());
        return $this->json(['received' => true]);
    }
}
