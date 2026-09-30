<?php

declare(strict_types=1);

namespace App\Shared;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ApiSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $csrf)
    {
    }
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['request', 16], KernelEvents::EXCEPTION => 'exception', KernelEvents::RESPONSE => 'response'];
    }
    public function request(RequestEvent $event): void
    {
        $r = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($r->getPathInfo(), '/api/') || $r->isMethodSafe() || $r->getPathInfo() === '/api/v1/payments/webhook') {
            return;
        }
        if (!$this->csrf->isTokenValid(new CsrfToken('api', $r->headers->get('X-CSRF-Token')))) {
            throw new DomainError('CSRF_INVALID', 'Votre session a expiré. Rechargez la page.', 403);
        }
        $limit = str_contains($r->getPathInfo(), '/images') ? 6 * 1024 * 1024 : 1024 * 1024;
        if ((int) $r->headers->get('Content-Length', '0') > $limit) {
            throw new DomainError('PAYLOAD_TOO_LARGE', 'La requête est trop volumineuse.', 413);
        }
        if (str_contains($r->headers->get('Content-Type', ''), 'application/json') && $r->getContent() !== '') {
            $data = $r->toArray();
            foreach (['components','address','specs'] as $field) {
                if (isset($data[$field]) && !is_array($data[$field])) {
                    throw new DomainError('INPUT_INVALID', "Le champ $field doit être structuré.");
                }
            }
            foreach (['name','slug','email','password','currentPassword','sku','description','summary','category','status','delivery','tracking','transition','reason','alt','brand'] as $field) {
                if (isset($data[$field]) && !is_string($data[$field])) {
                    throw new DomainError('INPUT_INVALID', "Le champ $field doit être du texte.");
                }
            }
            foreach (['quantity','price','budget','position'] as $field) {
                if (isset($data[$field]) && !is_int($data[$field])) {
                    throw new DomainError('INPUT_INVALID', "Le champ $field doit être un entier.");
                }
            }
        }
    }
    public function exception(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $e = $event->getThrowable();
        $status = $e instanceof DomainError ? $e->status : ($e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);
        if ($e instanceof \Symfony\Component\Security\Core\Exception\AccessDeniedException) {
            $status = 403;
        }
        if ($e instanceof \Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            $status = 409;
        }
        $event->setResponse(new JsonResponse(['error' => ['code' => $e instanceof DomainError ? $e->errorCode : 'HTTP_'.$status, 'message' => $e instanceof DomainError ? $e->getMessage() : match ($status) {
            400,422 => 'Les informations envoyées sont invalides.', 401 => 'Connectez-vous pour continuer.',403 => 'Accès refusé.',404 => 'Ressource introuvable.',409 => 'Cette opération existe déjà.',default => 'Le service est momentanément indisponible.'
        }]], $status));
    }
    public function response(ResponseEvent $event): void
    {
        $h = $event->getResponse()->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            $h->set('Cache-Control', 'no-store');
        }
    }
}
