<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los contadores estáticos hit/miss de Cache: que se incrementan correctamente
 * en get() y remember(), que getStats() los devuelve, y que resetStats() los limpia.
 *
 * ¿Qué me quieres demostrar?
 * Que Cache.getStats() refleja fielmente cuántas veces se accedió al cache con y sin hit
 * dentro de un ciclo de request, y que resetStats() los deja a cero.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el incremento de $hits/$misses en get(), si getStats() devuelve claves
 * incorrectas, o si resetStats() deja valores residuales.
 */

namespace Tests\Unit\Core;

use App\Core\Cache;
use PHPUnit\Framework\TestCase;

final class CacheStatsTest extends TestCase
{
    protected function setUp(): void
    {
        // Reinicia contadores y pool entre tests para aislamiento
        Cache::reset();
    }

    protected function tearDown(): void
    {
        Cache::reset();
    }

    public function testGetStatsReturnsZeroInitially(): void
    {
        $stats = Cache::getStats();

        $this->assertSame(0, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }

    public function testGetStatsHasRequiredKeys(): void
    {
        $stats = Cache::getStats();

        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
    }

    public function testMissIncrementedWhenKeyNotFound(): void
    {
        Cache::get('nonexistent-key-' . uniqid());

        $stats = Cache::getStats();
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(0, $stats['hits']);
    }

    public function testHitIncrementedWhenKeyFound(): void
    {
        $key = 'test-hit-' . uniqid();
        Cache::set($key, 'value', 60);

        // Reset stats after set (set doesn't count as hit/miss)
        Cache::resetStats();

        Cache::get($key);

        $stats = Cache::getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }

    public function testMultipleAccessesAccumulate(): void
    {
        $key = 'test-multi-' . uniqid();
        Cache::set($key, 'present', 60);
        Cache::resetStats();

        Cache::get($key);         // hit
        Cache::get($key);         // hit
        Cache::get('missing-1');  // miss
        Cache::get('missing-2');  // miss
        Cache::get('missing-3');  // miss

        $stats = Cache::getStats();
        $this->assertSame(2, $stats['hits']);
        $this->assertSame(3, $stats['misses']);
    }

    public function testResetStatsClearsCounters(): void
    {
        Cache::get('some-key');
        Cache::get('another-key');

        Cache::resetStats();

        $stats = Cache::getStats();
        $this->assertSame(0, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }

    public function testFullResetAlsoClearsStats(): void
    {
        Cache::get('some-key');

        Cache::reset(); // reinicia todo, incluyendo stats

        $stats = Cache::getStats();
        $this->assertSame(0, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }

    public function testRememberCountsMissWhenValueNotCached(): void
    {
        $key = 'remember-miss-' . uniqid();

        Cache::remember($key, fn() => 'generated', 60);

        $stats = Cache::getStats();
        $this->assertSame(1, $stats['misses']);
    }

    public function testRememberCountsHitWhenValueAlreadyCached(): void
    {
        $key = 'remember-hit-' . uniqid();
        Cache::set($key, 'cached-value', 60);
        Cache::resetStats();

        Cache::remember($key, fn() => 'should-not-execute', 60);

        $stats = Cache::getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }
}
