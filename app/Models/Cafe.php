<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Domain object para Cafe.
 *
 * Solo constantes de dominio — sin PDO, sin queries.
 * Todo el acceso a datos vive en App\Repositories\CafeRepository.
 */
final class Cafe
{
    public const string CATEGORY_LOUNGE   = 'lounge';
    public const string CATEGORY_PLAYROOM = 'playroom';
    public const string CATEGORY_FARM     = 'farm';
    public const string CATEGORY_ZEN      = 'zen';

    public const array VALID_CATEGORIES = [
        self::CATEGORY_LOUNGE,
        self::CATEGORY_PLAYROOM,
        self::CATEGORY_FARM,
        self::CATEGORY_ZEN,
    ];
}
