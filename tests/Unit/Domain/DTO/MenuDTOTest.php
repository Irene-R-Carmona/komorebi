<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * MenuDTO: construcción desde array crudo de BD (fromArray) y serialización para vistas (toViewArray).
 *
 * ¿Qué me quieres demostrar?
 * Que fromArray parsea correctamente todos los campos, incluyendo los GROUP_CONCAT de alérgenos,
 * y que toViewArray devuelve el array esperado por las vistas.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambian las claves del array de retorno de toViewArray, si se pierde el parseo de alérgenos
 * en fromArray, o si cambia la lógica de valores por defecto.
 */

namespace Tests\Unit\Domain\DTO;

use App\Domain\DTO\MenuDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuDTO::class)]
final class MenuDTOTest extends TestCase
{
    /** @return array<string, mixed> */
    private function minimalRow(): array
    {
        return [
            'id' => 42,
            'name' => 'Matcha Latte',
            'slug' => 'matcha-latte',
            'description' => 'Té verde japonés con leche de avena',
            'price' => '4.50',
            'category_id' => 3,
            'category_name' => 'Bebidas calientes',
            'category_slug' => 'bebidas-calientes',
            'product_type' => 'drink',
            'is_active' => 1,
            'image_url' => '/images/matcha.jpg',
            'stock_quantity' => 20,
            'created_at' => '2025-06-01 10:00:00',
        ];
    }

    public function testFromArrayPopulatesScalarFields(): void
    {
        $dto = MenuDTO::fromArray($this->minimalRow());

        $this->assertSame(42, $dto->id);
        $this->assertSame('Matcha Latte', $dto->name);
        $this->assertSame('matcha-latte', $dto->slug);
        $this->assertSame('Té verde japonés con leche de avena', $dto->description);
        $this->assertSame(4.5, $dto->price);
        $this->assertSame(3, $dto->category_id);
        $this->assertSame('Bebidas calientes', $dto->category_name);
        $this->assertSame('bebidas-calientes', $dto->category_slug);
        $this->assertSame('drink', $dto->product_type);
        $this->assertTrue($dto->is_active);
        $this->assertSame('/images/matcha.jpg', $dto->image_url);
        $this->assertSame(20, $dto->stock_quantity);
        $this->assertSame('2025-06-01 10:00:00', $dto->created_at);
    }

    public function testFromArrayReturnsEmptyAllergensWhenNoAllergenIds(): void
    {
        $dto = MenuDTO::fromArray($this->minimalRow());

        $this->assertSame([], $dto->allergens);
    }

    public function testFromArrayParsesAllergensFromGroupConcat(): void
    {
        $row = $this->minimalRow() + [
            'allergen_ids' => '1,2',
            'allergen_names' => 'Lactosa,Gluten',
            'allergen_icons' => 'milk,wheat',
            'allergen_colors' => '#fff,#f00',
            'allergen_severities' => 'low,high',
        ];

        $dto = MenuDTO::fromArray($row);

        $this->assertCount(2, $dto->allergens);
        $this->assertSame(1, $dto->allergens[0]['id']);
        $this->assertSame('Lactosa', $dto->allergens[0]['name']);
        $this->assertSame('milk', $dto->allergens[0]['icon']);
        $this->assertSame('#fff', $dto->allergens[0]['icon_color']);
        $this->assertSame('low', $dto->allergens[0]['severity']);
        $this->assertSame(2, $dto->allergens[1]['id']);
        $this->assertSame('high', $dto->allergens[1]['severity']);
    }

    public function testFromArrayUsesDefaultsForMissingFields(): void
    {
        $dto = MenuDTO::fromArray([]);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->name);
        $this->assertSame(0.0, $dto->price);
        $this->assertSame('item', $dto->product_type);
        $this->assertFalse($dto->is_active);
        $this->assertNull($dto->slug);
        $this->assertNull($dto->description);
        $this->assertNull($dto->image_url);
        $this->assertNull($dto->stock_quantity);
        $this->assertNull($dto->created_at);
    }

    public function testToViewArrayContainsRequiredKeys(): void
    {
        $dto = MenuDTO::fromArray($this->minimalRow());
        $view = $dto->toViewArray();

        foreach (['id', 'name', 'slug', 'price', 'category_id', 'category_name', 'category_slug', 'product_type', 'is_active', 'image_url', 'stock_quantity', 'allergens_list', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $view, "toViewArray debe contener la clave '{$key}'");
        }
    }

    public function testToViewArrayReturnsAllergensListKey(): void
    {
        $row = $this->minimalRow() + [
            'allergen_ids' => '5',
            'allergen_names' => 'Soja',
            'allergen_icons' => 'soy',
            'allergen_colors' => '#green',
            'allergen_severities' => 'moderate',
        ];

        $view = MenuDTO::fromArray($row)->toViewArray();

        $this->assertArrayHasKey('allergens_list', $view);
        $this->assertCount(1, $view['allergens_list']);
        $this->assertSame('Soja', $view['allergens_list'][0]['name']);
    }
}
