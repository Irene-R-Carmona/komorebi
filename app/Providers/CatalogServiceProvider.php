<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\AllergenRepository;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\MenuRepository;
use App\Services\AllergenService;
use App\Services\Contracts\AllergenServiceInterface;
use App\Services\Contracts\MenuServiceInterface;
use App\Services\Contracts\ProductServiceInterface;
use App\Services\MenuService;
use App\Services\ProductService;
use Override;

/**
 * Service Provider para el catálogo: menú y productos.
 *
 * Registra: MenuRepositoryInterface, ProductService, MenuService.
 * Nota: ProductRepositoryInterface ya está en ReservationServiceProvider
 * (se usa también para reservas); aquí lo registramos como alias.
 */
final class CatalogServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        // ── Repositorios ────────────────────────────────────────────

        Container::singleton(MenuRepositoryInterface::class, fn () => new MenuRepository(
            Database::getConnection()
        ));

        // ProductRepositoryInterface está registrado en ReservationServiceProvider (primera fuente de verdad).
        // CatalogServiceProvider lo consume vía Container::make() sin re-declararlo.

        // ── Servicios ────────────────────────────────────────────────

        Container::singleton(MenuService::class, fn () => new MenuService(
            Container::make(MenuRepositoryInterface::class)
        ));

        Container::singleton(MenuServiceInterface::class, fn () => Container::make(MenuService::class));

        Container::singleton(ProductService::class, fn () => new ProductService(
            Container::make(ProductRepositoryInterface::class)
        ));

        Container::singleton(ProductServiceInterface::class, fn () => Container::make(ProductService::class));

        Container::singleton(AllergenRepositoryInterface::class, fn () => new AllergenRepository(
            Database::getConnection()
        ));

        Container::singleton(AllergenService::class, fn () => new AllergenService(
            Container::make(AllergenRepositoryInterface::class)
        ));

        Container::singleton(AllergenServiceInterface::class, fn () => Container::make(AllergenService::class));
    }

    #[Override]
    public function boot(): void
    {
        // Sin bootstrap específico
    }
}
