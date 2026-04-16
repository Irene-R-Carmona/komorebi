<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use App\Exceptions\BusinessRuleException;

/**
 * Máquina de estados para reservas.
 *
 * Centraliza todas las transiciones de estado válidas y proporciona
 * guards declarativos para los modelos y repositorios.
 *
 * Estados del ciclo de vida:
 *   pending → confirmed | cancelled
 *   confirmed → active | cancelled | no_show
 *   active → completed
 *   completed, cancelled, no_show → (terminales, sin transiciones)
 */
final class ReservationStateMachine
{
    /** Mapa declarativo de transiciones válidas: estado_origen → [estados_destino] */
    private const array TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['active', 'cancelled', 'no_show'],
        'active' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    /**
     * Comprueba si una transición entre dos estados es válida.
     */
    public static function isValidTransition(string $from, string $to): bool
    {
        return \in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Afirma que una transición es válida; lanza BusinessRuleException si no lo es.
     *
     * @throws BusinessRuleException
     */
    public static function assertCanTransition(string $from, string $to): void
    {
        if (!self::isValidTransition($from, $to)) {
            throw BusinessRuleException::withMessage(
                "Transición de estado inválida: '{$from}' → '{$to}'",
                'invalid_transition',
                ['from' => $from, 'to' => $to]
            );
        }
    }
}
