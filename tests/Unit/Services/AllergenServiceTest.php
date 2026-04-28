<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Todos los métodos públicos de AllergenService: caminos de validación fallida
 * (getByProduct, getProductIds, create, update, attachToProduct, detachFromProduct)
 * y caminos de éxito que delegan en AllergenRepositoryInterface (listAll, getById,
 * getByName, getStatistics y los métodos con guarda).
 *
 * ¿Qué me quieres demostrar?
 * Que las guardas de validación devuelven Result::fail antes de tocar el repositorio,
 * y que los caminos de éxito propagan correctamente lo que devuelve el repositorio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina o relaja alguna guarda de validación, si cambia el código de error
 * 'validation_error', o si algún método deja de delegar en el repositorio.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\AllergenDTO;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use App\Services\AllergenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllergenService::class)]
final class AllergenServiceTest extends TestCase
{
    private AllergenService $service;

    protected function setUp(): void
    {
        $this->service = new AllergenService(
            $this->createStub(AllergenRepositoryInterface::class)
        );
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

    // ──────────────────────────────────────────────
    // listAll — delegación sin validación
    // ──────────────────────────────────────────────

    public function testListAllReturnsDelegatedArray(): void
    {
        $expected = [['id' => 1, 'name' => 'Gluten'], ['id' => 2, 'name' => 'Lactosa']];

        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('findAll')->willReturn($expected);

        $service = new AllergenService($repo);

        $this->assertSame($expected, $service->listAll());
    }

    // ──────────────────────────────────────────────
    // getById — delegación sin validación
    // ──────────────────────────────────────────────

    public function testGetByIdReturnsDelegatedResult(): void
    {
        $dto = new AllergenDTO(5, 'CACAHUETE', 'Cacahuete', null, null, null, 'medium', null);

        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('findById')->willReturn($dto);

        $service = new AllergenService($repo);

        $this->assertSame($dto, $service->getById(5));
    }

    // ──────────────────────────────────────────────
    // getByName — delegación sin validación
    // ──────────────────────────────────────────────

    public function testGetByNameReturnsDelegatedResult(): void
    {
        $allergen = ['id' => 3, 'name' => 'Soja'];

        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('findByName')->willReturn($allergen);

        $service = new AllergenService($repo);

        $this->assertSame($allergen, $service->getByName('Soja'));
    }

    // ──────────────────────────────────────────────
    // getByProduct — camino de éxito
    // ──────────────────────────────────────────────

    public function testGetByProductWithValidIdReturnsOkResult(): void
    {
        $expected = [['id' => 1, 'name' => 'Gluten']];

        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('findByProduct')->willReturn($expected);

        $service = new AllergenService($repo);
        $result = $service->getByProduct(5);

        $this->assertTrue($result->ok);
        $this->assertSame($expected, $result->data);
    }

    // ──────────────────────────────────────────────
    // getProductIds — camino de éxito
    // ──────────────────────────────────────────────

    public function testGetProductIdsWithValidAllergenIdReturnsOkResult(): void
    {
        $expected = [10, 15, 22];

        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('getProductIds')->willReturn($expected);

        $service = new AllergenService($repo);
        $result = $service->getProductIds(2);

        $this->assertTrue($result->ok);
        $this->assertSame($expected, $result->data);
    }

    // ──────────────────────────────────────────────
    // getStatistics — delegación sin validación
    // ──────────────────────────────────────────────

    public function testGetStatisticsReturnsDelegatedArray(): void
    {
        $expected = [['allergen_id' => 1, 'product_count' => 7]];

        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('getStatistics')->willReturn($expected);

        $service = new AllergenService($repo);

        $this->assertSame($expected, $service->getStatistics());
    }

    // ──────────────────────────────────────────────
    // create — camino de éxito
    // ──────────────────────────────────────────────

    public function testCreateWithValidNameReturnsOkResult(): void
    {
        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('create')->willReturn(8);

        $service = new AllergenService($repo);
        $result = $service->create(['name' => 'Mostaza']);

        $this->assertTrue($result->ok);
        $this->assertSame(8, $result->data);
    }

    // ──────────────────────────────────────────────
    // update — camino de éxito
    // ──────────────────────────────────────────────

    public function testUpdateWithValidIdReturnsOkResult(): void
    {
        $repo = $this->createStub(AllergenRepositoryInterface::class);
        $repo->method('update')->willReturn(true);

        $service = new AllergenService($repo);
        $result = $service->update(3, ['name' => 'Soja actualizada']);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    // ──────────────────────────────────────────────
    // attachToProduct — camino de éxito
    // ──────────────────────────────────────────────

    public function testAttachToProductWithValidIdsReturnsOkResult(): void
    {
        $repo = $this->createMock(AllergenRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('attachToProduct')
            ->with(5, 2, null)
            ->willReturn(true);

        $service = new AllergenService($repo);
        $result = $service->attachToProduct(5, 2);

        $this->assertTrue($result->ok);
    }

    // ──────────────────────────────────────────────
    // detachFromProduct — camino de éxito
    // ──────────────────────────────────────────────

    public function testDetachFromProductWithValidIdsReturnsOkResult(): void
    {
        $repo = $this->createMock(AllergenRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('detachFromProduct')
            ->with(5, 2)
            ->willReturn(true);

        $service = new AllergenService($repo);
        $result = $service->detachFromProduct(5, 2);

        $this->assertTrue($result->ok);
    }
}
