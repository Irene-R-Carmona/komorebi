<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CookieManager;
use App\Services\Contracts\RecentlyViewedServiceInterface;
use Override;

/**
 * Servicio para gestionar el historial de cafés vistos recientemente
 * Almacena hasta 10 cafés en orden FIFO (primero en entrar, primero en salir)
 */
final class RecentlyViewedService implements RecentlyViewedServiceInterface
{
    private const MAX_ITEMS = 10;
    private const COOKIE_DURATION = 30 * 24 * 3600; // 30 días

    /**
     * Añade un café al historial de vistos recientemente
     * Si ya existe, lo mueve al principio. Si se excede el límite, elimina el más antiguo.
     *
     * @param integer $cafeId ID del café visitado
     *
     * @return boolean True si se guardó correctamente
     */
    #[Override]
    public function add(int $cafeId): bool
    {
        $viewed = $this->getAll();

        // Eliminar el café si ya existe (para moverlo al principio)
        $viewed = \array_filter($viewed, static fn ($id) => $id !== $cafeId);

        // Añadir al principio
        \array_unshift($viewed, $cafeId);

        // Limitar a MAX_ITEMS
        if (\count($viewed) > self::MAX_ITEMS) {
            $viewed = \array_slice($viewed, 0, self::MAX_ITEMS);
        }

        return CookieManager::set(
            CookieManager::RECENTLY_VIEWED,
            \json_encode($viewed),
            self::COOKIE_DURATION
        );
    }

    /**
     * Obtiene la lista de IDs de cafés vistos recientemente
     *
     * @return array<int> Array de IDs ordenados por recencia (más reciente primero)
     */
    #[Override]
    public function getAll(): array
    {
        $cookie = CookieManager::get(CookieManager::RECENTLY_VIEWED);

        if ($cookie === null) {
            return [];
        }

        $decoded = \json_decode((string) $cookie, true);

        if (!\is_array($decoded)) {
            return [];
        }

        // Asegurar que todos son enteros
        return \array_map('intval', $decoded);
    }

    /**
     * Elimina el historial de productos vistos recientemente.
     *
     * @return boolean True si se eliminó correctamente
     */
    #[Override]
    public function clear(): bool
    {
        return CookieManager::delete(CookieManager::RECENTLY_VIEWED);
    }

    /**
     * Obtiene el número máximo de elementos que se pueden almacenar
     *
     * @return integer
     */
    #[Override]
    public function getMaxItems(): int
    {
        return self::MAX_ITEMS;
    }
}
