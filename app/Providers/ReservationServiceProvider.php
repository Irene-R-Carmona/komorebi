<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\Waitlist;
use App\Repositories\AnimalRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\TimeSlotRepository;
use App\Repositories\WaitlistRepository;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Services\InvoicePDFService;
use App\Services\ReservationService;
use App\Services\ReservationTimeSlotService;
use App\Services\TimeSlotService;
use App\Services\WaitlistService;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\Contracts\ReservationTimeSlotServiceInterface;
use App\Services\Contracts\TimeSlotServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\Contracts\WaitlistServiceInterface;
use App\Services\EmailService;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Provider para servicios de reservas y time slots
 */
final class ReservationServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Repositorios de reservas
        Container::singleton(ReservationRepositoryInterface::class, fn() => new ReservationRepository(
            Database::getConnection()
        ));

        Container::singleton(ProductRepositoryInterface::class, fn() => new ProductRepository(
            Database::getConnection()
        ));

        Container::singleton(AnimalRepositoryInterface::class, fn() => new AnimalRepository(
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
            Container::make(UserProfileServiceInterface::class)
        ));

        // Modelos (lazy loading)
        Container::singleton(Reservation::class, function () {
            return new Reservation();
        });

        Container::singleton(TimeSlot::class, function () {
            return new TimeSlot(Database::getConnection());
        });

        Container::singleton(Waitlist::class, function () {
            return new Waitlist(Database::getConnection());
        });

        // Servicio integrador de reserva + time slot
        Container::singleton(ReservationTimeSlotService::class, function () {
            return new ReservationTimeSlotService(
                Database::getConnection(),
                Container::make(Reservation::class),
                Container::make(TimeSlot::class),
                Container::make(Waitlist::class)
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
            Container::make(WaitlistRepositoryInterface::class)
        ));

        Container::singleton(WaitlistServiceInterface::class, fn() => Container::make(WaitlistService::class));

        Container::singleton(TimeSlotService::class, fn() => new TimeSlotService(Database::getConnection()));
        Container::singleton(TimeSlotServiceInterface::class, fn() => Container::make(TimeSlotService::class));
    }

    #[\Override]
    public function boot(): void
    {
        // No hay bootstrap específico
    }
}
