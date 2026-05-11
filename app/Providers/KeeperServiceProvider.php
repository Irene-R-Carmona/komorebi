<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\AdoptionRepository;
use App\Repositories\AnimalIncidentRepository;
use App\Repositories\Contracts\AdoptionRepositoryInterface;
use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Repositories\HealthCheckRepository;
use App\Services\AdoptionService;
use App\Services\AnimalCareService;
use App\Services\Contracts\AdoptionServiceInterface;
use App\Services\Contracts\AnimalCareServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use App\Services\HealthCheckService;
use Override;

/**
 * Service Provider para el módulo Keeper (control de salud de animales).
 *
 * Solo se registra cuando FEATURE_KEEPER=1 (activado por defecto).
 * Registra: AnimalCareService, HealthCheckService.
 * Nota: AnimalRepositoryInterface también está en ReservationServiceProvider.
 */
final class KeeperServiceProvider extends ServiceProvider
{
    #[Override]
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

        Container::singleton(AnimalIncidentRepository::class, fn() => new AnimalIncidentRepository());
        Container::singleton(AnimalIncidentRepositoryInterface::class, fn() => Container::make(AnimalIncidentRepository::class));

        Container::singleton(AnimalCareService::class, fn() => new AnimalCareService(
            Container::make(AnimalRepositoryInterface::class),
            Container::make(AnimalIncidentRepositoryInterface::class),
            Container::make(HealthCheckRepositoryInterface::class),
        ));

        Container::singleton(AnimalCareServiceInterface::class, fn() => Container::make(AnimalCareService::class));

        Container::singleton(AdoptionRepository::class, fn() => new AdoptionRepository(
            Database::getConnection()
        ));

        Container::singleton(AdoptionRepositoryInterface::class, fn() => Container::make(AdoptionRepository::class));

        Container::singleton(AdoptionService::class, fn() => new AdoptionService(
            Container::make(AdoptionRepositoryInterface::class),
            Container::make(AnimalRepositoryInterface::class),
        ));

        Container::singleton(AdoptionServiceInterface::class, fn() => Container::make(AdoptionService::class));
    }

    #[Override]
    public function boot(): void {}
}
