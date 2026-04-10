<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;

/**
 * Service Provider para el módulo Ops (turnos, asignaciones de supervisores).
 *
 * Solo se registra cuando FEATURE_OPS=1 (activado por defecto).
 */
final class OpsServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Registrar servicios y repositorios del módulo Ops aquí
    }

    #[\Override]
    public function boot(): void
    {
        // Arranque del módulo Ops aquí
    }
}
