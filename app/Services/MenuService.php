<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Database;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\MenuRepository;
use JsonException;
use PDO;

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
final class MenuService extends BaseService
{
    private PDO $db;
    private MenuRepositoryInterface $menuRepository;

    public function __construct(?PDO $db = null, ?MenuRepositoryInterface $menuRepository = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->menuRepository = $menuRepository ?? new MenuRepository($this->db);
    }

    /**
     * Comprueba si una columna existe en la tabla (soporta SQLite y MySQL)
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $this->db->prepare("PRAGMA table_info($table)");
                $stmt->execute();
                $cols = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($cols as $c) {
                    if (isset($c['name']) && $c['name'] === $column) {
                        return true;
                    }
                }
                return false;
            }

            // MySQL / MariaDB
            if ($driver === 'mysql') {
                $stmt = $this->db->prepare(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :col AND TABLE_SCHEMA = DATABASE()'
                );
                $stmt->execute(['table' => $table, 'col' => $column]);
                return (bool) $stmt->fetch();
            }
        } catch (\Throwable $e) {
            // En caso de error, asumimos que no existe
            return false;
        }

        return false;
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
    public function getCategories(bool $includeExperiences = false): array
    {
        return $this->menuRepository->getCategories($includeExperiences);
    }

    /**
     * Obtiene productos disponibles (items solo, no pases) agrupados por categoría.
     * Usa LEFT JOIN para cargar alérgenos en una sola query (elimina N+1)
     */
    public function getProductsByCategory(array $excludeAllergens = []): array
    {
        $products = $this->menuRepository->getProductsByCategory($excludeAllergens);

        // Agrupar productos por category_id
        $grouped = [];
        foreach ($products as $product) {
            $categoryId = (int)$product['category_id'];
            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [];
            }

            // Parsear allergen_ids y allergen_names en allergens_list
            $allergensList = [];
            if (!empty($product['allergen_ids'])) {
                $ids = explode(',', $product['allergen_ids']);
                $names = explode(',', $product['allergen_names']);
                $icons = !empty($product['allergen_icons']) ? explode(',', $product['allergen_icons']) : [];
                $colors = !empty($product['allergen_colors']) ? explode(',', $product['allergen_colors']) : [];
                $severities = !empty($product['allergen_severities']) ? explode(',', $product['allergen_severities']) : [];

                foreach ($ids as $idx => $id) {
                    $allergensList[] = [
                        'id' => (int)$id,
                        'name' => $names[$idx] ?? '',
                        'icon' => $icons[$idx] ?? '',
                        'icon_color' => $colors[$idx] ?? '',
                        'severity' => $severities[$idx] ?? 'moderate'
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
    public function getAllProducts(): array
    {
        return $this->menuRepository->getAllProducts();
    }

    /**
     * Obtiene pases disponibles.
     */
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
    public function getPassesForCafe(?string $cafeCategory = null, ?string $animalType = null): array
    {
        return $this->menuRepository->getPassesForCafe($cafeCategory, $animalType);
    }

    /**
     * Verifica si un pase cumple con los criterios de café y animal
     */
    private function passMatchesCriteria(array $pass, ?string $cafeCategory, ?string $animalType): bool
    {
        $cafeTargets = $this->decodeJsonField($pass['target_cafe_types'] ?? null);
        $animalTargets = $this->decodeJsonField($pass['target_animal_types'] ?? null);

        // Si JSON inválido, descartar pase
        if ($cafeTargets === false || $animalTargets === false) {
            return false;
        }

        // Si tiene targets específicos, filtrar estrictamente
        if (!empty($cafeTargets) || !empty($animalTargets)) {
            return $this->targetsMatch($cafeTargets, $animalTargets, $cafeCategory, $animalType);
        }

        // Si no tiene targets, es genérico - incluir siempre
        return true;
    }

    /**
     * Decodifica campo JSON o retorna array si ya es array
     *
     * @return array|false Array decodificado o false si JSON inválido
     */
    private function decodeJsonField($field)
    {
        if (empty($field)) {
            return [];
        }

        if (\is_array($field)) {
            return $field;
        }

        if (\is_string($field)) {
            try {
                return \json_decode($field, true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (JsonException) {
                return false; // JSON inválido
            }
        }

        return [];
    }

    /**
     * Verifica si los targets específicos coinciden con los criterios
     */
    private function targetsMatch(
        array $cafeTargets,
        array $animalTargets,
        ?string $cafeCategory,
        ?string $animalType
    ): bool {
        // Verificar categoría del café si hay targets
        if (!empty($cafeTargets) && $cafeCategory !== null && !\in_array($cafeCategory, $cafeTargets, true)) {
            return false;
        }

        // Verificar tipo de animal si hay targets
        if (!empty($animalTargets) && $animalType !== null && !\in_array($animalType, $animalTargets, true)) {
            return false;
        }

        return true;
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
    public function getAllergens(): array
    {
        return $this->menuRepository->getAllergens();
    }
}
