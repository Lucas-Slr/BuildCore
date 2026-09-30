<?php

declare(strict_types=1);

namespace App\Compatibility;

use App\Catalog\CatalogService;
use App\Shared\DomainError;
use App\Catalog\Variant;
use Doctrine\ORM\EntityManagerInterface;

final class ConfigurationService
{
    public function __construct(private CatalogService $catalog, private CompatibilityEngine $engine, private EntityManagerInterface $em)
    {
    }
    /**
     * @param list<string> $ids
     * @return array{items:list<array<string,mixed>>}
     */
    public function options(array $ids): array
    {
        $this->check($ids);
        $selected = array_map($this->catalog->variant(...), $ids);
        $candidates = $this->em->createQueryBuilder()->select('v', 'p')->from(Variant::class, 'v')->join('v.product', 'p')->where("p.status = 'PUBLISHED' AND v.status = 'ACTIVE'")->andWhere('p.category IN (:categories)')->setParameter('categories', ['cpu','motherboard','memory','gpu','psu','case','storage','cooler'])->orderBy('p.name', 'ASC')->setMaxResults(200)->getQuery()->getResult();
        $items = [];
        foreach ($candidates as $candidate) {
            $parts = array_values(array_filter($selected, fn ($p) => $p->product->category !== $candidate->product->category));
            $parts[] = $candidate;
            $issues = $this->engine->evaluate($parts);
            $blocking = array_filter($issues, fn ($issue) => $issue['severity'] === 'ERROR' && !str_starts_with($issue['code'], 'MISSING_') && $issue['code'] !== 'GPU_REQUIRED' && in_array($candidate->id, $issue['components'], true));
            $items[] = $this->catalog->view($candidate) + ['compatible' => !$blocking];
        }
        return ['items' => $items];
    }
    /**
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    public function check(array $ids, int $budget = 0, bool $requireValid = false): array
    {
        if (count($ids) > 8 || count(array_filter($ids, 'is_string')) !== count($ids)) {
            throw new DomainError('BUILD_INVALID', 'La sélection est invalide.');
        }
        $parts = array_map($this->catalog->variant(...), $ids);
        $issues = $this->engine->evaluate($parts, $budget);
        $valid = !array_filter($issues, fn ($i) => $i['severity'] === 'ERROR');
        if ($requireValid && !$valid) {
            throw new DomainError('BUILD_INCOMPATIBLE', 'Corrigez les incompatibilités avant de continuer.');
        }
        return ['valid' => $valid, 'issues' => $issues, 'total' => array_sum(array_map(fn ($v) => $v->price, $parts)), 'items' => array_map($this->catalog->view(...), $parts)];
    }
}
