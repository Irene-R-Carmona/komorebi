<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Workers;

use App\Workers\NotificationWorker;
use PHPUnit\Framework\TestCase;

/**
 * Tests para NotificationWorker
 *
 * Valida la lógica del worker de notificaciones (sin ejecutar loop real).
 */
final class NotificationWorkerTest extends TestCase
{
    private NotificationWorker $worker;

    protected function setUp(): void
    {
        $this->worker = new NotificationWorker();
    }

    protected function tearDown(): void
    {
        unset($this->worker);
    }

    public function testWorkerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(NotificationWorker::class, $this->worker);
    }

    public function testWorkerHasRunMethod(): void
    {
        $this->assertTrue(\method_exists($this->worker, 'run'));
    }

    /**
     * Nota: El método run() es bloqueante y contiene un loop infinito,
     * por lo que no se puede testear directamente sin complicar la lógica.
     *
     * En un escenario real, se podría:
     * - Extraer la lógica de procesamiento a métodos privados/protected
     * - Inyectar dependencias (Queue) para mockear
     * - Usar integration tests con Redis real
     *
     * Este test es básico y valida que la clase existe y es instanciable.
     */
}
