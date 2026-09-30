<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class CatalogService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function variant(string $id): Variant
    {
        $v = $this->em->find(Variant::class, $id);
        if (!$v || $v->product->status !== 'PUBLISHED' || $v->status !== 'ACTIVE') {
            throw new DomainError('PRODUCT_UNAVAILABLE', 'Ce produit est indisponible.', 404);
        }
        return $v;
    }
    /** @return array<string, mixed> */
    public function view(Variant $v): array
    {
        $p = $v->product;
        return ['id' => $v->id, 'productId' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'brand' => $p->brand, 'category' => $p->category, 'summary' => $p->summary, 'description' => $p->description, 'status' => $p->status, 'specs' => $p->specs, 'images' => $p->images, 'sku' => $v->sku, 'variantName' => $v->name, 'price' => $v->price, 'currency' => $v->currency, 'available' => $v->available()];
    }
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function search(array $filters, bool $admin = false): array
    {
        $q = $this->em->createQueryBuilder()->select('v', 'p')->from(Variant::class, 'v')->join('v.product', 'p');
        if (!$admin) {
            $q->andWhere("p.status = 'PUBLISHED' AND v.status = 'ACTIVE'");
        }
        foreach (['category', 'brand'] as $field) {
            if (!empty($filters[$field])) {
                $q->andWhere("p.$field = :$field")->setParameter($field, $filters[$field]);
            }
        }
        $field = $filters['specKey'] ?? '';
        if ($field !== '' && ($filters['specValue'] ?? '') !== '') {
            if (!is_string($field) || !isset(SpecificationSchema::FIELDS[$filters['category'] ?? ''][$field])) {
                throw new DomainError('FILTER_INVALID', 'Ce filtre technique ne correspond pas à la catégorie.');
            }
            $connection = $this->em->getConnection();
            $expression = $connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'specs ->> :key' : 'CAST(json_extract(specs, :key) AS TEXT)';
            $key = $connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? $field : '$.'.$field;
            $ids = $connection->fetchFirstColumn('SELECT id FROM product WHERE '.$expression.' = :value', ['key' => $key,'value' => (string)$filters['specValue']]);
            $q->andWhere('p.id IN (:specIds)')->setParameter('specIds', $ids ?: ['none']);
        }
        if (!empty($filters['q'])) {
            $q->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.brand) LIKE :q OR LOWER(v.sku) LIKE :q OR LOWER(p.description) LIKE :q')->setParameter('q', '%'.mb_strtolower(substr((string) $filters['q'], 0, 100)).'%');
        }
        if (($filters['stock'] ?? '') === 'true') {
            $q->andWhere('v.physical > v.reserved');
        }
        foreach (['min' => ' >= ', 'max' => ' <= '] as $key => $op) {
            if (isset($filters[$key]) && ctype_digit((string) $filters[$key])) {
                $q->andWhere('v.price'.$op.':'.$key)->setParameter($key, (int) $filters[$key]);
            }
        }
        $sort = ['price_asc' => ['v.price','ASC'], 'price_desc' => ['v.price','DESC'], 'name' => ['p.name','ASC']][$filters['sort'] ?? 'name'] ?? ['p.name','ASC'];
        $page = max(1, min(10000, (int) ($filters['page'] ?? 1)));
        $limit = 12;
        $q->orderBy(...$sort)->addOrderBy('v.id', 'ASC')->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $paginator = new Paginator($q, false);
        return ['items' => array_map($this->view(...), iterator_to_array($paginator)), 'total' => count($paginator), 'page' => $page, 'limit' => $limit];
    }
}
