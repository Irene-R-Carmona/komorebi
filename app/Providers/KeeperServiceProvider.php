<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;

/**
 * Service Provider para el módulo Keeper (control de salud de animales).
 *
 * Solo se registra cuando FEATURE_KEEPER=1 (activado por defecto).
 */
final class KeeperServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Registrar servicios y repositorios del módulo Keeper aquí
    }

    #[\Override]
    public function boot(): void
    {
        // Arranque del módulo Keeper aquí
    }
}
