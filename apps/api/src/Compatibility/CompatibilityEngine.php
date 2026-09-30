<?php

declare(strict_types=1);

namespace App\Compatibility;

use App\Catalog\Variant;

final class CompatibilityEngine
{
    /**
     * @param list<Variant> $variants
     * @return list<array<string, mixed>>
     */
    public function evaluate(array $variants, int $budget = 0): array
    {
        $parts = [];
        $results = [];
        $total = 0;
        $add = static function (string $code, string $message, array $components = [], string $suggestion = 'Choisissez un composant compatible.', string $severity = 'ERROR') use (&$results): void {
            $results[] = compact('code', 'severity', 'message', 'components', 'suggestion');
        };
        foreach ($variants as $v) {
            $category = $v->product->category;
            if (isset($parts[$category])) {
                $add('DUPLICATE_CATEGORY', 'Un seul composant par catégorie est accepté.', [$v->id]);
            }
            $parts[$category] = $v;
            $total += $v->price;
            if ($v->available() < 1 || $v->product->status !== 'PUBLISHED' || $v->status !== 'ACTIVE') {
                $add('COMPONENT_UNAVAILABLE', 'Un composant est indisponible.', [$v->id], 'Choisissez un composant en stock.');
            }
        }
        foreach (['cpu','motherboard','memory','psu','case','storage','cooler'] as $required) {
            if (!isset($parts[$required])) {
                $add('MISSING_'.strtoupper($required), "Le composant $required est manquant.", [], "Ajoutez un composant $required.");
            }
        }
        $pair = static function (string $a, string $b, callable $invalid, string $code, string $message) use ($parts, $add): void {
            if (isset($parts[$a], $parts[$b]) && $invalid($parts[$a]->product->specs, $parts[$b]->product->specs)) {
                $add($code, $message, [$parts[$a]->id, $parts[$b]->id]);
            }
        };
        $pair('cpu', 'motherboard', fn ($a, $b) => $a['socket'] !== $b['socket'], 'SOCKET_MISMATCH', 'Le processeur et la carte mère utilisent des sockets différents.');
        $pair('memory', 'motherboard', fn ($a, $b) => $a['memoryType'] !== $b['memoryType'], 'MEMORY_TYPE_MISMATCH', 'Le type de mémoire est incompatible avec la carte mère.');
        $pair('memory', 'motherboard', fn ($a, $b) => $a['modules'] > $b['memorySlots'] || $a['capacity'] > $b['maxMemory'], 'MEMORY_CAPACITY_EXCEEDED', 'La mémoire dépasse le nombre de slots ou la capacité acceptée.');
        $pair('motherboard', 'case', fn ($a, $b) => !in_array($a['formFactor'], $b['motherboardFormats'], true), 'BOARD_FORMAT_MISMATCH', 'Le format de carte mère ne rentre pas dans ce boîtier.');
        $pair('gpu', 'case', fn ($a, $b) => $a['length'] > $b['maxGpuLength'], 'GPU_TOO_LONG_FOR_CASE', 'La carte graphique est trop longue pour ce boîtier.');
        $pair('psu', 'case', fn ($a, $b) => !in_array($a['formFactor'], $b['psuFormats'], true), 'PSU_FORMAT_MISMATCH', 'Le format d’alimentation est incompatible.');
        $pair('cpu', 'cooler', fn ($a, $b) => !in_array($a['socket'], $b['sockets'], true), 'COOLER_SOCKET_MISMATCH', 'Le refroidissement ne prend pas en charge ce socket.');
        $pair('cooler', 'case', fn ($a, $b) => $a['height'] > $b['maxCoolerHeight'], 'COOLER_TOO_TALL', 'Le refroidissement dépasse la hauteur autorisée.');
        $pair('storage', 'motherboard', fn ($a, $b) => !in_array($a['interface'], $b['storageInterfaces'], true), 'STORAGE_INTERFACE_MISMATCH', 'L’interface de stockage n’est pas prise en charge.');
        if (isset($parts['cpu']) && !isset($parts['gpu']) && !($parts['cpu']->product->specs['integratedGraphics'] ?? false)) {
            $add('GPU_REQUIRED', 'Ce processeur nécessite une carte graphique.', [$parts['cpu']->id], 'Ajoutez une carte graphique.');
        }
        $watts = ($parts['cpu']->product->specs['tdp'] ?? 0) + ($parts['gpu']->product->specs['tdp'] ?? 0) + 100;
        $recommended = max((int) ceil($watts * 1.25), $parts['gpu']->product->specs['recommendedWatts'] ?? 0);
        if (isset($parts['psu'])) {
            if ($parts['psu']->product->specs['watts'] < $watts) {
                $add('PSU_INSUFFICIENT', "Puissance insuffisante : minimum estimé $watts W.", [$parts['psu']->id], "Choisissez au moins $recommended W.");
            } elseif ($parts['psu']->product->specs['watts'] < $recommended) {
                $add('PSU_HEADROOM', 'La marge de puissance est réduite.', [$parts['psu']->id], "Alimentation conseillée : $recommended W.", 'WARNING');
            }
        }
        if ($budget > 0 && $total > $budget) {
            $add('OVER_BUDGET', 'La configuration dépasse votre budget.', [], 'Ajustez votre sélection ou votre budget.', 'WARNING');
        }
        $add('ESTIMATE_ONLY', 'La puissance est une estimation ; vérifiez les notices et connecteurs avant montage.', [], 'Aucune performance chiffrée n’est garantie.', 'INFO');
        return $results;
    }
}
