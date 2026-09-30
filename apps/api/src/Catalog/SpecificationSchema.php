<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Shared\DomainError;

final class SpecificationSchema
{
    public const CATEGORIES = ['cpu', 'motherboard', 'memory', 'gpu', 'psu', 'case', 'storage', 'cooler', 'monitor', 'keyboard', 'mouse', 'headset', 'prebuilt'];
    public const FIELDS = [
        'cpu' => ['socket' => 'string','cores' => 'int','frequency' => 'int','tdp' => 'int','integratedGraphics' => 'bool'],
        'motherboard' => ['socket' => 'string','formFactor' => 'string','memoryType' => 'string','memorySlots' => 'int','maxMemory' => 'int','storageInterfaces' => 'array'],
        'memory' => ['memoryType' => 'string','capacity' => 'int','modules' => 'int','frequency' => 'int'],
        'gpu' => ['length' => 'int','slots' => 'int','tdp' => 'int','recommendedWatts' => 'int'],
        'psu' => ['watts' => 'int','efficiency' => 'string','formFactor' => 'string','connectors' => 'array'],
        'case' => ['motherboardFormats' => 'array','maxGpuLength' => 'int','psuFormats' => 'array','maxCoolerHeight' => 'int'],
        'storage' => ['interface' => 'string','formFactor' => 'string','capacity' => 'int'],
        'cooler' => ['sockets' => 'array','height' => 'int','type' => 'string'],
    ];
    /** @param array<string, mixed> $specs */
    public function validate(string $category, array $specs): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new DomainError('CATEGORY_INVALID', 'Catégorie inconnue.');
        }
        foreach (self::FIELDS[$category] ?? [] as $field => $type) {
            $value = $specs[$field] ?? null;
            $valid = match ($type) {
                'int' => is_int($value) && $value > 0 && $value <= 100000,
                'string' => is_string($value) && strlen($value) > 0 && strlen($value) <= 80,
                'bool' => is_bool($value),
                'array' => is_array($value) && count($value) > 0 && count($value) <= 30 && count(array_filter($value, fn ($v) => is_string($v) && strlen($v) <= 80)) === count($value),
            };
            if (!$valid) {
                throw new DomainError('SPEC_INVALID', "La caractéristique $field est obligatoire ou invalide.");
            }
        }
        if (array_diff(array_keys($specs), array_keys(self::FIELDS[$category] ?? []))) {
            throw new DomainError('SPEC_UNKNOWN', 'Caractéristique non prévue pour cette catégorie.');
        }
    }
}
