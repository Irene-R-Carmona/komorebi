<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? RateLimitingService: lógica de bloqueo según el estado del caché.
 * ¿Qué me quieres demostrar? Que isBlocked retorna false cuando no hay hit de caché, y true cuando locked_until está en el futuro.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la lógica de locked_until o la clave de retorno.
 */

namespace Tests\Unit\Services;

use App\Services\RateLimitingService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(RateLimitingService::class)]
final class RateLimitingServiceTest extends TestCase
{
    public function testIsBlockedReturnsFalseWhenCacheHasNoEntry(): void
    {
        $itemStub = $this->createStub(CacheItemInterface::class);
        $itemStub->method('isHit')->willReturn(false);

        $cacheStub = $this->createStub(CacheItemPoolInterface::class);
        $cacheStub->method('getItem')->willReturn($itemStub);

        $service = new RateLimitingService($cacheStub);
        $result = $service->isBlocked('login', 'user@example.com');

        $this->assertFalse($result['blocked']);
    }

    public function testIsBlockedReturnsTrueWhenLockedUntilIsInFuture(): void
    {
        $itemStub = $this->createStub(CacheItemInterface::class);
        $itemStub->method('isHit')->willReturn(true);
        $itemStub->method('get')->willReturn([
            'attempts' => 5,
            'locked_until' => \time() + 300,
        ]);

        $cacheStub = $this->createStub(CacheItemPoolInterface::class);
        $cacheStub->method('getItem')->willReturn($itemStub);

        $service = new RateLimitingService($cacheStub);
        $result = $service->isBlocked('login', 'user@example.com');

        $this->assertTrue($result['blocked']);
        $this->assertArrayHasKey('minutes_remaining', $result);
    }

    public function testIsBlockedReturnsFalseWhenLockHasExpired(): void
    {
        $itemStub = $this->createStub(CacheItemInterface::class);
        $itemStub->method('isHit')->willReturn(true);
        $itemStub->method('get')->willReturn([
            'attempts' => 5,
            'locked_until' => \time() - 60,
        ]);

        $cacheStub = $this->createStub(CacheItemPoolInterface::class);
        $cacheStub->method('getItem')->willReturn($itemStub);

        $service = new RateLimitingService($cacheStub);
        $result = $service->isBlocked('login', 'user@example.com');

        $this->assertFalse($result['blocked']);
    }
}
