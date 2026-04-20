<?php

/**
 * ¿Qué pruebas aquí?
 * Smoke tests de Manager\ProductController: verifica métodos esperados e instanciación.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador expone index(), toggleAvailability(), create(), update(), delete().
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombra alguno de los métodos de gestión de productos del manager.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Manager\ProductController;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Contracts\ProductServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ProductController::class)]
final class ProductControllerTest extends ControllerTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ProductController::class, 'index'));
        $this->assertTrue(\method_exists(ProductController::class, 'create'));
        $this->assertTrue(\method_exists(ProductController::class, 'update'));
        $this->assertTrue(\method_exists(ProductController::class, 'delete'));
        $this->assertTrue(\method_exists(ProductController::class, 'toggleAvailability'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_instance_can_be_created_with_response_factory(): void
    {
        $controller = new ProductController(
            productService: $this->createStub(ProductServiceInterface::class),
            productRepo: $this->createStub(ProductRepositoryInterface::class),
            categoryRepo: $this->createStub(MenuCategoryRepositoryInterface::class),
            response: new ResponseFactory(),
        );
        $this->assertInstanceOf(ProductController::class, $controller);
    }
}
