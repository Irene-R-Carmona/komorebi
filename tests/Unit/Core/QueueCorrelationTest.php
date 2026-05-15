<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de trazabilidad: buildJobPayload() incluye correlation_id tomado de WideEvent.
 *
 * ¿Qué me quieres demostrar?
 * Que todo job encolado lleva un campo top-level `correlation_id` con el request_id activo.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si buildJobPayload() deja de leer WideEvent::get('request_id'), o si el campo desaparece
 * o se mueve al interior del payload, estos tests fallarán.
 */

namespace Tests\Unit\Core;

use App\Core\Queue;
use App\Core\WideEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Queue::class)]
final class QueueCorrelationTest extends TestCase
{
    protected function tearDown(): void
    {
        WideEvent::reset();
        parent::tearDown();
    }

    public function testBuildJobPayloadIncludesCorrelationIdFromWideEvent(): void
    {
        WideEvent::set('request_id', 'test-correlation-abc123');

        $result = Queue::buildJobPayload('App\Jobs\SomeJob', ['key' => 'value']);

        self::assertArrayHasKey('correlation_id', $result);
        self::assertSame('test-correlation-abc123', $result['correlation_id']);
    }

    public function testBuildJobPayloadHasEmptyCorrelationIdWhenWideEventIsEmpty(): void
    {
        WideEvent::reset();

        $result = Queue::buildJobPayload('App\Jobs\SomeJob', ['key' => 'value']);

        self::assertArrayHasKey('correlation_id', $result);
        self::assertSame('', $result['correlation_id']);
    }

    public function testBuildJobPayloadPreservesJobClassAndPayload(): void
    {
        $payload = ['to' => 'test@example.com', 'subject' => 'Hello'];

        $result = Queue::buildJobPayload('App\Jobs\SendEmailJob', $payload);

        self::assertSame('App\Jobs\SendEmailJob', $result['job']);
        self::assertSame($payload, $result['payload']);
        self::assertSame(0, $result['attempts']);
        self::assertArrayHasKey('created_at', $result);
        self::assertArrayHasKey('available_at', $result);
    }

    public function testBuildJobPayloadWithDelayShiftsAvailableAt(): void
    {
        $before = \time();
        $result = Queue::buildJobPayload('App\Jobs\SomeJob', [], 60);
        $after  = \time();

        self::assertGreaterThanOrEqual($before + 60, $result['available_at']);
        self::assertLessThanOrEqual($after + 60, $result['available_at']);
    }

    public function testBuildJobPayloadWithoutDelayHasAvailableAtNow(): void
    {
        $before = \time();
        $result = Queue::buildJobPayload('App\Jobs\SomeJob', []);
        $after  = \time();

        self::assertGreaterThanOrEqual($before, $result['available_at']);
        self::assertLessThanOrEqual($after, $result['available_at']);
    }
}
