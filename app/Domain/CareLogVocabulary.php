<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Vocabulario controlado para registros de cuidado animal.
 *
 * Centraliza todos los valores válidos para tipos de actividad y estados
 * de ánimo en los care logs. Úsese para validación en servicios y para
 * poblar selectores en vistas.
 */
final class CareLogVocabulary
{
    /** @var list<string> */
    public const array ACTIVITY_TYPES = [
        'feeding',
        'grooming',
        'exercise',
        'vet_visit',
        'enrichment',
        'observation',
    ];

    /** @var list<string> */
    public const array MOOD_VALUES = [
        'calm',
        'happy',
        'playful',
        'anxious',
        'lethargic',
        'aggressive',
    ];

    public static function isValidActivityType(string $value): bool
    {
        return \in_array($value, self::ACTIVITY_TYPES, true);
    }

    public static function isValidMood(string $value): bool
    {
        return \in_array($value, self::MOOD_VALUES, true);
    }
}
