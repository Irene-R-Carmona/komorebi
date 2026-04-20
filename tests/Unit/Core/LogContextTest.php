<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\LogContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * Comportamiento del registry estático LogContext: set, get, all, reset.
 *
 * ¿Qué me quieres demostrar?
 * Que LogContext almacena pares clave-valor en memoria y reset() los borra.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio a la mutabilidad del estado o nombres de métodos.
 */
#[CoversClass(LogContext::class)]
final class LogContextTest extends TestCase
{
    protected function setUp(): void
    {
        LogContext::reset();
    }

    protected function tearDown(): void
    {
        LogContext::reset();
    }

    public function testSetAndGetValue(): void
    {
        LogContext::set('request_id', 'abc123');

        $this->assertSame('abc123', LogContext::get('request_id'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertNull(LogContext::get('missing_key'));
        $this->assertSame('default', LogContext::get('missing_key', 'default'));
    }

    public function testAllReturnsAllEntries(): void
    {
        LogContext::set('request_id', 'abc123');
        LogContext::set('method', 'GET');

        $this->assertSame(['request_id' => 'abc123', 'method' => 'GET'], LogContext::all());
    }

    public function testResetClearsAllEntries(): void
    {
        LogContext::set('request_id', 'abc123');
        LogContext::reset();

        $this->assertSame([], LogContext::all());
    }

    public function testSetOverwritesExistingKey(): void
    {
        LogContext::set('request_id', 'first');
        LogContext::set('request_id', 'second');

        $this->assertSame('second', LogContext::get('request_id'));
    }
}
