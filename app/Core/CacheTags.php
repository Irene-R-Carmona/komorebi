<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Constantes de tags para invalidación de cache por grupo (TagAwareAdapter).
 *
 * Uso:
 *   Cache::setWithTags($key, $value, [CacheTags::MENU], 3600);
 *   Cache::invalidateTags([CacheTags::MENU]);
 *   Cache::computeIfAbsent($key, $fn, 3600, [CacheTags::LOYALTY]);
 */
final class CacheTags
{
    public const string MENU          = 'menu';
    public const string CAFE          = 'cafe';
    public const string LOYALTY       = 'loyalty';
    public const string USERS         = 'users';
    public const string RESERVATIONS  = 'reservations';
    public const string ANIMALS       = 'animals';
    public const string PRODUCTS      = 'products';
    public const string SETTINGS      = 'settings';

    /** @codeCoverageIgnore */
    private function __construct() {}
}
