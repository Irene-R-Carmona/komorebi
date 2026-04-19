<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Métodos de validación de AllergenService: getByProduct, getProductIds,
 * create, update, attachToProduct y detachFromProduct con entradas inválidas.
 *
 * ¿Qué me quieres demostrar?
 * Que las guardas de validación devuelven Result::fail antes de tocar el modelo,
 * protegiendo la integridad de datos contra IDs negativos o nombres vacíos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina o relaja alguna guarda de validación (ej. se permite id <= 0),
 * o si se cambia el código de error de 'validation_error' a otro valor.
 */

namespace Tests\Unit\Services;

use App\Services\AllergenService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AllergenService::class)]
final class AllergenServiceTest extends TestCase
{
    private AllergenService $service;

    protected function setUp(): void
    {
        // Pasamos null → el constructor creará el modelo (DB disponible)
        // Pero todos los tests que aquí validamos retornan ANTES de tocar la DB
        $this->service = new AllergenService();
    }

    // ──────────────────────────────────────────────
    // getByProduct — validación de ID
    // ──────────────────────────────────────────────

    public function testGetByProductWithZeroIdReturnsFail(): void
    {
        $result = $this->service->getByProduct(0);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testGetByProductWithNegativeIdReturnsFail(): void
    {
        $result = $this->service->getByProduct(-5);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // getProductIds — validación de ID
    // ──────────────────────────────────────────────

    public function testGetProductIdsWithZeroIdReturnsFail(): void
    {
        $result = $this->service->getProductIds(0);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testGetProductIdsWithNegativeIdReturnsFail(): void
    {
        $result = $this->service->getProductIds(-1);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // create — validación de nombre
    // ──────────────────────────────────────────────

    public function testCreateWithEmptyNameReturnsFail(): void
    {
        $result = $this->service->create(['name' => '']);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateWithNoNameKeyReturnsFail(): void
    {
        $result = $this->service->create([]);

        $this->assertFalse($result->ok);
    }

    public function testCreateWithWhitespaceOnlyNameReturnsFail(): void
    {
        $result = $this->service->create(['name' => '   ']);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // update — validación de ID
    // ──────────────────────────────────────────────

    public function testUpdateWithZeroIdReturnsFail(): void
    {
        $result = $this->service->update(0, ['name' => 'Cacahuete']);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    // ──────────────────────────────────────────────
    // attachToProduct / detachFromProduct
    // ──────────────────────────────────────────────

    public function testAttachToProductWithInvalidProductIdReturnsFail(): void
    {
        $result = $this->service->attachToProduct(0, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testAttachToProductWithInvalidAllergenIdReturnsFail(): void
    {
        $result = $this->service->attachToProduct(1, 0);

        $this->assertFalse($result->ok);
    }

    public function testDetachFromProductWithInvalidIdsReturnsFail(): void
    {
        $result = $this->service->detachFromProduct(-1, 1);

        $this->assertFalse($result->ok);
    }
}
