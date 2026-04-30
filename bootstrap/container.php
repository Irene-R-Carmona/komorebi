<?php

declare(strict_types=1);

/**
 * Bootstrap del Container y Service Providers.
 *
 * Este archivo se encarga de:
 * 1. Inicializar el Container
 * 2. Registrar Service Providers
 * 3. Ejecutar boot() de los providers
 *
 * Se llama desde public/index.php DESPUÉS de cargar Composer y Env.
 */

use App\Core\Config;
use App\Core\Container;
use App\Core\Env;
use App\Jobs\RewardUnlockedJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Providers\AuthServiceProvider;
use App\Providers\CacheServiceProvider;
use App\Providers\CatalogServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\NewsletterServiceProvider;
use App\Providers\ReservationServiceProvider;
use App\Providers\SharedServiceProvider;
use App\Providers\StaffServiceProvider;
use App\Repositories\ApiTokenRepository;
use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\AccountDeletionService;
use App\Services\ApiTokenService;
use App\Services\CacheService;
use App\Services\ClimaContextoService;
use App\Services\ContextServiceInstance;
use App\Services\Contracts\ApiTokenServiceInterface;
use App\Services\Contracts\ContextServiceInterface;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Contracts\NavigationServiceInterface;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\RecentlyViewedServiceInterface;
use App\Services\Contracts\TelegramServiceInterface;
use App\Services\Contracts\WeatherServiceInterface;
use App\Services\Manager\DashboardService;
use App\Services\NavigationService;
use App\Services\RateLimitingService;
use App\Services\RecentlyViewedService;
use App\Services\TelegramService;
use App\Services\WeatherService;

// Asegurar que Config está inicializado
// Usar `getString` para evitar TypeErrors si el default no es string
Config::getString('app.name', 'Komorebi'); // Trigger lazy init

// Lista de Service Providers a registrar
$providers = [
    DatabaseServiceProvider::class,
    CacheServiceProvider::class,
    EventServiceProvider::class,
    SharedServiceProvider::class,       // CafeRepositoryInterface, ReviewService, CafeService, UserService
    ReservationServiceProvider::class,
    CatalogServiceProvider::class,      // MenuService, ProductService
    AuthServiceProvider::class,         // AuthService, AuthTokenService, SessionManagementService
    NewsletterServiceProvider::class,
];

if (Env::bool('FEATURE_BACKOFFICE', true)) {
    $providers[] = StaffServiceProvider::class;
}

if (\App\Core\Env::bool('FEATURE_KEEPER', true)) {
    $providers[] = \App\Providers\KeeperServiceProvider::class;
}

if (\App\Core\Env::bool('FEATURE_OPS', true)) {
    $providers[] = \App\Providers\OpsServiceProvider::class;
}

// Fase 1: register() para todos los providers
foreach ($providers as $providerClass) {
    $provider = new $providerClass();
    $provider->register();
}

// Registros manuales — deben estar antes de boot() para que el Container
// tenga todos los bindings cuando EventServiceProvider::boot() dispara ensureBuild().

// UserRepositoryInterface → UserRepository
Container::singleton(UserRepositoryInterface::class, fn () => new UserRepository(
    Container::make(\PDO::class)
));
// Alias concreto para inyección directa (AuthService espera ?UserRepository)
Container::singleton(UserRepository::class, fn () => Container::make(UserRepositoryInterface::class));

// AccountDeletionService: eliminación atómica de cuentas (GDPR)
Container::singleton(AccountDeletionService::class, fn () => new AccountDeletionService(
    Container::make(\PDO::class)
));

// RewardUnlockedJob: PDO inyectado (no Database::getConnection() estático)
Container::singleton(RewardUnlockedJob::class, fn () => new RewardUnlockedJob(
    Container::make(\PDO::class)
));

// SendTelegramNotificationJob: notificaciones Telegram asíncronas
Container::singleton(SendTelegramNotificationJob::class, fn () => new SendTelegramNotificationJob(
    Container::make(TelegramServiceInterface::class)
));

// TelegramService: notificaciones internas vía bot
Container::singleton(TelegramService::class, static fn (): TelegramService => new TelegramService());
Container::singleton(TelegramServiceInterface::class, static fn (): TelegramServiceInterface => Container::make(TelegramService::class));

// Servicios de clima: WeatherService (HTTP + caché) y ClimaContextoService (contexto poético)
Container::singleton(WeatherService::class, static function (): WeatherService {
    return new WeatherService(Container::make(CacheService::class));
});

Container::singleton(ClimaContextoService::class, static function (): ClimaContextoService {
    return new ClimaContextoService(Container::make(WeatherServiceInterface::class));
});

// Tokens Bearer opacos para la API
Container::singleton(ApiTokenRepositoryInterface::class, fn () => new ApiTokenRepository());
Container::singleton(ApiTokenServiceInterface::class, fn () => new ApiTokenService(
    Container::make(ApiTokenRepositoryInterface::class),
    Container::make(UserRepositoryInterface::class)
));
// Alias por concrete class (para compatibilidad interna si fuera necesario)
Container::singleton(ApiTokenRepository::class, fn () => Container::make(ApiTokenRepositoryInterface::class));
Container::singleton(ApiTokenService::class, fn () => Container::make(ApiTokenServiceInterface::class));

// ContextServiceInstance: versión inyectable de ContextService (per-request)
// El bind de la interfaz permite inyectar por contrato (no por clase concreta)
Container::bind(ContextServiceInterface::class, function (): ContextServiceInstance {
    $selectedId = \App\Core\Session::get('admin_selected_cafe_id');

    return new ContextServiceInstance(
        Container::make(\App\Repositories\Contracts\CafeRepositoryInterface::class),
        \App\Core\Session::role(),
        \App\Core\Session::userCafeId(),
        $selectedId !== null ? (int) $selectedId : null
    );
});
Container::bind(ContextServiceInstance::class, function (): ContextServiceInstance {
    $selectedId = \App\Core\Session::get('admin_selected_cafe_id');

    return new ContextServiceInstance(
        Container::make(\App\Repositories\Contracts\CafeRepositoryInterface::class),
        \App\Core\Session::role(),
        \App\Core\Session::userCafeId(),
        $selectedId !== null ? (int) $selectedId : null
    );
});

// NavigationService: singleton inyectable (sin dependencias, lógica pura)
Container::singleton(NavigationService::class, fn () => new NavigationService());
Container::singleton(NavigationServiceInterface::class, fn () => Container::make(NavigationService::class));

// DashboardService + RecentlyViewedService: bindings para inyección por interfaz
Container::singleton(DashboardService::class, fn () => new DashboardService());
Container::singleton(DashboardServiceInterface::class, fn () => Container::make(DashboardService::class));
Container::singleton(RecentlyViewedService::class, fn () => new RecentlyViewedService());
Container::singleton(RecentlyViewedServiceInterface::class, fn () => Container::make(RecentlyViewedService::class));

// RateLimitingServiceInterface: rate limiting vía PSR-6 cache
Container::singleton(RateLimitingServiceInterface::class, fn () => new RateLimitingService(
    Container::make(CacheService::class)
));

// Fase 2: boot() para todos los providers
foreach ($providers as $providerClass) {
    $provider = new $providerClass();
    $provider->boot();
}
