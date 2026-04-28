<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La clase value object Pagination.
 *
 * ¿Qué me quieres demostrar?
 * Que Pagination calcula correctamente offset, hasNextPage, y expose
 * sus propiedades de forma coherente con los parámetros de entrada.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la fórmula del offset, los límites de validación,
 * o la forma de calcular si hay página siguiente.
 */

namespace Tests\Unit\Core;

use App\Core\Pagination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pagination::class)]
final class PaginationTest extends TestCase
{
    public function test_offset_is_zero_on_first_page(): void
    {
        $p = Pagination::fromRequest(1, 10);

        $this->assertSame(0, $p->offset);
    }

    public function test_offset_on_second_page(): void
    {
        $p = Pagination::fromRequest(2, 10);

        $this->assertSame(10, $p->offset);
    }

    public function test_offset_on_third_page_with_custom_limit(): void
    {
        $p = Pagination::fromRequest(3, 25);

        $this->assertSame(50, $p->offset);
    }

    public function test_limit_is_bounded_to_max(): void
    {
        $p = Pagination::fromRequest(1, 999);

        $this->assertLessThanOrEqual(Pagination::MAX_LIMIT, $p->limit);
    }

    public function test_limit_minimum_is_one(): void
    {
        $p = Pagination::fromRequest(1, 0);

        $this->assertGreaterThanOrEqual(1, $p->limit);
    }

    public function test_page_minimum_is_one(): void
    {
        $p = Pagination::fromRequest(0, 10);

        $this->assertSame(1, $p->page);
        $this->assertSame(0, $p->offset);
    }

    public function test_has_next_page_false_when_items_equal_limit(): void
    {
        $p = Pagination::fromRequest(1, 10);

        // Si se recuperaron exactamente "limit" items, no hay siguiente
        $this->assertFalse($p->hasNextPage(10));
    }

    public function test_has_next_page_true_when_items_exceed_limit(): void
    {
        $p = Pagination::fromRequest(1, 10);

        // Si se recuperaron limit+1 items (sentinel row), hay siguiente página
        $this->assertTrue($p->hasNextPage(11));
    }

    public function test_to_meta_array_contains_required_keys(): void
    {
        $p = Pagination::fromRequest(2, 15);
        $meta = $p->toMeta(false);

        $this->assertArrayHasKey('page', $meta);
        $this->assertArrayHasKey('limit', $meta);
        $this->assertArrayHasKey('has_next_page', $meta);
        $this->assertSame(2, $meta['page']);
        $this->assertSame(15, $meta['limit']);
        $this->assertFalse($meta['has_next_page']);
    }

    public function test_fetch_limit_is_limit_plus_one(): void
    {
        // Para detectar si hay página siguiente, pedimos un item extra
        $p = Pagination::fromRequest(1, 10);

        $this->assertSame(11, $p->fetchLimit);
    }
}
