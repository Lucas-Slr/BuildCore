<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalog\{Product,Variant,SpecificationSchema};
use App\Compatibility\CompatibilityEngine;
use App\Shared\DomainError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CompatibilityTest extends TestCase
{
    /** @return array<string,Variant> */
    private function parts(): array
    {
        $rows = json_decode(file_get_contents(__DIR__.'/../../../infrastructure/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        $parts = [];
        foreach ($rows as $r) {
            if (isset($parts[$r['category']]) || !in_array($r['category'], ['cpu','motherboard','memory','gpu','psu','case','storage','cooler'], true)) {
                continue;
            } $p = new Product();
            $p->category = $r['category'];
            $p->specs = $r['specs'];
            $p->status = 'PUBLISHED';
            $v = new Variant($p);
            $v->physical = 10;
            $v->price = $r['price'];
            $parts[$p->category] = $v;
        }
        return $parts;
    }
    public function testCompatibleConfigurationHasNoErrors(): void
    {
        $issues = (new CompatibilityEngine())->evaluate(array_values($this->parts()));
        self::assertSame([], array_values(array_filter($issues, fn ($i) => $i['severity'] === 'ERROR')));
    }
    /** @return iterable<string,array{string,string,mixed,string}> */
    public static function mismatches(): iterable
    {
        yield 'socket' => ['cpu','socket','WRONG','SOCKET_MISMATCH'];
        yield 'memory type' => ['memory','memoryType','DDR4','MEMORY_TYPE_MISMATCH'];
        yield 'memory capacity' => ['memory','capacity',256,'MEMORY_CAPACITY_EXCEEDED'];
        yield 'memory slots' => ['memory','modules',8,'MEMORY_CAPACITY_EXCEEDED'];
        yield 'board format' => ['motherboard','formFactor','EATX','BOARD_FORMAT_MISMATCH'];
        yield 'gpu length' => ['gpu','length',400,'GPU_TOO_LONG_FOR_CASE'];
        yield 'psu format' => ['psu','formFactor','SFX','PSU_FORMAT_MISMATCH'];
        yield 'cooler socket' => ['cooler','sockets',['AT4'],'COOLER_SOCKET_MISMATCH'];
        yield 'cooler height' => ['cooler','height',200,'COOLER_TOO_TALL'];
        yield 'storage' => ['storage','interface','SAS','STORAGE_INTERFACE_MISMATCH'];
        yield 'power' => ['psu','watts',300,'PSU_INSUFFICIENT'];
    }
    #[DataProvider('mismatches')]
    public function testMismatches(string $category, string $field, mixed $value, string $code): void
    {
        $parts = $this->parts();
        $parts[$category]->product->specs[$field] = $value;
        $issues = (new CompatibilityEngine())->evaluate(array_values($parts));
        $match = array_values(array_filter($issues, fn ($i) => $i['code'] === $code));
        self::assertNotEmpty($match);
        self::assertSame('ERROR', $match[0]['severity']);
        self::assertNotEmpty($match[0]['suggestion']);
        self::assertNotEmpty($match[0]['components']);
    }
    public function testPowerHeadroomIsWarning(): void
    {
        $p = $this->parts();
        $p['psu']->product->specs['watts'] = 500;
        $issues = (new CompatibilityEngine())->evaluate(array_values($p));
        self::assertSame('WARNING', array_values(array_filter($issues, fn ($i) => $i['code'] === 'PSU_HEADROOM'))[0]['severity']);
    }
    public function testMissingParts(): void
    {
        self::assertCount(8, (new CompatibilityEngine())->evaluate([]));
    }
    public function testUnavailableAndBudget(): void
    {
        $p = $this->parts();
        $p['gpu']->physical = 0;
        $issues = (new CompatibilityEngine())->evaluate(array_values($p), 100);
        self::assertContains('COMPONENT_UNAVAILABLE', array_column($issues, 'code'));
        self::assertContains('OVER_BUDGET', array_column($issues, 'code'));
    }
    public function testDedicatedGraphicsRequired(): void
    {
        $p = $this->parts();
        unset($p['gpu']);
        $p['cpu']->product->specs['integratedGraphics'] = false;
        self::assertContains('GPU_REQUIRED', array_column((new CompatibilityEngine())->evaluate(array_values($p)), 'code'));
    }
    public function testEveryFixtureHasValidatedSpecs(): void
    {
        $schema = new SpecificationSchema();
        $rows = json_decode(file_get_contents(__DIR__.'/../../../infrastructure/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($rows as $r) {
            $schema->validate($r['category'], $r['specs']);
        }self::assertCount(26, $rows);
    }
    public function testUnstructuredSpecsRejected(): void
    {
        $this->expectException(DomainError::class);
        (new SpecificationSchema())->validate('cpu',['socket' => 'NC5']);
    }
}
