<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Services\Contracts\MenuServiceInterface;

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
    #[\Override]
    public function getCategories(bool $includeExperiences = false): array
    {
        return $this->menuRepository->getCategories($includeExperiences);
    }

    /**
     * Obtiene productos disponibles (items solo, no pases) agrupados por categoría.
     * Usa LEFT JOIN para cargar alérgenos en una sola query (elimina N+1)
     */
    #[\Override]
    public function getProductsByCategory(array $excludeAllergens = []): array
    {
        $products = $this->menuRepository->getProductsByCategory($excludeAllergens);

        // Agrupar productos por category_id
        $grouped = [];
        foreach ($products as $product) {
            $categoryId = (int) $product['category_id'];
            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [];
            }

            // Parsear allergen_ids y allergen_names en allergens_list
            $allergensList = [];
            if (!empty($product['allergen_ids'])) {
                $ids = \explode(',', $product['allergen_ids']);
                $names = \explode(',', $product['allergen_names']);
                $icons = !empty($product['allergen_icons']) ? \explode(',', $product['allergen_icons']) : [];
                $colors = !empty($product['allergen_colors']) ? \explode(',', $product['allergen_colors']) : [];
                $severities = !empty($product['allergen_severities']) ? \explode(',', $product['allergen_severities']) : [];

                foreach ($ids as $idx => $id) {
                    $allergensList[] = [
                        'id' => (int) $id,
                        'name' => $names[$idx] ?? '',
                        'icon' => $icons[$idx] ?? '',
                        'icon_color' => $colors[$idx] ?? '',
                        'severity' => $severities[$idx] ?? 'moderate',
                    ];
                }
            }
            $product['allergens_list'] = $allergensList;

            $grouped[$categoryId][] = $product;
        }

        return $grouped;
    }

    /**
     * Obtiene todos los productos disponibles sin agrupar.
     *
     * @return array<int, array{id: int, category_id: int, name: string, japanese_name: string, description: string, price: int, image_url: string, is_active: int, product_type: string, allergens_list: array}>
     */
    #[\Override]
    public function getAllProducts(): array
    {
        return $this->menuRepository->getAllProducts();
    }

    /**
     * Obtiene pases disponibles.
     */
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
    public function getAllergens(): array
    {
        return $this->menuRepository->getAllergens();
    }
}
