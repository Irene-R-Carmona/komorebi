<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

/**
 * DTO de producto de menú (ítems activos del catálogo público).
 *
 * Encapsula un producto del menú incluyendo la lista de alérgenos
 * ya parseada desde los campos GROUP_CONCAT de la consulta SQL.
 *
 * @phpstan-type AllergenEntry array{id: int, name: string, icon: string, icon_color: string, severity: string}
 */
final readonly class MenuDTO implements DomainTransferObject
{
    /**
     * @param array<int, array{id: int, name: string, icon: string, icon_color: string, severity: string}> $allergens
     */
    public function __construct(
        public int     $id,
        public string  $name,
        public ?string $slug,
        public ?string $description,
        public float   $price,
        public int     $category_id,
        public string  $category_name,
        public string  $category_slug,
        public ?string $japanese_name,
        public string  $product_type,
        public bool    $is_active,
        public ?string $image_url,
        public ?int    $stock_quantity,
        public array   $allergens,
        public ?string $created_at,
        public array   $target_cafe_types,
        public array   $attrs,
    ) {
    }

    /**
     * Construye el DTO desde una fila cruda de la base de datos.
     *
     * Maneja el parseo de los campos GROUP_CONCAT de alérgenos
     * (allergen_ids, allergen_names, allergen_icons, allergen_colors, allergen_severities).
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $allergens = [];

        if (!empty($row['allergen_ids'])) {
            $ids = \explode(',', (string) $row['allergen_ids']);
            $names = !empty($row['allergen_names']) ? \explode(',', (string) $row['allergen_names']) : [];
            $icons = !empty($row['allergen_icons']) ? \explode(',', (string) $row['allergen_icons']) : [];
            $colors = !empty($row['allergen_colors']) ? \explode(',', (string) $row['allergen_colors']) : [];
            $severities = !empty($row['allergen_severities']) ? \explode(',', (string) $row['allergen_severities']) : [];

            foreach ($ids as $idx => $rawId) {
                $allergens[] = [
                    'id' => (int) $rawId,
                    'name' => $names[$idx] ?? '',
                    'icon' => $icons[$idx] ?? '',
                    'icon_color' => $colors[$idx] ?? '',
                    'severity' => $severities[$idx] ?? 'moderate',
                ];
            }
        }

        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            slug: isset($row['slug']) ? (string) $row['slug'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null,
            price: (float) ($row['price'] ?? 0),
            category_id: (int) ($row['category_id'] ?? 0),
            category_name: (string) ($row['category_name'] ?? ''),
            category_slug: (string) ($row['category_slug'] ?? ''),
            japanese_name: isset($row['japanese_name']) ? (string) $row['japanese_name'] : null,
            product_type: (string) ($row['product_type'] ?? 'item'),
            is_active: (bool) ($row['is_active'] ?? false),
            image_url: isset($row['image_url']) ? (string) $row['image_url'] : null,
            stock_quantity: isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : null,
            allergens: $allergens,
            created_at: isset($row['created_at']) ? (string) $row['created_at'] : null,
            target_cafe_types: \is_array($row['target_cafe_types'] ?? null)
                ? $row['target_cafe_types']
                : (\is_string($row['target_cafe_types'] ?? null) && $row['target_cafe_types'] !== ''
                    ? (\json_decode((string) $row['target_cafe_types'], true) ?? [])
                    : []),
            attrs: \is_array($row['attributes'] ?? null)
                ? $row['attributes']
                : (\is_string($row['attributes'] ?? null) && $row['attributes'] !== ''
                    ? (\json_decode((string) $row['attributes'], true) ?? [])
                    : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toViewArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'japanese_name' => $this->japanese_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'category_slug' => $this->category_slug,
            'product_type' => $this->product_type,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'stock_quantity' => $this->stock_quantity,
            'allergens_list' => $this->allergens,
            'created_at' => $this->created_at,
            'target_cafe_types' => $this->target_cafe_types,
            'attrs' => $this->attrs,
        ];
    }
}
