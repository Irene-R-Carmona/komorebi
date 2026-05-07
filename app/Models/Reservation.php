<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Constantes del modelo Reservation
 *
 * Los datos se gestionan mediante ReservationRepository + ReservationDTO.
 */
final class Reservation
{
    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Estados posibles de una reserva */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_CANCELLED = 'cancelled';
    public const string STATUS_NO_SHOW = 'no_show';

    /** Estados que permiten cancelación */
    public const array CANCELLABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
    ];

    /** Estados "activos" (reserva vigente) */
    public const array ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_ACTIVE,
    ];

    /** Horas de antelación mínima para reservar */
    public const int MIN_ADVANCE_HOURS = 2;

    /** Días máximos de antelación para reservar */
    public const int MAX_ADVANCE_DAYS = 30;
}
