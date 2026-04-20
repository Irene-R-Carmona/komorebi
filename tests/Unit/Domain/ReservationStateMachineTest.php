<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La máquina de estados de reservas: isValidTransition y assertCanTransition.
 *
 * ¿Qué me quieres demostrar?
 * Que el mapa de transiciones es correcto (tabla de verdad completa),
 * que los estados terminales no permiten salidas y que assertCanTransition
 * lanza BusinessRuleException exactamente cuando la transición no es válida.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier modificación al mapa TRANSITIONS (añadir, eliminar o mover una
 * transición) romperá uno o más tests de esta suite.
 */

namespace Tests\Unit\Domain;

use App\Domain\Reservation\ReservationStateMachine;
use App\Exceptions\BusinessRuleException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationStateMachine::class)]
final class ReservationStateMachineTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────
    // isValidTransition — transiciones válidas
    // ──────────────────────────────────────────────────────────────

    public function testPendingCanTransitionToConfirmed(): void
    {
        self::assertTrue(ReservationStateMachine::isValidTransition('pending', 'confirmed'));
    }

    public function testPendingCanTransitionToCancelled(): void
    {
        self::assertTrue(ReservationStateMachine::isValidTransition('pending', 'cancelled'));
    }

    public function testConfirmedCanTransitionToActive(): void
    {
        self::assertTrue(ReservationStateMachine::isValidTransition('confirmed', 'active'));
    }

    public function testConfirmedCanTransitionToCancelled(): void
    {
        self::assertTrue(ReservationStateMachine::isValidTransition('confirmed', 'cancelled'));
    }

    public function testConfirmedCanTransitionToNoShow(): void
    {
        self::assertTrue(ReservationStateMachine::isValidTransition('confirmed', 'no_show'));
    }

    public function testActiveCanTransitionToCompleted(): void
    {
        self::assertTrue(ReservationStateMachine::isValidTransition('active', 'completed'));
    }

    // ──────────────────────────────────────────────────────────────
    // isValidTransition — transiciones inválidas desde estados no terminales
    // ──────────────────────────────────────────────────────────────

    public function testPendingCannotTransitionToActive(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('pending', 'active'));
    }

    public function testPendingCannotTransitionToCompleted(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('pending', 'completed'));
    }

    public function testPendingCannotTransitionToNoShow(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('pending', 'no_show'));
    }

    public function testConfirmedCannotTransitionToCompleted(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('confirmed', 'completed'));
    }

    public function testConfirmedCannotTransitionToPending(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('confirmed', 'pending'));
    }

    public function testActiveCannotTransitionToCancelled(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('active', 'cancelled'));
    }

    public function testActiveCannotTransitionToNoShow(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('active', 'no_show'));
    }

    public function testActiveCannotTransitionToPending(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('active', 'pending'));
    }

    // ──────────────────────────────────────────────────────────────
    // isValidTransition — estados terminales no permiten ninguna transición
    // ──────────────────────────────────────────────────────────────

    public function testCompletedIsTerminalState(): void
    {
        foreach (['pending', 'confirmed', 'active', 'cancelled', 'no_show', 'completed'] as $to) {
            self::assertFalse(
                ReservationStateMachine::isValidTransition('completed', $to),
                "Estado 'completed' no debe permitir transición a '{$to}'"
            );
        }
    }

    public function testCancelledIsTerminalState(): void
    {
        foreach (['pending', 'confirmed', 'active', 'completed', 'no_show', 'cancelled'] as $to) {
            self::assertFalse(
                ReservationStateMachine::isValidTransition('cancelled', $to),
                "Estado 'cancelled' no debe permitir transición a '{$to}'"
            );
        }
    }

    public function testNoShowIsTerminalState(): void
    {
        foreach (['pending', 'confirmed', 'active', 'completed', 'cancelled', 'no_show'] as $to) {
            self::assertFalse(
                ReservationStateMachine::isValidTransition('no_show', $to),
                "Estado 'no_show' no debe permitir transición a '{$to}'"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // isValidTransition — estado de origen desconocido
    // ──────────────────────────────────────────────────────────────

    public function testUnknownFromStateReturnsFalse(): void
    {
        self::assertFalse(ReservationStateMachine::isValidTransition('unknown', 'confirmed'));
    }

    // ──────────────────────────────────────────────────────────────
    // assertCanTransition — lanza excepción en transiciones inválidas
    // ──────────────────────────────────────────────────────────────

    public function testAssertCanTransitionDoesNotThrowForValidTransition(): void
    {
        $this->expectNotToPerformAssertions();
        ReservationStateMachine::assertCanTransition('pending', 'confirmed');
    }

    public function testAssertCanTransitionThrowsBusinessRuleExceptionForInvalidTransition(): void
    {
        $this->expectException(BusinessRuleException::class);
        ReservationStateMachine::assertCanTransition('pending', 'active');
    }

    public function testAssertCanTransitionExceptionContainsTransitionDetails(): void
    {
        try {
            ReservationStateMachine::assertCanTransition('active', 'cancelled');
            self::fail('Debería haber lanzado BusinessRuleException');
        } catch (BusinessRuleException $e) {
            self::assertStringContainsString('active', $e->getMessage());
            self::assertStringContainsString('cancelled', $e->getMessage());
            self::assertSame('invalid_transition', $e->getRuleCode());
        }
    }

    public function testAssertCanTransitionThrowsFromTerminalState(): void
    {
        $this->expectException(BusinessRuleException::class);
        ReservationStateMachine::assertCanTransition('completed', 'pending');
    }
}
