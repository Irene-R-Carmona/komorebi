<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo Animal — constantes de dominio.
 */
final class Animal
{
    public const string STATUS_ACTIVE  = 'active';
    public const string STATUS_RESTING = 'resting';
    public const string STATUS_SICK    = 'sick';
    public const string STATUS_RETIRED = 'retired';

    public const array VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RESTING,
        self::STATUS_SICK,
        self::STATUS_RETIRED,
    ];

    /** Estados de ánimo para logs */
    public const array VALID_MOODS = [
        'happy',
        'calm',
        'stressed',
        'aggressive',
        'tired',
    ];
}
