<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Constantes del modelo Waitlist
 *
 * Los datos se gestionan mediante WaitlistRepository + WaitlistEntryDTO.
 */
final class Waitlist
{
    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Estados posibles */
    public const string STATUS_WAITING = 'waiting';
    public const string STATUS_NOTIFIED = 'notified';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_EXPIRED = 'expired';
    public const string STATUS_CANCELLED = 'cancelled';

    /** Tiempo por defecto para responder notificación (minutos) */
    public const int DEFAULT_RESPONSE_TIMEOUT = 15;
}
