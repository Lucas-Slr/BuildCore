<?php

declare(strict_types=1);

namespace App\Administration;

use App\Catalog\{Product,Variant,CatalogService,SpecificationSchema};
use App\Customer\Customer;
use App\Inventory\StockAdjustment;
use App\Order\{Purchase,OrderWorkflow,OrderController};
use App\Payment\StripeGateway;
use App\Notification\OrderChanged;
use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/api/v1/admin')]
final class AdminController extends AbstractController
{
    #[Route('/dashboard', methods:['GET'])]
    public function dashboard(EntityManagerInterface $em): JsonResponse
    {
        $c = $em->getConnection();
        return $this->json(['revenue' => (int) $c->fetchOne("SELECT COALESCE(SUM(total),0) FROM purchase WHERE payment_status = 'PAID'"),'pending' => (int) $c->fetchOne("SELECT COUNT(*) FROM purchase WHERE status = 'PENDING_PAYMENT'"),'preparing' => (int) $c->fetchOne("SELECT COUNT(*) FROM purchase WHERE status IN ('PAID','PREPARING')"),'lowStock' => $c->fetchAllAssociative('SELECT sku, physical, reserved FROM variant WHERE physical - reserved <= low_threshold LIMIT 20')]);
    }
    #[Route('/products', methods:['GET'])]
    public function products(Request $r, CatalogService $catalog): JsonResponse
    {
        return $this->json($catalog->search($r->query->all(), true));
    }
    #[Route('/products', methods:['POST'])]
    #[Route('/products/{id}', methods:['PUT'])]
    public function save(Request $r, EntityManagerInterface $em, SpecificationSchema $schema, CatalogService $catalog, ?string $id = null): JsonResponse
    {
        $d = $r->toArray();
        $v = $id ? $em->find(Variant::class, $id) : new Variant(new Product());
        if (!$v) {
            throw $this->createNotFoundException();
        }
        $p = $v->product;
        foreach (['name' => 180,'slug' => 180,'brand' => 80,'summary' => 280,'description' => 10000,'category' => 40,'status' => 20] as $field => $max) {
            $value = $d[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || strlen($value) > $max) {
                throw new DomainError('PRODUCT_INVALID', "Le champ $field est invalide.");
            } $p->$field = trim($value);
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $p->slug) || !in_array($p->status, ['DRAFT','PUBLISHED','ARCHIVED'], true)) {
            throw new DomainError('PRODUCT_INVALID', 'Slug ou statut invalide.');
        }
        $schema->validate($p->category, $d['specs'] ?? []);
        $p->specs = $d['specs'] ?? [];
        if (!is_int($d['price'] ?? null) || $d['price'] < 1 || $d['price'] > 10000000 || !is_string($d['sku'] ?? null) || !preg_match('/^[A-Z0-9-]{3,80}$/', $d['sku'])) {
            throw new DomainError('VARIANT_INVALID', 'Le prix en centimes ou le SKU est invalide.');
        }
        $v->price = $d['price'];
        $v->sku = $d['sku'];
        $p->updatedAt = $v->updatedAt = new \DateTimeImmutable();
        $em->persist($p);
        $em->persist($v);
        $em->flush();
        return $this->json($catalog->view($v), $id ? 200 : 201);
    }
    #[Route('/stock/{id}', methods:['POST'])]
    public function stock(string $id, Request $r, EntityManagerInterface $em): JsonResponse
    {
        $d = $r->toArray();
        $qty = $d['quantity'] ?? null;
        $reason = $d['reason'] ?? '';
        if (!is_int($qty) || $qty < 0 || $qty > 100000 || !is_string($reason) || strlen(trim($reason)) < 3 || strlen($reason) > 280) {
            throw new DomainError('STOCK_INVALID', 'Indiquez une quantité entière positive et une raison.');
        }
        $u = $this->getUser();
        if (!$u instanceof Customer) {
            throw $this->createAccessDeniedException();
        }
        $em->wrapInTransaction(function () use ($em, $id, $qty, $reason, $u): void {
            $v = $em->find(Variant::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$v) {
                throw $this->createNotFoundException();
            }
            if ($qty < $v->reserved) {
                throw new DomainError('RESERVED_STOCK', 'La quantité ne peut pas être inférieure au stock réservé.', 409);
            }
            $em->persist(new StockAdjustment($id, $v->physical, $qty, $reason, $u->id));
            $v->physical = $qty;
        });
        return $this->json(['updated' => true]);
    }
    #[Route('/stock/{id}/history', methods:['GET'])]
    public function stockHistory(string $id, EntityManagerInterface $em): JsonResponse
    {
        return $this->json(['items' => $em->getRepository(StockAdjustment::class)->findBy(['variantId' => $id], ['createdAt' => 'DESC'], 100)]);
    }
    #[Route('/orders', methods:['GET'])]
    public function orders(Request $r, EntityManagerInterface $em): JsonResponse
    {
        $query = $em->createQueryBuilder()->select('o')->from(Purchase::class, 'o');
        $status = $r->query->getString('status');
        $search = mb_substr(trim($r->query->getString('q')), 0, 180);
        if ($status !== '') {
            $query->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ($search !== '') {
            $query->andWhere('LOWER(o.number) LIKE :q OR LOWER(o.email) LIKE :q OR LOWER(o.tracking) LIKE :q')->setParameter('q', '%'.mb_strtolower($search).'%');
        }
        foreach (['from' => '>=', 'to' => '<'] as $field => $operator) {
            $value = $r->query->getString($field);
            if ($value === '') {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                throw new DomainError('DATE_INVALID', 'Utilisez une date au format AAAA-MM-JJ.');
            }
            $query->andWhere('o.createdAt '.$operator.' :'.$field)->setParameter($field, $field === 'to' ? $date->modify('+1 day') : $date);
        }
        $total = (int) (clone $query)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
        $page = max(1, $r->query->getInt('page', 1));
        $items = $query->orderBy('o.createdAt', 'DESC')->addOrderBy('o.id', 'DESC')->setFirstResult(($page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();
        return $this->json(['items' => array_map(OrderController::view(...), $items), 'total' => $total, 'page' => $page, 'limit' => 20]);
    }
    #[Route('/orders/{id}/transition', methods:['POST'])]
    public function transition(string $id, Request $r, EntityManagerInterface $em, OrderWorkflow $workflow, MessageBusInterface $bus): JsonResponse
    {
        $d = $r->toArray();
        $transition = $d['transition'] ?? '';
        if (!in_array($transition, ['prepare','ship','deliver'], true)) {
            throw new DomainError('TRANSITION_INVALID', 'Transition administrative non autorisée.');
        }
        $o = $em->wrapInTransaction(function () use ($em, $id, $d, $transition, $workflow, $bus): Purchase {
            $o = $em->find(Purchase::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$o) {
                throw $this->createNotFoundException();
            }
            if ($transition === 'ship') {
                $tracking = $d['tracking'] ?? '';
                if (!is_string($tracking) || strlen($tracking) < 3 || strlen($tracking) > 180) {
                    throw new DomainError('TRACKING_REQUIRED', 'Indiquez le numéro de suivi.');
                } $o->tracking = $tracking;
            }
            $workflow->apply($o, $transition, $this->getUser()?->getUserIdentifier() ?? 'admin');
            $bus->dispatch(new OrderChanged($o->id, $o->status));
            return $o;
        });
        return $this->json(OrderController::view($o));
    }
    #[Route('/orders/{id}/refund', methods:['POST'])]
    public function refund(string $id, EntityManagerInterface $em, OrderWorkflow $workflow, StripeGateway $stripe): JsonResponse
    {
        $o = $em->wrapInTransaction(function () use ($em, $id, $workflow): Purchase {
            $o = $em->find(Purchase::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$o) {
                throw $this->createNotFoundException();
            }
            if ($o->status !== 'REFUND_PENDING') {
                if ($o->paymentStatus !== 'PAID' || !$o->paymentIntent) {
                    throw new DomainError('REFUND_INVALID', 'Ce paiement ne peut pas être remboursé.', 409);
                } $workflow->apply($o, 'request_refund', 'admin');
            }
            return $o;
        });
        $o->stripeRefund = $stripe->refund($o);
        $em->flush();
        return $this->json(OrderController::view($o));
    }
}
