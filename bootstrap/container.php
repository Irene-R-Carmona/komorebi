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
use App\Providers\CacheServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\NewsletterServiceProvider;
use App\Providers\ReservationServiceProvider;
use App\Providers\StaffServiceProvider;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\AccountDeletionService;
use App\Jobs\RewardUnlockedJob;
use App\Jobs\SendTelegramNotificationJob;
use App\Repositories\ApiTokenRepository;
use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use App\Services\ApiTokenService;
use App\Services\Contracts\ApiTokenServiceInterface;
use App\Services\CacheService;
use App\Services\ClimaContextoService;
use App\Services\Contracts\TelegramServiceInterface;
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
    ReservationServiceProvider::class,
    NewsletterServiceProvider::class,
];

if (Env::get('FEATURE_BACKOFFICE', '1') === '1') {
    $providers[] = StaffServiceProvider::class;
}

// Fase 1: register() para todos los providers
foreach ($providers as $providerClass) {
    $provider = new $providerClass();
    $provider->register();
}

// Fase 2: boot() para todos los providers
foreach ($providers as $providerClass) {
    $provider = new $providerClass();
    $provider->boot();
}

// UserRepositoryInterface → UserRepository
Container::singleton(UserRepositoryInterface::class, fn() => new UserRepository(
    Container::make(\PDO::class)
));

// AccountDeletionService: eliminación atómica de cuentas (GDPR)
Container::singleton(AccountDeletionService::class, fn() => new AccountDeletionService(
    Container::make(\PDO::class)
));

// RewardUnlockedJob: PDO inyectado (no Database::getConnection() estático)
Container::singleton(RewardUnlockedJob::class, fn() => new RewardUnlockedJob(
    Container::make(\PDO::class)
));

// SendTelegramNotificationJob: notificaciones Telegram asíncronas
Container::singleton(SendTelegramNotificationJob::class, fn() => new SendTelegramNotificationJob(
    Container::make(TelegramServiceInterface::class)
));

// TelegramService: notificaciones internas vía bot
Container::singleton(TelegramService::class, static fn(): TelegramService => new TelegramService());
Container::singleton(TelegramServiceInterface::class, static fn(): TelegramServiceInterface => Container::make(TelegramService::class));

// Servicios de clima: WeatherService (HTTP + caché) y ClimaContextoService (contexto poético)
Container::singleton(WeatherService::class, static function (): WeatherService {
    return new WeatherService(Container::make(CacheService::class));
});

Container::singleton(ClimaContextoService::class, static function (): ClimaContextoService {
    return new ClimaContextoService(Container::make(WeatherService::class));
});

// El Container ya está listo para usar
// Ejemplo: Container::make(PDO::class) retornará conexión configurada

// Tokens Bearer opacos para la API
Container::singleton(ApiTokenRepositoryInterface::class, fn() => new ApiTokenRepository());
Container::singleton(ApiTokenServiceInterface::class, fn() => new ApiTokenService(Container::make(ApiTokenRepositoryInterface::class)));
// Alias por concrete class (para compatibilidad interna si fuera necesario)
Container::singleton(ApiTokenRepository::class, fn() => Container::make(ApiTokenRepositoryInterface::class));
Container::singleton(ApiTokenService::class, fn() => Container::make(ApiTokenServiceInterface::class));
