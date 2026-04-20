<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Domain object para Allergen.
 *
 * Solo constantes de dominio — sin PDO, sin queries, sin AbstractModel.
 * Todo el acceso a datos vive en App\Repositories\AllergenRepository.
 */
final class Allergen
{
    public const string SEVERITY_LOW = 'low';
    public const string SEVERITY_MEDIUM = 'medium';
    public const string SEVERITY_HIGH = 'high';

    public const array VALID_SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
    ];
}
