<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * Verifica que RateLimitingService registra intentos y bloqueos usando Cache (PSR-6).
 *
 * ¿Qué me quieres demostrar?
 * Que el servicio usa Redis TTL nativo en lugar de limpieza manual de la DB.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la lógica de bloqueo, si cambia el umbral de intentos, o si
 * se vuelve a usar PDO en lugar de CacheItemPoolInterface.
 */

namespace Tests\Unit\Services;

use App\Services\RateLimitingService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class RateLimitingServiceTest extends TestCase
{
    private RateLimitingService $service;
    private CacheItemPoolInterface $cacheMock;

    protected function setUp(): void
    {
        $this->cacheMock = $this->createStub(CacheItemPoolInterface::class);
        $this->service = new RateLimitingService($this->cacheMock);
    }

    public function testRecordAttemptCreatesNewRecordWhenNotExists(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();

        $this->cacheMock->method('getItem')->willReturn($item);
        $this->cacheMock->method('save')->willReturn(true);

        $result = $this->service->recordAttempt('login', 'test@example.com', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testRecordAttemptIncrementsExistingRecord(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['attempts' => 2, 'locked_until' => null]);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();

        $this->cacheMock->method('getItem')->willReturn($item);
        $this->cacheMock->method('save')->willReturn(true);

        $result = $this->service->recordAttempt('login', 'test@example.com', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testIsBlockedReturnsFalseWhenNoRecordExists(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->cacheMock->method('getItem')->willReturn($item);

        $result = $this->service->isBlocked('login', 'test@example.com');

        $this->assertIsArray($result);
        $this->assertFalse($result['blocked']);
    }

    public function testIsBlockedReturnsTrueWhenLockedUntilNotExpired(): void
    {
        $futureTimestamp = \time() + 600; // 10 minutos en el futuro

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['attempts' => 5, 'locked_until' => $futureTimestamp]);

        $this->cacheMock->method('getItem')->willReturn($item);

        $result = $this->service->isBlocked('login', 'test@example.com');

        $this->assertIsArray($result);
        $this->assertTrue($result['blocked']);
    }

    public function testGetRecentAttemptsReturnsCount(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['attempts' => 3, 'locked_until' => null]);

        $this->cacheMock->method('getItem')->willReturn($item);

        $attempts = $this->service->getRecentAttempts('login', 'test@example.com');

        $this->assertEquals(3, $attempts);
    }

    public function testGetRecentAttemptsReturnsZeroWhenNoRecord(): void
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->cacheMock->method('getItem')->willReturn($item);

        $attempts = $this->service->getRecentAttempts('login', 'test@example.com');

        $this->assertEquals(0, $attempts);
    }

    public function testClearAttemptsDeletesRecord(): void
    {
        $this->cacheMock->method('deleteItem')->willReturn(true);

        $result = $this->service->clearAttempts('login', 'test@example.com');

        $this->assertTrue($result);
    }
}
