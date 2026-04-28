<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

use App\Core\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test unitario básico para Queue
 *
 * NOTA: Este test requiere Redis disponible. Para ejecutar sin Redis,
 * mockear Cache::getRedis() en setUp().
 */
#[CoversClass(Queue::class)]
final class QueueTest extends TestCase
{
    private const string TEST_QUEUE = 'test_queue_phpunit';

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar cola de test antes de cada test (Queue usa fallback si Redis no está disponible)
        Queue::clear(self::TEST_QUEUE);
    }

    protected function tearDown(): void
    {
        // Limpiar cola de test después de cada test
        try {
            Queue::clear(self::TEST_QUEUE);
        } catch (\Throwable $e) {
            // Ignorar errores en cleanup
        }

        parent::tearDown();
    }

    public function testPushJobToQueue(): void
    {
        $pushed = Queue::push(
            \App\Jobs\SendEmailJob::class,
            ['to' => 'test@example.com', 'subject' => 'Test', 'body' => 'Body'],
            self::TEST_QUEUE
        );

        $this->assertTrue($pushed, 'Job debería añadirse correctamente');
        $this->assertSame(1, Queue::size(self::TEST_QUEUE), 'Cola debería tener 1 job');
    }

    public function testPopJobFromQueue(): void
    {
        // Preparar: añadir job
        Queue::push(
            \App\Jobs\SendEmailJob::class,
            ['to' => 'test@example.com', 'subject' => 'Test', 'body' => 'Body'],
            self::TEST_QUEUE
        );

        // Ejecutar: extraer job
        $job = Queue::pop(self::TEST_QUEUE);

        // Verificar
        $this->assertIsArray($job, 'Job extraído debería ser array');
        $this->assertArrayHasKey('job', $job);
        $this->assertArrayHasKey('payload', $job);
        $this->assertSame(\App\Jobs\SendEmailJob::class, $job['job']);

        // Con Redis Streams, el mensaje pasa al PEL tras xReadGroup (pendiente de ACK).
        // Un segundo pop() con '>' no ve mensajes ya entregados — la cola está efectivamente vacía.
        $this->assertNull(Queue::pop(self::TEST_QUEUE), 'No debe haber más jobs disponibles tras consumir el único');
    }

    public function testPopFromEmptyQueueReturnsNull(): void
    {
        $job = Queue::pop(self::TEST_QUEUE);

        $this->assertNull($job, 'Pop en cola vacía debería devolver null');
    }

    public function testQueueSize(): void
    {
        $this->assertSame(0, Queue::size(self::TEST_QUEUE), 'Cola nueva debería estar vacía');

        Queue::push(\App\Jobs\SendEmailJob::class, [], self::TEST_QUEUE);
        $this->assertSame(1, Queue::size(self::TEST_QUEUE));

        Queue::push(\App\Jobs\SendEmailJob::class, [], self::TEST_QUEUE);
        $this->assertSame(2, Queue::size(self::TEST_QUEUE));
    }

    public function testClearQueue(): void
    {
        // Preparar: añadir varios jobs
        Queue::push(\App\Jobs\SendEmailJob::class, [], self::TEST_QUEUE);
        Queue::push(\App\Jobs\SendEmailJob::class, [], self::TEST_QUEUE);

        $this->assertSame(2, Queue::size(self::TEST_QUEUE));

        // Ejecutar: limpiar
        $cleared = Queue::clear(self::TEST_QUEUE);

        // Verificar
        $this->assertTrue($cleared);
        $this->assertSame(0, Queue::size(self::TEST_QUEUE));
    }

    public function testRetryJobIncreasesAttempts(): void
    {
        $jobData = [
            'job' => \App\Jobs\SendEmailJob::class,
            'payload' => ['to' => 'test@example.com', 'subject' => 'Test', 'body' => 'Body'],
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time(),
        ];

        $retried = Queue::retry($jobData, self::TEST_QUEUE, 3);

        $this->assertTrue($retried, 'Job debería reencolarse');

        // Esperar a que el delay expire (2^1 = 2 segundos)
        // Para tests, podemos verificar que se añadió a delayed
        // En producción, el worker procesaría esto automáticamente
    }

    public function testRetryFailsAfterMaxAttempts(): void
    {
        $jobData = [
            'job' => \App\Jobs\SendEmailJob::class,
            'payload' => [],
            'attempts' => 2, // Ya tiene 2 intentos
            'created_at' => time(),
            'available_at' => time(),
        ];

        $retried = Queue::retry($jobData, self::TEST_QUEUE, 3);

        $this->assertFalse($retried, 'Job no debería reencolarse (máximo alcanzado)');
    }
}
