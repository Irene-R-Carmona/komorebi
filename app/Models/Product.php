<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo Product — constantes de dominio.
 */
final class Product
{
    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Tipos de producto */
    public const string TYPE_ITEM = 'item';
    public const string TYPE_PASS = 'pass';

    /** Estaciones de preparación */
    public const string STATION_BAR = 'bar';
    public const string STATION_KITCHEN_HOT = 'kitchen_hot';
    public const string STATION_KITCHEN_COLD = 'kitchen_cold';
    public const string STATION_BAKERY = 'bakery';
    public const string STATION_ASSEMBLY = 'assembly';

    public const array VALID_STATIONS = [
        self::STATION_BAR,
        self::STATION_KITCHEN_HOT,
        self::STATION_KITCHEN_COLD,
        self::STATION_BAKERY,
        self::STATION_ASSEMBLY,
    ];
}
