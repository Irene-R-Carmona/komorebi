<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? CacheService: comprobación de claves ausentes y lógica de remember.
 * ¿Qué me quieres demostrar? Que hasItem retorna false para claves inexistentes y que remember invoca el callback.
 * ¿Qué va a fallar en este test si se cambia el código? Si hasItem o remember dejan de funcionar correctamente.
 */

namespace Tests\Unit\Services;

use App\Services\CacheService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheService::class)]
final class CacheServiceTest extends TestCase
{
    private CacheService $service;

    protected function setUp(): void
    {
        $this->service = new CacheService();
    }

    public function testHasItemReturnsFalseForNonExistentKey(): void
    {
        $this->assertFalse($this->service->hasItem('__test_nonexistent_key_' . \uniqid()));
    }

    public function testRememberInvokesCallbackWhenKeyNotCached(): void
    {
        $key = '__test_remember_' . \uniqid();
        $invoked = false;
        $callback = function () use (&$invoked): string {
            $invoked = true;

            return 'value';
        };

        $result = $this->service->remember($key, $callback, 1);

        $this->assertTrue($invoked);
        $this->assertSame('value', $result);

        // Cleanup
        $this->service->deleteItem($key);
    }

    public function testRememberReturnsCachedValueWithoutInvokingCallback(): void
    {
        $key = '__test_remember_cached_' . \uniqid();

        $this->service->remember($key, fn () => 'original', 3600);

        $callCount = 0;
        $result = $this->service->remember($key, function () use (&$callCount): string {
            $callCount++;

            return 'should-not-be-called';
        }, 3600);

        $this->assertSame(0, $callCount);
        $this->assertSame('original', $result);

        // Cleanup
        $this->service->deleteItem($key);
    }

    public function testGetItemReturnsUnhitCacheItemForNewKey(): void
    {
        $key = '__test_getItem_' . \uniqid();
        $item = $this->service->getItem($key);

        $this->assertFalse($item->isHit());
    }

    public function testSaveAndGetItemRoundTrip(): void
    {
        $key = '__test_save_' . \uniqid();
        $item = $this->service->getItem($key);
        $item->set('hello');

        $saved = $this->service->save($item);

        $this->assertTrue($saved);
        $this->assertSame('hello', $this->service->getItem($key)->get());

        // Cleanup
        $this->service->deleteItem($key);
    }

    public function testDeleteItemReturnsTrueAndRemovesKey(): void
    {
        $key = '__test_delete_' . \uniqid();
        $item = $this->service->getItem($key);
        $item->set('to-delete');
        $this->service->save($item);

        $deleted = $this->service->deleteItem($key);

        $this->assertTrue($deleted);
        $this->assertFalse($this->service->hasItem($key));
    }

    public function testSaveDeferredAndCommitPersistsItem(): void
    {
        $key = '__test_deferred_' . \uniqid();
        $item = $this->service->getItem($key);
        $item->set('deferred-value');

        $this->service->saveDeferred($item);
        $committed = $this->service->commit();

        $this->assertTrue($committed);
        $this->assertTrue($this->service->hasItem($key));

        // Cleanup
        $this->service->deleteItem($key);
    }
}
