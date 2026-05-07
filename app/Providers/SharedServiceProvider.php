<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\ServiceProvider;
use App\Domain\Mappers\CafeMapper;
use App\Domain\Mappers\MenuCategoryMapper;
use App\Repositories\CafeRepository;
use App\Repositories\Contracts\CafeCatalogRepositoryInterface;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Repositories\Contracts\UserManagementRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\FavoriteRepository;
use App\Repositories\LoyaltyRepository;
use App\Repositories\MenuCategoryRepository;
use App\Repositories\ReservationItemRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\SettingRepository;
use App\Repositories\StatisticsRepository;
use App\Services\AdminActivityService;
use App\Services\AdminReportService;
use App\Services\AdminStatisticsService;
use App\Services\CafeService;
use App\Services\CartService;
use App\Services\ClimaContextoService;
use App\Services\CloudinaryStorageService;
use App\Services\Contracts\AdminActivityServiceInterface;
use App\Services\Contracts\AdminReportServiceInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use App\Services\Contracts\CafeServiceInterface;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\ClimaContextoServiceInterface;
use App\Services\Contracts\FileStorageServiceInterface;
use App\Services\Contracts\FileUploadServiceInterface;
use App\Services\Contracts\GamificationServiceInterface;
use App\Services\Contracts\LoyaltyServiceInterface;
use App\Services\Contracts\ReviewModerationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use App\Services\Contracts\SettingsServiceInterface;
use App\Services\Contracts\UserAccountServiceInterface;
use App\Services\Contracts\UserManagementServiceInterface;
use App\Services\Contracts\UserPreferenceServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\Contracts\WeatherServiceInterface;
use App\Services\FileUploadService;
use App\Services\GamificationService;
use App\Services\LoyaltyService;
use App\Services\ReviewModerationService;
use App\Services\ReviewQueryService;
use App\Services\ReviewService;
use App\Services\SettingsService;
use App\Services\UserAccountService;
use App\Services\UserManagementService;
use App\Services\UserPreferenceService;
use App\Services\UserProfileService;
use App\Services\WeatherService;
use Override;
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
    #[Override]
    public function register(): void
    {
        // ── Repositorios ────────────────────────────────────────────

        Container::singleton(CafeRepositoryInterface::class, fn() => new CafeRepository(
            new CafeMapper(),
            Database::getConnection()
        ));

        Container::singleton(CafeCatalogRepositoryInterface::class, fn() => Container::make(CafeRepositoryInterface::class));

        Container::singleton(ReviewRepositoryInterface::class, fn() => new ReviewRepository(
            Database::getConnection()
        ));

        Container::singleton(FavoriteRepository::class, fn() => new FavoriteRepository(
            Database::getConnection()
        ));
        Container::singleton(FavoriteRepositoryInterface::class, fn() => Container::make(
            FavoriteRepository::class
        ));

        Container::singleton(MenuCategoryRepository::class, fn() => new MenuCategoryRepository(
            new MenuCategoryMapper(),
            Database::getConnection()
        ));
        Container::singleton(MenuCategoryRepositoryInterface::class, fn() => Container::make(
            MenuCategoryRepository::class
        ));

        // ── Servicios ────────────────────────────────────────────────

        Container::singleton(CafeService::class, fn() => new CafeService(
            Container::make(CafeRepositoryInterface::class)
        ));

        Container::singleton(CafeServiceInterface::class, fn() => Container::make(CafeService::class));

        Container::singleton(UserProfileServiceInterface::class, fn() => new UserProfileService(
            Container::make(UserRepositoryInterface::class)
        ));

        Container::singleton(UserPreferenceServiceInterface::class, fn() => new UserPreferenceService(
            Container::make(UserRepositoryInterface::class)
        ));

        Container::singleton(UserAccountServiceInterface::class, fn() => new UserAccountService(
            Container::make(UserRepositoryInterface::class)
        ));

        Container::singleton(ReviewService::class, fn() => new ReviewService(
            Container::make(UserRepositoryInterface::class),
            Container::make(ReviewRepositoryInterface::class),
            Container::make(ReservationRepositoryInterface::class)
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

        Container::singleton(LoyaltyRepository::class, fn() => new LoyaltyRepository(
            Database::getConnection()
        ));
        Container::singleton(LoyaltyRepositoryInterface::class, fn() => Container::make(LoyaltyRepository::class));

        Container::singleton(LoyaltyService::class, fn() => new LoyaltyService(
            Container::make(LoyaltyRepositoryInterface::class)
        ));
        Container::singleton(LoyaltyServiceInterface::class, fn() => Container::make(LoyaltyService::class));

        Container::singleton(GamificationService::class, fn() => new GamificationService());
        Container::singleton(GamificationServiceInterface::class, fn() => Container::make(GamificationService::class));

        Container::singleton(WeatherService::class, fn() => new WeatherService());
        Container::singleton(WeatherServiceInterface::class, fn() => Container::make(WeatherService::class));

        Container::singleton(ClimaContextoService::class, fn() => new ClimaContextoService(
            Container::make(WeatherService::class)
        ));
        Container::singleton(ClimaContextoServiceInterface::class, fn() => Container::make(ClimaContextoService::class));

        Container::singleton(CartService::class, fn() => new CartService(
            Container::make(ProductRepositoryInterface::class)
        ));
        Container::singleton(CartServiceInterface::class, fn() => Container::make(CartService::class));

        Container::singleton(SettingRepository::class, fn() => new SettingRepository(Database::getConnection()));
        Container::singleton(SettingRepositoryInterface::class, fn() => Container::make(SettingRepository::class));
        Container::singleton(SettingsService::class, fn() => new SettingsService(
            Container::make(SettingRepositoryInterface::class)
        ));
        Container::singleton(SettingsServiceInterface::class, fn() => Container::make(SettingsService::class));

        Container::singleton(FileUploadService::class, fn() => new FileUploadService());
        Container::singleton(FileUploadServiceInterface::class, fn() => Container::make(FileUploadService::class));

        Container::singleton(CloudinaryStorageService::class, static fn() => new CloudinaryStorageService(
            Env::get('CLOUDINARY_CLOUD_NAME', ''),
            Env::get('CLOUDINARY_API_KEY', ''),
            Env::get('CLOUDINARY_API_SECRET', ''),
        ));
        Container::singleton(FileStorageServiceInterface::class, fn() => Container::make(CloudinaryStorageService::class));

        Container::singleton(UserManagementRepositoryInterface::class, fn() => Container::make(UserRepositoryInterface::class));

        Container::singleton(UserManagementService::class, fn() => new UserManagementService());
        Container::singleton(UserManagementServiceInterface::class, fn() => Container::make(UserManagementService::class));

        Container::singleton(AdminActivityService::class, fn() => new AdminActivityService());
        Container::singleton(AdminActivityServiceInterface::class, fn() => Container::make(AdminActivityService::class));

        Container::singleton(StatisticsRepository::class, fn() => new StatisticsRepository(
            Database::getConnection()
        ));
        Container::singleton(StatisticsRepositoryInterface::class, fn() => Container::make(
            StatisticsRepository::class
        ));
        Container::singleton(AdminStatisticsService::class, fn() => new AdminStatisticsService(
            Container::make(StatisticsRepositoryInterface::class)
        ));
        Container::singleton(AdminStatisticsServiceInterface::class, fn() => Container::make(AdminStatisticsService::class));

        Container::singleton(AdminReportService::class, fn() => new AdminReportService());
        Container::singleton(AdminReportServiceInterface::class, fn() => Container::make(AdminReportService::class));

        Container::singleton(ReservationItemRepository::class, fn() => new ReservationItemRepository(
            Database::getConnection()
        ));
        Container::singleton(ReservationItemRepositoryInterface::class, fn() => Container::make(
            ReservationItemRepository::class
        ));
    }

    #[Override]
    public function boot(): void
    {
        // Sin bootstrap específico
    }
}
