<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Domain\Mappers\AnimalMapper;
use App\Repositories\AnimalRepository;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\PassInclusionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Repositories\PassInclusionRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\TimeSlotRepository;
use App\Repositories\WaitlistRepository;
use App\Services\AvailabilityService;
use App\Services\Contracts\AvailabilityServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\FestivosJaponesesServiceInterface;
use App\Services\Contracts\FileStorageServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ReservationTimeSlotServiceInterface;
use App\Services\Contracts\TimeSlotServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\Contracts\WaitlistServiceInterface;
use App\Services\EmailService;
use App\Services\FestivosEspañolesService;
use App\Services\FestivosJaponesesService;
use App\Services\InvoicePDFService;
use App\Services\ReservationService;
use App\Services\ReservationTimeSlotService;
use App\Services\TimeSlotService;
use App\Services\WaitlistService;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ReservationServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        // Repositorios de reservas
        Container::singleton(ReservationRepositoryInterface::class, fn() => new ReservationRepository(
            Database::getConnection()
        ));

        Container::singleton(ProductRepositoryInterface::class, fn() => new ProductRepository(
            Database::getConnection()
        ));

        Container::singleton(PassInclusionRepositoryInterface::class, fn() => new PassInclusionRepository(
            Database::getConnection()
        ));

        Container::singleton(AnimalRepositoryInterface::class, fn() => new AnimalRepository(
            new AnimalMapper(),
            Database::getConnection()
        ));

        Container::singleton(TimeSlotRepositoryInterface::class, fn() => new TimeSlotRepository(
            Database::getConnection()
        ));

        // Servicios de soporte
        Container::singleton(InvoicePDFServiceInterface::class, fn() => new InvoicePDFService());

        Container::singleton(EmailServiceInterface::class, fn() => new EmailService());

        // Servicio principal de reservas (DI limpio)
        Container::singleton(ReservationService::class, fn() => new ReservationService(
            Container::make(ReservationRepositoryInterface::class),
            Container::make(CafeRepositoryInterface::class),
            Container::make(ProductRepositoryInterface::class),
            Container::make(InvoicePDFServiceInterface::class),
            Container::make(EmailServiceInterface::class),
            Container::make(EventDispatcherInterface::class),
            Container::make(UserProfileServiceInterface::class),
            Container::make(FileStorageServiceInterface::class),
            null,
            Container::make(PassInclusionRepositoryInterface::class),
            Container::make(TimeSlotRepositoryInterface::class)
        ));

        Container::singleton(ReservationServiceInterface::class, fn() => Container::make(ReservationService::class));

        // Servicio integrador de reserva + time slot
        Container::singleton(ReservationTimeSlotService::class, function () {
            return new ReservationTimeSlotService(
                Database::getConnection(),
                Container::make(ReservationRepositoryInterface::class),
                Container::make(TimeSlotRepositoryInterface::class),
                Container::make(WaitlistRepositoryInterface::class)
            );
        });

        Container::singleton(ReservationTimeSlotServiceInterface::class, fn() => Container::make(ReservationTimeSlotService::class));

        // WaitlistService: gestión completa de lista de espera
        Container::singleton(WaitlistRepository::class, fn() => new WaitlistRepository(
            Database::getConnection()
        ));

        Container::singleton(WaitlistRepositoryInterface::class, fn() => Container::make(
            WaitlistRepository::class
        ));

        Container::singleton(WaitlistService::class, fn() => new WaitlistService(
            Database::getConnection(),
            Container::make(EmailServiceInterface::class),
            Container::make(WaitlistRepositoryInterface::class),
            Container::make(TimeSlotRepositoryInterface::class),
            Container::make(ReservationRepositoryInterface::class)
        ));

        Container::singleton(WaitlistServiceInterface::class, fn() => Container::make(WaitlistService::class));

        Container::singleton(TimeSlotService::class, fn() => new TimeSlotService(Container::make(TimeSlotRepositoryInterface::class)));
        Container::singleton(TimeSlotServiceInterface::class, fn() => Container::make(TimeSlotService::class));

        Container::singleton(FestivosJaponesesService::class, fn() => new FestivosJaponesesService());
        Container::singleton(FestivosEspañolesService::class, fn() => new FestivosEspañolesService());
        Container::singleton(FestivosJaponesesServiceInterface::class, fn() => Container::make(FestivosEspañolesService::class));

        Container::singleton(AvailabilityService::class, fn() => new AvailabilityService(
            Container::make(CafeRepositoryInterface::class),
            Container::make(ProductRepositoryInterface::class),
            Container::make(ReservationRepositoryInterface::class),
            Container::make(TimeSlotRepositoryInterface::class),
        ));
        Container::singleton(AvailabilityServiceInterface::class, fn() => Container::make(AvailabilityService::class));
    }

    #[Override]
    public function boot(): void
    {
        // No hay bootstrap específico
    }
}
