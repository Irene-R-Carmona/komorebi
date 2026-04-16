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
use App\Services\Contracts\ProductServiceInterface;
use Tests\Support\ControllerTestCase;

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
        $productService = $this->createStub(ProductServiceInterface::class);
        $controller = new ProductController($productService, new ResponseFactory());
        $this->assertInstanceOf(ProductController::class, $controller);
    }
}
