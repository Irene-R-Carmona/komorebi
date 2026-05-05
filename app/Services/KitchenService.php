<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ReservationItem;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\Contracts\KitchenServiceInterface;
use Override;

/**
 * Servicio de Cocina (KDS - Kitchen Display System)
 *
 * Gestiona el flujo de comandas para la cocina.
 */
final class KitchenService implements KitchenServiceInterface
{
    public function __construct(
        private readonly ReservationItemRepositoryInterface $itemRepo
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Comandas Pendientes
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function getPendingByStation(int $cafeId): array
    {
        $result = [];

        foreach (Product::VALID_STATIONS as $station) {
            $items = $this->itemRepo->findPendingByStation($cafeId, $station);

            if (!empty($items)) {
                $result[$station] = $this->enrichItems($items);
            }
        }

        return $result;
    }

    #[Override]
    public function getPendingForStation(int $cafeId, string $station): array
    {
        return $this->enrichItems(
            $this->itemRepo->findPendingByStation($cafeId, $station)
        );
    }

    #[Override]
    public function getAllPending(int $cafeId): array
    {
        return $this->enrichItems(
            $this->itemRepo->findAllPendingByCafe($cafeId)
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Gestión de Estados
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function startPreparing(int $itemId): bool
    {
        return $this->itemRepo->updateStatus($itemId, ReservationItem::STATUS_KITCHEN);
    }

    #[Override]
    public function markReady(int $itemId): bool
    {
        return $this->itemRepo->markReady($itemId);
    }

    #[Override]
    public function markServed(int $itemId): bool
    {
        return $this->itemRepo->markServed($itemId);
    }

    #[Override]
    public function bumpTicket(int $reservationId): int
    {
        return $this->itemRepo->bumpTicket($reservationId);
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function getDailyStats(int $cafeId): array
    {
        $stats = $this->itemRepo->getDailyStats($cafeId);

        return [
            'pending' => (int) ($stats['pending'] ?? 0),
            'in_progress' => (int) ($stats['in_progress'] ?? 0),
            'ready' => (int) ($stats['ready'] ?? 0),
            'served' => (int) ($stats['served'] ?? 0),
            'avg_prep_time' => \round((float) ($stats['avg_prep_time'] ?? 0), 1),
        ];
    }

    #[Override]
    public function getEstimatedWaitTime(int $cafeId): int
    {
        return $this->itemRepo->getEstimatedWaitTime($cafeId);
    }

    #[Override]
    public function getCompletedToday(int $cafeId): array
    {
        return $this->enrichItems(
            $this->itemRepo->findCompletedToday($cafeId)
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function enrichItems(array $items): array
    {
        foreach ($items as &$item) {
            if (isset($item['ingredients_list']) && \is_string($item['ingredients_list'])) {
                $item['ingredients_list'] = \json_decode($item['ingredients_list'], true) ?? [];
            }

            if (isset($item['allergen_data']) && \is_string($item['allergen_data']) && $item['allergen_data'] !== '') {
                $item['allergens'] = \array_map(static function (string $entry): array {
                    $parts = \explode('|', $entry) + ['', '', '#ccc', 'medium'];
                    return [
                        'code'     => $parts[0],
                        'name'     => $parts[1],
                        'color'    => $parts[2],
                        'severity' => $parts[3],
                    ];
                }, \explode(';;', $item['allergen_data']));
            } else {
                $item['allergens'] = [];
            }
            unset($item['allergen_data']);

            if (isset($item['created_at'])) {
                $created = \strtotime($item['created_at']);
                $item['waiting_minutes'] = (int) \floor((\time() - $created) / 60);
                $item['is_delayed'] = $item['waiting_minutes'] > ($item['prep_time'] ?? 10);
            }

            if (empty($item['station'])) {
                $item['station'] = Product::STATION_ASSEMBLY;
            }
        }

        return $items;
    }
}
