<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Models\User;
use App\Repositories\CafeRepository;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\ReviewRepository;
use App\Services\CafeService;
use App\Services\CartService;
use App\Services\ClimaContextoService;
use App\Services\FileUploadService;
use App\Services\GamificationService;
use App\Services\HolidayService;
use App\Services\KitchenService;
use App\Services\LoyaltyService;
use App\Services\ReceptionService;
use App\Services\SettingsService;
use App\Services\UserManagementService;
use App\Services\WeatherService;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\CafeServiceInterface;
use App\Services\Contracts\ClimaContextoServiceInterface;
use App\Services\Contracts\FileUploadServiceInterface;
use App\Services\Contracts\GamificationServiceInterface;
use App\Services\Contracts\HolidayServiceInterface;
use App\Services\Contracts\KitchenServiceInterface;
use App\Services\Contracts\LoyaltyServiceInterface;
use App\Services\Contracts\ReceptionServiceInterface;
use App\Services\Contracts\ReviewModerationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use App\Services\Contracts\SettingsServiceInterface;
use App\Services\Contracts\UserAccountServiceInterface;
use App\Services\Contracts\UserManagementServiceInterface;
use App\Services\Contracts\UserPreferenceServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\Contracts\WeatherServiceInterface;
use App\Services\ReviewModerationService;
use App\Services\ReviewQueryService;
use App\Services\ReviewService;
use App\Services\UserAccountService;
use App\Services\UserPreferenceService;
use App\Services\UserProfileService;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service Provider para servicios compartidos de dominio.
 *
 * Registra: CafeRepositoryInterface, ReviewRepositoryInterface,
 *           CafeService, ReviewService.
 *
 * UserRepositoryInterface se registra en bootstrap/container.php
 * por dependencia de arranque temprano.
 */
final class SharedServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // ── Repositorios ────────────────────────────────────────────

        Container::singleton(CafeRepositoryInterface::class, fn() => new CafeRepository(
            Database::getConnection()
        ));

        Container::singleton(ReviewRepositoryInterface::class, fn() => new ReviewRepository(
            Database::getConnection()
        ));

        // ── Servicios ────────────────────────────────────────────────

        Container::singleton(CafeService::class, fn() => new CafeService(
            Container::make(CafeRepositoryInterface::class)
        ));

        Container::singleton(CafeServiceInterface::class, fn() => Container::make(CafeService::class));

        Container::singleton(UserProfileServiceInterface::class, fn() => new UserProfileService(
            Container::make(UserRepositoryInterface::class),
            new User()
        ));

        Container::singleton(UserPreferenceServiceInterface::class, fn() => new UserPreferenceService(
            Container::make(UserRepositoryInterface::class)
        ));

        Container::singleton(UserAccountServiceInterface::class, fn() => new UserAccountService(
            Container::make(UserRepositoryInterface::class),
            new User()
        ));

        Container::singleton(ReviewService::class, fn() => new ReviewService(
            new User(),
            Container::make(ReviewRepositoryInterface::class)
        ));

        Container::singleton(ReviewServiceInterface::class, fn() => Container::make(ReviewService::class));

        Container::singleton(ReviewQueryServiceInterface::class, fn() => new ReviewQueryService(
            Container::make(ReviewRepositoryInterface::class)
        ));

        Container::singleton(ReviewModerationServiceInterface::class, fn() => new ReviewModerationService(
            Container::make(ReviewRepositoryInterface::class),
            Container::make(CafeRepositoryInterface::class),
            Container::make(EventDispatcherInterface::class)
        ));

        Container::singleton(LoyaltyService::class, fn() => new LoyaltyService());
        Container::singleton(LoyaltyServiceInterface::class, fn() => Container::make(LoyaltyService::class));

        Container::singleton(GamificationService::class, fn() => new GamificationService());
        Container::singleton(GamificationServiceInterface::class, fn() => Container::make(GamificationService::class));

        Container::singleton(WeatherService::class, fn() => new WeatherService());
        Container::singleton(WeatherServiceInterface::class, fn() => Container::make(WeatherService::class));

        Container::singleton(ClimaContextoService::class, fn() => new ClimaContextoService(
            Container::make(WeatherService::class)
        ));
        Container::singleton(ClimaContextoServiceInterface::class, fn() => Container::make(ClimaContextoService::class));

        Container::singleton(CartService::class, fn() => new CartService());
        Container::singleton(CartServiceInterface::class, fn() => Container::make(CartService::class));

        Container::singleton(SettingsService::class, fn() => new SettingsService());
        Container::singleton(SettingsServiceInterface::class, fn() => Container::make(SettingsService::class));

        Container::singleton(HolidayService::class, fn() => new HolidayService());
        Container::singleton(HolidayServiceInterface::class, fn() => Container::make(HolidayService::class));

        Container::singleton(FileUploadService::class, fn() => new FileUploadService());
        Container::singleton(FileUploadServiceInterface::class, fn() => Container::make(FileUploadService::class));

        Container::singleton(UserManagementService::class, fn() => new UserManagementService());
        Container::singleton(UserManagementServiceInterface::class, fn() => Container::make(UserManagementService::class));

        Container::singleton(KitchenService::class, fn() => new KitchenService(Database::getConnection()));
        Container::singleton(KitchenServiceInterface::class, fn() => Container::make(KitchenService::class));

        Container::singleton(ReceptionService::class, fn() => new ReceptionService(Database::getConnection()));
        Container::singleton(ReceptionServiceInterface::class, fn() => Container::make(ReceptionService::class));
    }

    #[\Override]
    public function boot(): void
    {
        // Sin bootstrap específico
    }
}
