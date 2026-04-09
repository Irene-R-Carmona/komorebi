<?php

/**
 * Script de Benchmark de Performance
 *
 * Mide el rendimiento de consultas optimizadas vs no optimizadas
 * Ejecutar: php scripts/benchmark.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Cache;
use App\Core\Database;
use App\Services\MenuService;
use App\Services\ProductService;

// Colores para consola
const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const RESET = "\033[0m";

echo BLUE . "┌─────────────────────────────────────────────┐\n";
echo "│   Komorebi Café - Performance Benchmark     │\n";
echo "└─────────────────────────────────────────────┘\n" . RESET;

/**
 * Mide el tiempo de ejecución de una función
 */
function benchmark(string $name, callable $fn, int $iterations = 10): array
{
    $times = [];

    echo YELLOW . "\n🔍 Ejecutando: $name ($iterations iteraciones)\n" . RESET;

    for ($i = 0; $i < $iterations; $i++) {
        // Limpiar cache antes de cada iteración
        Cache::flush();

        $start = microtime(true);
        $result = $fn();
        $end = microtime(true);

        $time = ($end - $start) * 1000; // ms
        $times[] = $time;

        echo '.';
    }

    $avg = array_sum($times) / count($times);
    $min = min($times);
    $max = max($times);

    echo GREEN . " ✓\n" . RESET;
    echo '   Promedio: ' . number_format($avg, 2) . " ms\n";
    echo '   Mín: ' . number_format($min, 2) . ' ms | Máx: ' . number_format($max, 2) . " ms\n";

    return [
        'average' => $avg,
        'min' => $min,
        'max' => $max,
        'result' => $result,
    ];
}

/**
 * Cuenta queries ejecutadas
 */
final class QueryCounter
{
    public static int $count = 0;

    public static function reset(): void
    {
        self::$count = 0;
    }

    public static function increment(): void
    {
        self::$count++;
    }
}

// Inicializar servicios
$productService = new ProductService();
$menuService = new MenuService();
$db = Database::getConnection();

echo "\n" . BLUE . "═══════════════════════════════════════════════\n";
echo "  TEST 1: ProductService.getAll() - Con Cache\n";
echo "═══════════════════════════════════════════════\n" . RESET;

// Sin cache
$noCacheResult = benchmark('Sin cache', static function () use ($productService) {
    Cache::flush();

    return $productService->getAll();
}, 5);

// Con cache
$withCacheResult = benchmark('Con cache (primera carga)', static function () use ($productService) {
    Cache::flush();

    return $productService->getAll();
}, 5);

// Desde cache
$fromCacheResult = benchmark('Desde cache (hits)', static function () use ($productService) {
    return $productService->getAll();
}, 20);

$improvement = (($noCacheResult['average'] - $fromCacheResult['average']) / $noCacheResult['average']) * 100;

echo GREEN . "\n📊 Mejora con cache: " . number_format($improvement, 1) . "%\n" . RESET;
echo '   Sin cache: ' . number_format($noCacheResult['average'], 2) . " ms\n";
echo '   Desde cache: ' . number_format($fromCacheResult['average'], 2) . " ms\n";

// ═══════════════════════════════════════════════════════════

echo "\n" . BLUE . "═══════════════════════════════════════════════\n";
echo "  TEST 2: Paginación vs Carga Completa\n";
echo "═══════════════════════════════════════════════\n" . RESET;

$allResult = benchmark('getAll() - Carga completa', static function () use ($productService) {
    Cache::flush();

    return $productService->getAll();
}, 5);

$paginatedResult = benchmark('getAllPaginated(1, 20)', static function () use ($productService) {
    Cache::flush();

    return $productService->getAllPaginated(1, 20);
}, 5);

$paginationImprovement = (($allResult['average'] - $paginatedResult['average']) / $allResult['average']) * 100;

echo GREEN . "\n📊 Mejora con paginación: " . number_format($paginationImprovement, 1) . "%\n" . RESET;
echo '   Total de productos: ' . count($allResult['result']) . "\n";
echo "   Productos por página: 20\n";

// ═══════════════════════════════════════════════════════════

echo "\n" . BLUE . "═══════════════════════════════════════════════\n";
echo "  TEST 3: Queries N+1 - MenuService\n";
echo "═══════════════════════════════════════════════\n" . RESET;

// Simular versión con N+1 (consulta original sin optimizar)
$n1SimulationResult = benchmark('Simulación N+1 (múltiples queries)', static function () use ($db) {
    // Obtener productos
    $stmt = $db->query("SELECT * FROM products WHERE is_active = 1 AND product_type = 'item' LIMIT 20");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada producto, obtener alérgenos (N queries)
    foreach ($products as &$product) {
        $stmt = $db->prepare('
            SELECT a.name, a.icon
            FROM allergens a
            JOIN product_allergens pa ON a.id = pa.allergen_id
            WHERE pa.product_id = :product_id
        ');
        $stmt->execute(['product_id' => $product['id']]);
        $product['allergens'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $products;
}, 5);

// Versión optimizada con JOIN
$optimizedResult = benchmark('Optimizado con LEFT JOIN', static function () use ($menuService) {
    return $menuService->getProductsByCategory(1);
}, 5);

$queryImprovement = (($n1SimulationResult['average'] - $optimizedResult['average']) / $n1SimulationResult['average']) * 100;

echo GREEN . "\n📊 Mejora eliminando N+1: " . number_format($queryImprovement, 1) . "%\n" . RESET;
echo '   N+1 (1 + N queries): ' . number_format($n1SimulationResult['average'], 2) . " ms\n";
echo '   JOIN (1 query): ' . number_format($optimizedResult['average'], 2) . " ms\n";

// ═══════════════════════════════════════════════════════════

echo "\n" . BLUE . "═══════════════════════════════════════════════\n";
echo "  RESUMEN GENERAL\n";
echo "═══════════════════════════════════════════════\n" . RESET;

echo "\n✅ Cache Redis:\n";
echo '   • Mejora: ' . number_format($improvement, 1) . "%\n";
echo '   • Tiempo sin cache: ' . number_format($noCacheResult['average'], 2) . " ms\n";
echo '   • Tiempo con cache: ' . number_format($fromCacheResult['average'], 2) . " ms\n";

echo "\n✅ Paginación:\n";
echo '   • Mejora: ' . number_format($paginationImprovement, 1) . "%\n";
echo '   • Carga completa: ' . number_format($allResult['average'], 2) . " ms\n";
echo '   • Paginado (20 items): ' . number_format($paginatedResult['average'], 2) . " ms\n";

echo "\n✅ Eliminación N+1:\n";
echo '   • Mejora: ' . number_format($queryImprovement, 1) . "%\n";
echo '   • N+1 queries: ' . number_format($n1SimulationResult['average'], 2) . " ms\n";
echo '   • Single JOIN: ' . number_format($optimizedResult['average'], 2) . " ms\n";

$totalImprovement = ($improvement + $paginationImprovement + $queryImprovement) / 3;

echo "\n" . GREEN . '🎯 Mejora promedio total: ' . number_format($totalImprovement, 1) . "%\n" . RESET;

echo "\n" . BLUE . "═══════════════════════════════════════════════\n";
echo "  Benchmark completado ✓\n";
echo "═══════════════════════════════════════════════\n" . RESET;
