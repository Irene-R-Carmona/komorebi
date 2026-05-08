<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use App\Repositories\TrackerRepository;
use App\Services\Contracts\KitchenServiceInterface;
use App\Services\Contracts\ReceptionServiceInterface;
use App\Services\KitchenService;
use App\Services\ReceptionService;
use Override;

/**
 * Service Provider para el módulo Ops (recepción, cocina, turnos, asignaciones de supervisores).
 *
 * Solo se registra cuando FEATURE_OPS=1 (activado por defecto).
 */
final class OpsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(TrackerRepository::class, fn() => new TrackerRepository(
            Database::getConnection()
        ));
        Container::singleton(TrackerRepositoryInterface::class, fn() => Container::make(TrackerRepository::class));

        Container::singleton(KitchenService::class, fn() => new KitchenService(
            Container::make(ReservationItemRepositoryInterface::class)
        ));
        Container::singleton(KitchenServiceInterface::class, fn() => Container::make(KitchenService::class));

        Container::singleton(ReceptionService::class, fn() => new ReceptionService(
            Container::make(ReservationRepositoryInterface::class),
            Container::make(TrackerRepositoryInterface::class),
            Container::make(CafeRepositoryInterface::class)
        ));
        Container::singleton(ReceptionServiceInterface::class, fn() => Container::make(ReceptionService::class));
    }

    #[Override]
    public function boot(): void {}
}
