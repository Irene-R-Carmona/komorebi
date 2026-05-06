<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralizes status → human label and CSS badge-class mappings.
 *
 * All methods accept an empty string and return a safe fallback rather
 * than throwing, so callers can safely pass `$row['status'] ?? ''`.
 */
final class StatusLabeling
{
    // -------------------------------------------------------------------------
    // Reservations
    // -------------------------------------------------------------------------

    public static function reservationLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'active' => 'Activa',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No Show',
            default => $status !== '' ? \ucfirst($status) : 'Desconocido',
        };
    }

    public static function reservationBadge(string $status): string
    {
        return match ($status) {
            'pending' => 'reservation-badge--pending',
            'confirmed' => 'reservation-badge--confirmed',
            'active' => 'reservation-badge--active',
            'completed' => 'reservation-badge--completed',
            'cancelled' => 'reservation-badge--cancelled',
            'no_show' => 'reservation-badge--no-show',
            default => 'reservation-badge--cancelled',
        };
    }

    // -------------------------------------------------------------------------
    // Waitlist
    // -------------------------------------------------------------------------

    public static function waitlistLabel(string $status): string
    {
        return match ($status) {
            'waiting' => 'Esperando',
            'notified' => 'Notificado',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'expired' => 'Expirado',
            default => $status !== '' ? \ucfirst($status) : 'Desconocido',
        };
    }

    public static function waitlistBadge(string $status): string
    {
        return match ($status) {
            'waiting' => 'status-badge--waiting',
            'notified' => 'status-badge--notified',
            'confirmed' => 'status-badge--confirmed',
            'cancelled' => 'status-badge--cancelled',
            'expired' => 'status-badge--expired',
            default => 'status-badge--expired',
        };
    }

    // -------------------------------------------------------------------------
    // Reviews
    // -------------------------------------------------------------------------

    public static function reviewLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            default => $status !== '' ? \ucfirst($status) : 'Desconocido',
        };
    }

    public static function reviewBadge(string $status): string
    {
        return match ($status) {
            'pending' => 'badge-status--pending',
            'approved' => 'badge-status--approved',
            'rejected' => 'badge-status--rejected',
            default => 'badge-status--pending',
        };
    }

    // -------------------------------------------------------------------------
    // Animals
    // -------------------------------------------------------------------------

    public static function animalLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activo',
            'resting' => 'Reposo',
            'sick' => 'Enfermo',
            'retired' => 'Retirado',
            'injured' => 'Lesionado',
            default => $status !== '' ? \ucfirst($status) : 'Desconocido',
        };
    }

    /**
     * Returns Bootstrap contextual color suffix (success, warning, danger, secondary)
     * for use with `bg-{badge}` Bootstrap classes.
     */
    public static function animalBadge(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'resting' => 'warning',
            'sick' => 'danger',
            'injured' => 'danger',
            'retired' => 'secondary',
            default => 'secondary',
        };
    }
}
