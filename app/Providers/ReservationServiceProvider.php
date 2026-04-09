<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\Waitlist;
use App\Services\ReservationTimeSlotService;

/**
 * Provider para servicios de reservas y time slots
 */
final class ReservationServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Registrar modelos (lazy loading)
        Container::singleton(Reservation::class, function () {
            return new Reservation();
        });

        Container::singleton(TimeSlot::class, function () {
            return new TimeSlot(Database::getConnection());
        });

        Container::singleton(Waitlist::class, function () {
            return new Waitlist(Database::getConnection());
        });

        // Registrar servicio integrador
        Container::singleton(ReservationTimeSlotService::class, function () {
            return new ReservationTimeSlotService(
                Database::getConnection(), // Devuelve PDO
                Container::make(Reservation::class),
                Container::make(TimeSlot::class),
                Container::make(Waitlist::class)
            );
        });
    }

    #[\Override]
    public function boot(): void
    {
        // No hay bootstrap específico
    }
}
