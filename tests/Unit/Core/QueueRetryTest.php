<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Queue;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * Comportamiento de Queue::retry(): jitter backoff, cap de 300 s y
 * movimiento a cola de fallos tras agotar los reintentos máximos.
 *
 * ¿Qué me quieres demostrar?
 * Que el delay de reintento siempre cae dentro del rango [0, min(300, 2^attempts)],
 * que el cap de 300 s se respeta aunque 2^attempts lo supere, y que cuando
 * se alcanza maxAttempts el job va a la cola failed y el método devuelve false.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se elimina el jitter → testRetryDelayIsWithinJitterBounds puede seguir verde,
 *   pero la distribución ya no será uniforme.
 * - Si se cambia random_int(0, min(300, ...)) por otro cap → testRetryDelayNeverExceedsCap falla.
 * - Si se cambia maxAttempts default de 10 → testJobSentToFailedAfterMaxAttempts puede fallar.
 * - Si pushToFailedQueue deja de usar la key 'queue:failed' → la assertion de lPush falla.
 */
final class QueueRetryTest extends TestCase
{
    /** @var array<int, array{key: string, score: float, value: string}> */
    private array $zaddCalls = [];

    /** @var array<int, array{key: string, value: string}> */
    private array $lpushCalls = [];

    protected function setUp(): void
    {
        $this->zaddCalls = [];
        $this->lpushCalls = [];

        $zaddRef = &$this->zaddCalls;
        $lpushRef = &$this->lpushCalls;

        $fakeRedis = new class ($zaddRef, $lpushRef) {
            public function __construct(
                /** @phpstan-ignore property.onlyWritten */
                private array &$zaddCalls,
                /** @phpstan-ignore property.onlyWritten */
                private array &$lpushCalls,
            ) {
            }

            /** @param mixed ...$args */
            public function zAdd(string $key, array $options, float $score, mixed ...$args): mixed
            {
                foreach ($args as $value) {
                    $this->zaddCalls[] = ['key' => $key, 'score' => $score, 'value' => (string) $value];
                }

                return 1;
            }

            /** @param mixed ...$values */
            public function lPush(string $key, mixed ...$values): mixed
            {
                foreach ($values as $value) {
                    $this->lpushCalls[] = ['key' => $key, 'value' => (string) $value];
                }

                return 1;
            }
        };

        $prop = new \ReflectionClass(Queue::class)->getProperty('redis');
        $prop->setValue(null, $fakeRedis);
    }

    protected function tearDown(): void
    {
        new \ReflectionClass(Queue::class)->getProperty('redis')->setValue(null, null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRetryDelayIsWithinJitterBounds(): void
    {
        // attempts=2 → internal $attempts=3 → max = min(300, 2^3) = 8
        $jobData = ['job' => 'App\Jobs\SendEmailJob', 'payload' => [], 'attempts' => 2];
        $maxDelay = \min(300, 2 ** 3);  // 8

        $before = \time();

        for ($i = 0; $i < 50; $i++) {
            Queue::retry($jobData, 'default', 10);
        }

        $after = \time();  // captures worst-case elapsed time for the loop

        $this->assertCount(50, $this->zaddCalls, 'Se esperan 50 llamadas a zAdd');

        foreach ($this->zaddCalls as $call) {
            $delay = $call['score'] - $before;
            $this->assertGreaterThanOrEqual(
                0.0,
                $delay,
                'El delay no puede ser negativo'
            );
            $this->assertLessThanOrEqual(
                (float) ($maxDelay + ($after - $before) + 1),
                $delay,
                "El delay debe ser ≤ {$maxDelay} + tiempo transcurrido"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testRetryDelayNeverExceedsCap(): void
    {
        // attempts=9 → internal $attempts=10 → 2^10=1024 → cap=300
        $jobData = ['job' => 'App\Jobs\SendEmailJob', 'payload' => [], 'attempts' => 9];
        $cap = 300;

        $before = \time();

        for ($i = 0; $i < 50; $i++) {
            Queue::retry($jobData, 'default', 20);  // maxAttempts=20 para no llegar al límite
        }

        $this->assertCount(50, $this->zaddCalls);

        foreach ($this->zaddCalls as $call) {
            $delay = $call['score'] - $before;
            $this->assertLessThanOrEqual(
                (float) ($cap + 1),  // +1 por posible tick de reloj entre time() del método y del test
                $delay,
                'El delay nunca debe superar el cap de 300 s'
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testJobSentToFailedAfterMaxAttempts(): void
    {
        // attempts=9, maxAttempts=10 → $attempts=10, 10 >= 10 → va a failed
        $jobData = ['job' => 'App\Jobs\SendEmailJob', 'payload' => [], 'attempts' => 9];

        $result = Queue::retry($jobData, 'default', 10);

        $this->assertFalse($result, 'retry() debe devolver false al agotar los intentos');
        $this->assertEmpty($this->zaddCalls, 'No debe encolarse en delayed cuando alcanza el máximo');
        $this->assertNotEmpty($this->lpushCalls, 'Debe llamar a lPush para la cola de fallos');
        $this->assertStringContainsString(
            'failed',
            $this->lpushCalls[0]['key'],
            "La key de la cola de fallos debe contener 'failed'"
        );
    }
}
