<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Domain\DTO\MenuDTO;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Services\Contracts\MenuServiceInterface;
use Override;

/**
 * Servicio de Menú
 *
 * Orquesta la obtención y formateado del menú público.
 * Responsabilidades:
 * - Obtener categorías de menú ordenadas
 * - Obtener productos disponibles agrupados por categoría
 * - Decodificar JSON de alérgenos y atributos de forma segura
 * - Preparar datos para renderizado en vista
 */
final class MenuService extends BaseService implements MenuServiceInterface
{
    private MenuRepositoryInterface $menuRepository;

    public function __construct(MenuRepositoryInterface $menuRepository)
    {
        $this->menuRepository = $menuRepository;
    }

    // ─────────────────────────────────────────────────────────────
    // Obtención de datos
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todas las categorías de menú ordenadas por display_order.
     * Filtra "experiencias" del menú público (se muestran en detalle de café).
     *
     * @param bool $includeExperiences Si incluir categoría "experiencias" (por defecto false)
     * @return array<int, array{id: int, name: string, slug: string, display_order: int}>
     */
    #[Override]
    public function getCategories(bool $includeExperiences = false): array
    {
        return $this->menuRepository->getCategories($includeExperiences);
    }

    /**
     * Obtiene productos disponibles (items solo, no pases) agrupados por categoría.
     * Usa LEFT JOIN para cargar alérgenos en una sola query (elimina N+1)
     *
     * @param array<int> $excludeAllergens IDs de alérgenos a excluir
     * @return array<int, array<int, array<string, mixed>>>
     */
    #[Override]
    public function getProductsByCategory(array $excludeAllergens = []): array
    {
        $products = $this->menuRepository->getProductsByCategory($excludeAllergens);

        // Agrupar DTOs por category_id; allergens ya parseados en MenuDTO::fromArray()
        $grouped = [];
        foreach ($products as $product) {
            $grouped[$product->category_id][] = $product->toViewArray();
        }

        return $grouped;
    }

    /**
     * Obtiene todos los productos disponibles sin agrupar.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getAllProducts(): array
    {
        $dtos = $this->menuRepository->getAllProducts();

        return \array_map(static fn (MenuDTO $dto): array => $dto->toViewArray(), $dtos);
    }

    /**
     * Obtiene pases disponibles.
     */
    #[Override]
    public function getPasses(): array
    {
        return $this->menuRepository->getPasses();
    }

    /**
     * Obtiene pases compatibles con un café específico.
     * Filtra por categoría del café Y tipo de animal.
     * Pases sin targets son genéricos (Pase Komorebi).
     *
     * @param string|null $cafeCategory Categoría del café
     * @param string|null $animalType   Tipo de animal del café
     * @return array<int, array>
     */
    #[Override]
    public function getPassesForCafe(?string $cafeCategory = null, ?string $animalType = null): array
    {
        return $this->menuRepository->getPassesForCafe($cafeCategory, $animalType);
    }

    // ─────────────────────────────────────────────────────────────
    // Datos completos para renderizado
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los datos necesarios para renderizar el menú.
     *
     * @return array{
     *     categorias: array<int, array>,
     *     productos: array<int, array>,
     *     pases: array<int, array>,
     *     allergens: array<int, array>,
     *     cafeTypes: array<int, array>
     * }
     */
    #[Override]
    public function getMenuForView(array $excludeAllergens = []): array
    {
        return [
            'categorias' => $this->getCategories(false), // Excluir experiencias del menú público
            'productos' => $this->getProductsByCategory($excludeAllergens),
            'pases' => $this->getPasses(),
            'allergens' => $this->getAllergens(),
            // Añadir tipos de café para filtrado (coinciden con Cafe::CATEGORY_*)
            'cafeTypes' => [
                ['value' => 'lounge', 'label' => 'Cat Lounge', 'icon' => '🐱'],
                ['value' => 'playroom', 'label' => 'Cat Playroom', 'icon' => '🐾'],
                ['value' => 'farm', 'label' => 'Mini Farm', 'icon' => '🐰'],
                ['value' => 'zen', 'label' => 'Zen Garden', 'icon' => '🌿'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los alérgenos disponibles.
     *
     * @return array<int, array{id: int, name: string, name_jp: string, icon: string, icon_color: string, severity: string}>
     */
    #[Override]
    public function getAllergens(): array
    {
        return $this->menuRepository->getAllergens();
    }
}
