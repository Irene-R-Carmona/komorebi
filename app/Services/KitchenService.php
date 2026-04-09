<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Product;
use App\Models\ReservationItem;
use PDO;

/**
 * Servicio de Cocina (KDS - Kitchen Display System)
 *
 * Gestiona el flujo de comandas para la cocina.
 */
final class KitchenService
{
    private PDO $db;
    private ReservationItem $itemModel;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->itemModel = new ReservationItem($this->db);
    }

    // ─────────────────────────────────────────────────────────────
    // Comandas Pendientes
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todas las comandas pendientes agrupadas por estación.
     *
     * @return array<string, array>
     */
    public function getPendingByStation(int $cafeId): array
    {
        $stations = Product::VALID_STATIONS;
        $result = [];

        foreach ($stations as $station) {
            $items = $this->itemModel->findPendingByStation($cafeId, $station);

            if (!empty($items)) {
                $result[$station] = $this->enrichItems($items);
            }
        }

        return $result;
    }

    /**
     * Obtiene comandas pendientes de una estación específica.
     */
    public function getPendingForStation(int $cafeId, string $station): array
    {
        $items = $this->itemModel->findPendingByStation($cafeId, $station);

        return $this->enrichItems($items);
    }

    /**
     * Obtiene todas las comandas pendientes (sin agrupar).
     */
    public function getAllPending(int $cafeId): array
    {
        $sql = "
            SELECT
                ri.id, ri.quantity, ri.status, ri.created_at, ri.reservation_id,
                p.id AS product_id, p.name AS product_name, p.station,
                p.prep_time, p.recipe_steps, p.ingredients_list, p.critical_check,
                t.code AS tracker_code,
                r.guest_count AS guests
            FROM reservation_items ri
            JOIN products p ON ri.product_id = p.id
            JOIN reservations r ON ri.reservation_id = r.id
            LEFT JOIN trackers t ON r.tracker_id = t.id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
              AND ri.status IN ('pending', 'kitchen')
              AND r.status = 'active'
            ORDER BY ri.created_at
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $this->enrichItems($stmt->fetchAll());
    }

    // ─────────────────────────────────────────────────────────────
    // Gestión de Estados
    // ─────────────────────────────────────────────────────────────

    /**
     * Marca un item como "en preparación" (kitchen).
     */
    public function startPreparing(int $itemId): bool
    {
        return $this->itemModel->updateStatus($itemId, ReservationItem::STATUS_KITCHEN);
    }

    /**
     * Marca un item como listo (bump).
     */
    public function markReady(int $itemId): bool
    {
        return $this->itemModel->markReady($itemId);
    }

    /**
     * Marca un item como servido.
     */
    public function markServed(int $itemId): bool
    {
        return $this->itemModel->markServed($itemId);
    }

    /**
     * Marca todos los items de una reserva como listos (bump ticket completo).
     */
    public function bumpTicket(int $reservationId): int
    {
        $sql = "UPDATE reservation_items
                SET status = :status
                WHERE reservation_id = :reservation_id
                  AND status IN ('pending', 'kitchen')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'reservation_id' => $reservationId,
            'status' => ReservationItem::STATUS_READY,
        ]);

        return $stmt->rowCount();
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene estadísticas de cocina para el día.
     */
    public function getDailyStats(int $cafeId): array
    {
        $sql = "
            SELECT
                COUNT(CASE WHEN ri.status = 'pending' THEN 1 END) AS pending,
                COUNT(CASE WHEN ri.status = 'kitchen' THEN 1 END) AS in_progress,
                COUNT(CASE WHEN ri.status = 'ready' THEN 1 END) AS ready,
                COUNT(CASE WHEN ri.status = 'served' THEN 1 END) AS served,
                AVG(
                    CASE WHEN ri.status IN ('ready', 'served')
                    THEN TIMESTAMPDIFF(MINUTE, ri.created_at, NOW())
                    END
                ) AS avg_prep_time
            FROM reservation_items ri
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);
        $stats = $stmt->fetch();

        return [
            'pending' => (int) ($stats['pending'] ?? 0),
            'in_progress' => (int) ($stats['in_progress'] ?? 0),
            'ready' => (int) ($stats['ready'] ?? 0),
            'served' => (int) ($stats['served'] ?? 0),
            'avg_prep_time' => \round((float) ($stats['avg_prep_time'] ?? 0), 1),
        ];
    }

    /**
     * Obtiene el tiempo estimado de espera actual.
     */
    public function getEstimatedWaitTime(int $cafeId): int
    {
        $sql = "
            SELECT SUM(p.prep_time * ri.quantity) AS total_prep_time
            FROM reservation_items ri
            JOIN products p ON ri.product_id = p.id
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
              AND ri.status IN ('pending', 'kitchen')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Enriquece los items con datos adicionales.
     */
    private function enrichItems(array $items): array
    {
        foreach ($items as &$item) {
            // Decodificar JSON
            if (isset($item['ingredients_list']) && \is_string($item['ingredients_list'])) {
                $item['ingredients_list'] = \json_decode($item['ingredients_list'], true) ?? [];
            }

            // Calcular tiempo de espera
            if (isset($item['created_at'])) {
                $created = \strtotime($item['created_at']);
                $item['waiting_minutes'] = (int) \floor((\time() - $created) / 60);
                $item['is_delayed'] = $item['waiting_minutes'] > ($item['prep_time'] ?? 10);
            }

            // Default station
            if (empty($item['station'])) {
                $item['station'] = Product::STATION_ASSEMBLY;
            }
        }

        return $items;
    }
}
