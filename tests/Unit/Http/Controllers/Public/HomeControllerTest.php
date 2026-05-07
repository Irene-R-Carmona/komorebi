<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Verifica que Public/HomeController puede cargarse correctamente.
 *
 * ¿Qué me quieres demostrar?
 * Que la clase existe, tiene el método index() y puede ser instanciada
 * sin errores de autoloading (el constructor no accede a la BD).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el método index() o se rompe el namespace/autoload.
 */

namespace Tests\Unit\Http\Controllers\Public;

use App\Http\Controllers\Public\HomeController;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\CafeCatalogRepositoryInterface;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HomeController::class)]
final class HomeControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(HomeController::class));
    }

    public function test_index_method_exists(): void
    {
        $this->assertTrue(\method_exists(HomeController::class, 'index'));
    }

    public function test_can_be_instantiated(): void
    {
        $controller = new HomeController(
            cafeRepo: $this->createStub(CafeCatalogRepositoryInterface::class),
            favoriteRepo: $this->createStub(FavoriteRepositoryInterface::class),
            animalRepo: $this->createStub(AnimalRepositoryInterface::class),
        );
        $this->assertInstanceOf(HomeController::class, $controller);
    }
}
