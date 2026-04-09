<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modelo ReservationItem
 *
 * Gestiona los items (productos) pedidos durante una reserva.
 * Usado por Kitchen Display System (KDS).
 */
final class ReservationItem
{
    private PDO $db;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_KITCHEN = 'kitchen';
    public const string STATUS_READY = 'ready';
    public const string STATUS_SERVED = 'served';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Añade un item a una reserva.
     */
    public function add(int $reservationId, int $productId, int $quantity, float $unitPrice): int
    {
        $sql = 'INSERT INTO reservation_items (reservation_id, product_id, quantity, unit_price)
                VALUES (:reservation_id, :product_id, :quantity, :unit_price)';

        $this->db->prepare($sql)->execute([
            'reservation_id' => $reservationId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private const SELECT_FIELDS = 'ri.id, ri.reservation_id, ri.product_id, ri.quantity, ri.unit_price, ri.status, ri.created_at';

    /**
     * Obtiene items de una reserva.
     */
    public function findByReservation(int $reservationId): array
    {
        $sql = 'SELECT ' . self::SELECT_FIELDS . ', p.name AS product_name, p.station
                FROM reservation_items ri
                JOIN products p ON p.id = ri.product_id
                WHERE ri.reservation_id = :reservation_id
                ORDER BY ri.created_at';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['reservation_id' => $reservationId]);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene items pendientes para cocina (KDS).
     */
    public function findPendingByStation(int $cafeId, string $station): array
    {
        $sql = 'SELECT ' . self::SELECT_FIELDS . ",
                       p.name AS product_name, p.recipe_steps, p.prep_time,
                       t.code AS tracker_code
                FROM reservation_items ri
                JOIN products p ON p.id = ri.product_id
                JOIN reservations r ON r.id = ri.reservation_id
                LEFT JOIN trackers t ON t.id = r.tracker_id
                WHERE r.cafe_id = :cafe_id
                  AND p.station = :station
                  AND ri.status IN ('pending', 'kitchen')
                  AND r.status = 'active'
                ORDER BY ri.created_at";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'station' => $station]);

        return $stmt->fetchAll();
    }

    /**
     * Actualiza el estado de un item.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reservation_items SET status = :status WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Marca un item como listo (bump).
     */
    public function markReady(int $id): bool
    {
        return $this->updateStatus($id, self::STATUS_READY);
    }

    /**
     * Marca un item como servido.
     */
    public function markServed(int $id): bool
    {
        return $this->updateStatus($id, self::STATUS_SERVED);
    }
}
