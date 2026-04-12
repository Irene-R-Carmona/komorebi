<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\AnimalRepository;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Repositories\HealthCheckRepository;
use App\Services\AnimalCareService;
use App\Services\HealthCheckService;
use App\Services\Contracts\AnimalCareServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;

/**
 * Service Provider para el módulo Keeper (control de salud de animales).
 *
 * Solo se registra cuando FEATURE_KEEPER=1 (activado por defecto).
 * Registra: AnimalCareService, HealthCheckService.
 * Nota: AnimalRepositoryInterface también está en ReservationServiceProvider.
 */
final class KeeperServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Container::singleton(HealthCheckRepository::class, fn() => new HealthCheckRepository(
            Database::getConnection()
        ));

        Container::singleton(HealthCheckRepositoryInterface::class, fn() => Container::make(
            HealthCheckRepository::class
        ));

        Container::singleton(HealthCheckService::class, fn() => new HealthCheckService(
            Container::make(HealthCheckRepositoryInterface::class)
        ));

        Container::singleton(HealthCheckServiceInterface::class, fn() => Container::make(HealthCheckService::class));

        Container::singleton(AnimalCareService::class, fn() => new AnimalCareService(
            Database::getConnection(),
            Container::make(AnimalRepositoryInterface::class)
        ));

        Container::singleton(AnimalCareServiceInterface::class, fn() => Container::make(AnimalCareService::class));
    }

    #[\Override]
    public function boot(): void
    {
        // Sin bootstrap específico
    }
}
