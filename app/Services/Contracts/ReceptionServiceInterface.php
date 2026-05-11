<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Interfaz para el Servicio de Recepción
 */
interface ReceptionServiceInterface
{
    public function getDashboard(int $cafeId): array;

    public function getPendingArrivals(int $cafeId): array;

    public function getActiveGroups(int $cafeId): array;

    public function getAvailableTrackers(int $cafeId): array;

    public function getCapacityInfo(int $cafeId): array;

    public function processCheckin(int $reservationId, int $trackerId): Result;

    public function processCheckout(int $reservationId): Result;

    public function assignTracker(int $reservationId, int $trackerId): bool;

    public function completeProtocol(int $reservationId, string $protocol): bool;

    public function getProtocolStatus(int $reservationId): Result;

    public function getDailyStats(int $cafeId, string $date): array;

    public function addItem(int $reservationId, int $productId, int $qty, int $cafeId): Result;

    public function processPayment(int $reservationId, string $paymentMethod, int $cafeId, ?string $notes = null): Result;

    public function activatePreOrder(int $reservationId, int $cafeId): Result;
}
