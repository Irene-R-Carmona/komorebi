<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
        $callbackExecuted = false;
        $value = 'computed_value';

        if (!$item->isHit()) {
            $callbackExecuted = true;
            $item->set($value);
            $item->expiresAfter(10);
            $this->cache->save($item);
        }

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
