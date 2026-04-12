<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
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
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service Provider para autenticación y gestión de sesiones.
 *
 * Registra: AuthTokenService, SessionManagementService, AuthService
 * Prerequisitos: UserRepositoryInterface, RateLimitingServiceInterface
 */
final class AuthServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Container::singleton(AuthTokenService::class, fn() => new AuthTokenService(
            Database::getConnection()
        ));

        Container::singleton(AuthTokenServiceInterface::class, fn() => Container::make(AuthTokenService::class));

        Container::singleton(AccountDeletionService::class, fn() => new AccountDeletionService(
            Database::getConnection()
        ));

        Container::singleton(AccountDeletionServiceInterface::class, fn() => Container::make(AccountDeletionService::class));

        Container::singleton(SessionManagementService::class, fn() => new SessionManagementService(
            Database::getConnection()
        ));

        Container::singleton(SessionManagementServiceInterface::class, fn() => Container::make(SessionManagementService::class));

        // EmailServiceInterface: si ya está registrado (ReservationServiceProvider lo registra),
        // este binding no sobreescribe porque el primero gana en PHP-DI.
        // Lo registramos aquí para garantizar disponibilidad independiente del orden.
        Container::singleton(EmailService::class, fn() => new EmailService());

        Container::singleton(PasswordResetServiceInterface::class, fn() => new PasswordResetService(
            new User(),
            Container::make(AuthTokenService::class),
            Container::make(SessionManagementService::class),
            Container::make(RateLimitingServiceInterface::class),
            Container::make(EmailServiceInterface::class)
        ));

        Container::singleton(EmailVerificationServiceInterface::class, fn() => new EmailVerificationService(
            new User(),
            Container::make(AuthTokenService::class),
            Container::make(EmailServiceInterface::class)
        ));

        Container::singleton(AuthService::class, fn() => new AuthService(
            Container::make(UserRepositoryInterface::class),
            new User(),
            Container::make(SessionManagementService::class),
            Container::make(RateLimitingServiceInterface::class),
            Database::getConnection(),
            Container::make(EventDispatcherInterface::class)
        ));

        Container::singleton(AuthServiceInterface::class, fn() => Container::make(AuthService::class));
    }

    #[\Override]
    public function boot(): void
    {
        // Sin bootstrap específico
    }
}
