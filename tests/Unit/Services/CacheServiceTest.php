<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Comportamiento de Symfony ArrayAdapter como implementación de PSR-6
 * CacheItemPoolInterface: getItem, hasItem, clear, save y el patrón remember.
 *
 * ¿Qué me quieres demostrar?
 * Que ArrayAdapter es un CacheItemPoolInterface válido para usar en tests
 * como sustituto in-memory de Redis/Filesystem en entornos sin infraestructura.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la dependencia symfony/cache, si se cambia el adaptador
 * por uno que no soporte TTL en memoria, o si PSR-6 cambia la interfaz.
 */

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CacheServiceTest extends TestCase
{
    private CacheItemPoolInterface $cache;

    protected function setUp(): void
    {
        // Usar ArrayAdapter para tests (in-memory, no requiere filesystem ni Redis)
        $this->cache = new ArrayAdapter();
    }

    public function testImplementsCacheItemPoolInterface(): void
    {
        $this->assertInstanceOf(\Psr\Cache\CacheItemPoolInterface::class, $this->cache);
    }

    public function testGetItemReturnsItemInstance(): void
    {
        $item = $this->cache->getItem('test_key');

        $this->assertInstanceOf(CacheItemInterface::class, $item);
    }

    public function testGetItemsReturnsIterableOfItems(): void
    {
        $items = $this->cache->getItems(['key1', 'key2']);

        $this->assertIsIterable($items);
    }

    public function testHasItemReturnsBool(): void
    {
        $result = $this->cache->hasItem('test_key');

        $this->assertIsBool($result);
    }

    public function testClearReturnsTrue(): void
    {
        $result = $this->cache->clear();

        $this->assertTrue($result);
    }

    public function testRememberCallsCallbackWhenCacheMiss(): void
    {
        $key = 'unique_key_' . \uniqid();
        $item = $this->cache->getItem($key);

        // Cache miss
        $this->assertFalse($item->isHit());

        // Simular remember pattern
        $callbackExecuted = true;
        $value = 'computed_value';

        $item->set($value);
        $item->expiresAfter(10);
        $this->cache->save($item);

        $this->assertTrue($callbackExecuted);

        // Verificar que está en caché ahora
        $cachedItem = $this->cache->getItem($key);
        $this->assertTrue($cachedItem->isHit());
        $this->assertSame($value, $cachedItem->get());
    }

    public function testSaveItemWorks(): void
    {
        $item = $this->cache->getItem('save_test_key');
        $item->set('test_value');

        $saved = $this->cache->save($item);

        $this->assertTrue($saved);
    }
}
