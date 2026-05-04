<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Vocabulario controlado para el dominio animal.
 *
 * Centraliza todos los valores válidos para ENUMs relacionados con animales,
 * incidentes y su estado. Úsese para validación en servicios y para poblar
 * selectores en vistas.
 */
final class AnimalVocabulary
{
    /** @var list<string> */
    public const array SPECIES = [
        'cat',
        'dog',
        'rabbit',
        'bird',
        'hedgehog',
        'capybara',
        'hamster',
        'other',
    ];

    /** @var list<string> */
    public const array INCIDENT_TYPES = [
        'bite',
        'injury',
        'escape',
        'illness',
        'behavior',
        'other',
    ];

    /** @var list<string> */
    public const array INCIDENT_STATUSES = [
        'open',
        'monitoring',
        'resolved',
        'archived',
    ];

    public static function isValidSpecies(string $value): bool
    {
        return \in_array($value, self::SPECIES, true);
    }

    public static function isValidIncidentType(string $value): bool
    {
        return \in_array($value, self::INCIDENT_TYPES, true);
    }

    public static function isValidIncidentStatus(string $value): bool
    {
        return \in_array($value, self::INCIDENT_STATUSES, true);
    }
}
