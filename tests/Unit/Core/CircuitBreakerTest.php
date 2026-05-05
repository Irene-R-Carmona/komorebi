<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\CircuitBreaker;
use App\Exceptions\CircuitOpenException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * ¿Qué pruebas aquí?
 * Transiciones de estado del Circuit Breaker: CLOSED → OPEN → HALF_OPEN → CLOSED.
 * Acumulación de fallos hasta el umbral, rechazo de llamadas en OPEN,
 * y sondas de recuperación en HALF_OPEN.
 *
 * ¿Qué me quieres demostrar?
 * Que CircuitBreaker protege contra servicios externos fallidos sin Redis (fallback
 * en memoria): abre tras FAILURE_THRESHOLD fallos, rechaza llamadas mientras está OPEN,
 * y se cierra cuando la sonda en HALF_OPEN tiene éxito.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si FAILURE_THRESHOLD cambia → testReachingThresholdOpensCircuit fallará.
 * - Si TIMEOUT_SECONDS cambia → testOpenCircuitAllowsProbeAfterTimeout puede fallar.
 * - Si se elimina la transición OPEN→HALF_OPEN → tests de sonda fallarán.
 * - Si el fallo en HALF_OPEN no reabre el circuito → testFailingProbeReopensCircuit falla.
 * - Si resetFailures() se elimina de on-success → testCircuitRemainsClosedBelowThreshold falla.
 */
#[CoversClass(CircuitBreaker::class)]
#[RunTestsInSeparateProcesses]
final class CircuitBreakerTest extends TestCase
{
    private string $circuit;

    protected function setUp(): void
    {
        $this->circuit = 'test-service-' . \getmypid() . '-' . \uniqid('', true);
        CircuitBreaker::reset($this->circuit);
    }

    protected function tearDown(): void
    {
        CircuitBreaker::reset($this->circuit);
    }

    public function testClosedCircuitAllowsOperationAndReturnsValue(): void
    {
        $result = CircuitBreaker::call($this->circuit, fn () => 42);

        $this->assertSame(42, $result);
    }

    public function testCircuitRemainsClosedBelowThreshold(): void
    {
        // (FAILURE_THRESHOLD - 1) fallos consecutivos: circuito sigue CLOSED
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD - 1; $i++) {
            try {
                CircuitBreaker::call($this->circuit, fn () => throw new RuntimeException('fallo'));
            } catch (RuntimeException) {
                // registrado por el CB, continuamos
            }
        }

        $result = CircuitBreaker::call($this->circuit, fn () => 'ok');

        $this->assertSame('ok', $result);
    }

    public function testReachingThresholdOpensCircuit(): void
    {
        // Exactamente FAILURE_THRESHOLD fallos consecutivos → circuito se abre
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            try {
                CircuitBreaker::call($this->circuit, fn () => throw new RuntimeException('servicio caído'));
            } catch (RuntimeException) {
                // fallo esperado
            }
        }

        $this->expectException(CircuitOpenException::class);
        CircuitBreaker::call($this->circuit, fn () => 'rechazada');
    }

    public function testOpenCircuitRejectsCallWithoutExecutingOperation(): void
    {
        CircuitBreaker::forceOpenAt($this->circuit, \time());

        $operationExecuted = false;

        try {
            CircuitBreaker::call($this->circuit, function () use (&$operationExecuted) {
                $operationExecuted = true;

                return 'valor';
            });
            $this->fail('Debería haber lanzado CircuitOpenException');
        } catch (CircuitOpenException) {
            $this->assertFalse($operationExecuted, 'La operación no debe ejecutarse con el circuito OPEN');
        }
    }

    public function testOpenCircuitAllowsProbeAfterTimeout(): void
    {
        $pastTimestamp = \time() - CircuitBreaker::TIMEOUT_SECONDS - 10;
        CircuitBreaker::forceOpenAt($this->circuit, $pastTimestamp);

        // La sonda en HALF_OPEN debe ejecutarse y retornar el valor
        $result = CircuitBreaker::call($this->circuit, fn () => 'sonda exitosa');

        $this->assertSame('sonda exitosa', $result);
    }

    public function testSuccessfulProbeInHalfOpenClosesCircuit(): void
    {
        $pastTimestamp = \time() - CircuitBreaker::TIMEOUT_SECONDS - 10;
        CircuitBreaker::forceOpenAt($this->circuit, $pastTimestamp);

        // Sonda exitosa → circuito se cierra
        CircuitBreaker::call($this->circuit, fn () => 'sonda ok');

        // Circuito CLOSED: llamadas normales funcionan sin lanzar CircuitOpenException
        $result = CircuitBreaker::call($this->circuit, fn () => 'llamada normal');
        $this->assertSame('llamada normal', $result);
    }

    public function testFailingProbeInHalfOpenReopensCircuit(): void
    {
        $pastTimestamp = \time() - CircuitBreaker::TIMEOUT_SECONDS - 10;
        CircuitBreaker::forceOpenAt($this->circuit, $pastTimestamp);

        // Sonda fallida → circuito reabierto
        try {
            CircuitBreaker::call($this->circuit, fn () => throw new RuntimeException('servicio aún caído'));
        } catch (RuntimeException) {
            // fallo esperado
        }

        // Circuito OPEN de nuevo → siguiente llamada rechazada
        $this->expectException(CircuitOpenException::class);
        CircuitBreaker::call($this->circuit, fn () => 'rechazada');
    }

    public function testResetClearsAllState(): void
    {
        CircuitBreaker::forceOpenAt($this->circuit, \time());
        CircuitBreaker::reset($this->circuit);

        // Después del reset, el circuito vuelve a CLOSED
        $result = CircuitBreaker::call($this->circuit, fn () => 'post-reset');
        $this->assertSame('post-reset', $result);
    }
}
