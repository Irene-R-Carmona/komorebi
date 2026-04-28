<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\AuthLogRepository;
use App\Repositories\AuthTokenRepository;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\SessionRepository;
use App\Services\AccountDeletionService;
use App\Services\AuthService;
use App\Services\AuthTokenService;
use App\Services\Contracts\AccountDeletionServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\AuthTokenServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\EmailVerificationServiceInterface;
use App\Services\Contracts\PasswordResetServiceInterface;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use App\Services\EmailService;
use App\Services\EmailVerificationService;
use App\Services\PasswordResetService;
use App\Services\SessionManagementService;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service Provider para autenticación y gestión de sesiones.
 *
 * Registra: AuthTokenService, SessionManagementService, AuthService
 * Prerequisitos: UserRepositoryInterface, RateLimitingServiceInterface
 */
final class AuthServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(AuthTokenRepository::class, fn() => new AuthTokenRepository(
            Database::getConnection()
        ));

        Container::singleton(AuthTokenRepositoryInterface::class, fn() => Container::make(AuthTokenRepository::class));

        Container::singleton(AuthTokenService::class, fn() => new AuthTokenService(
            Container::make(AuthTokenRepositoryInterface::class)
        ));

        Container::singleton(AuthTokenServiceInterface::class, fn() => Container::make(AuthTokenService::class));

        Container::singleton(AccountDeletionService::class, fn() => new AccountDeletionService(
            Container::make(UserRepositoryInterface::class)
        ));

        Container::singleton(AccountDeletionServiceInterface::class, fn() => Container::make(AccountDeletionService::class));

        Container::singleton(SessionRepository::class, fn() => new SessionRepository(
            Database::getConnection()
        ));
        Container::singleton(SessionRepositoryInterface::class, fn() => Container::make(SessionRepository::class));

        // AuthLogRepository ya se registra en StaffServiceProvider; si no está disponible
        // (FEATURE_BACKOFFICE=false), registramos un fallback aquí para garantizar DI.
        Container::singleton(AuthLogRepository::class, fn() => new AuthLogRepository(
            Database::getConnection()
        ));
        Container::singleton(AuthLogRepositoryInterface::class, fn() => Container::make(AuthLogRepository::class));

        Container::singleton(SessionManagementService::class, fn() => new SessionManagementService(
            Container::make(SessionRepositoryInterface::class),
            Container::make(AuthLogRepositoryInterface::class)
        ));

        Container::singleton(SessionManagementServiceInterface::class, fn() => Container::make(SessionManagementService::class));

        // EmailServiceInterface: si ya está registrado (ReservationServiceProvider lo registra),
        // este binding no sobreescribe porque el primero gana en PHP-DI.
        // Lo registramos aquí para garantizar disponibilidad independiente del orden.
        Container::singleton(EmailService::class, fn() => new EmailService());

        Container::singleton(PasswordResetServiceInterface::class, fn() => new PasswordResetService(
            Container::make(UserRepositoryInterface::class),
            Container::make(AuthTokenService::class),
            Container::make(SessionManagementService::class),
            Container::make(RateLimitingServiceInterface::class),
            Container::make(EmailServiceInterface::class)
        ));

        Container::singleton(EmailVerificationServiceInterface::class, fn() => new EmailVerificationService(
            Container::make(UserRepositoryInterface::class),
            Container::make(AuthTokenService::class),
            Container::make(EmailServiceInterface::class)
        ));

        Container::singleton(AuthService::class, fn() => new AuthService(
            Container::make(UserRepositoryInterface::class),
            Container::make(SessionManagementService::class),
            Container::make(RateLimitingServiceInterface::class),
            Container::make(EventDispatcherInterface::class)
        ));

        Container::singleton(AuthServiceInterface::class, fn() => Container::make(AuthService::class));
    }

    #[Override]
    public function boot(): void
    {
        // Sin bootstrap específico
    }
}
