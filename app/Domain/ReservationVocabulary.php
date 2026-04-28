<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Vocabulario controlado para el dominio de reservas.
 *
 * Centraliza todos los valores válidos para métodos de pago.
 * Úsese para validación en servicios y para poblar selectores en vistas.
 */
final class ReservationVocabulary
{
    /** @var list<string> */
    public const array PAYMENT_METHODS = [
        'cash',
        'card',
        'transfer',
    ];

    public static function isValidPaymentMethod(string $value): bool
    {
        return \in_array($value, self::PAYMENT_METHODS, true);
    }
}
