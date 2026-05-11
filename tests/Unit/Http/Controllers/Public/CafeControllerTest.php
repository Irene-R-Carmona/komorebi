<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato básico de Public/CafeController: instanciación sin DI container
 * y que show() lanza NotFoundException cuando el café no existe.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse con dependencias inyectadas
 * y que show() delega correctamente la excepción cuando findWithAnimals retorna null.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el check de null en show() o si se cambia la firma del constructor.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Exceptions\NotFoundException;
use App\Http\Controllers\Public\CafeController;
use App\Http\Transformers\AnimalTransformer;
use App\Http\Transformers\CafeTransformer;
use App\Models\Favorite;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\PassInclusionRepositoryInterface;
use App\Services\Contracts\MenuServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CafeController::class)]
final class CafeControllerTest extends TestCase
{
    private function makeController(): CafeController
    {
        return new CafeController(
            menuService: $this->createStub(MenuServiceInterface::class),
            queryService: $this->createStub(ReviewQueryServiceInterface::class),
            reviewService: $this->createStub(ReviewServiceInterface::class),
            cafeRepo: $this->createStub(CafeRepositoryInterface::class),
            favoriteModel: new Favorite($this->createStub(PDO::class)),
            cafeTransformer: new CafeTransformer(),
            animalTransformer: new AnimalTransformer(),
            passInclusionRepo: $this->createStub(PassInclusionRepositoryInterface::class),
        );
    }

    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(CafeController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(CafeController::class, 'index'));
        $this->assertTrue(\method_exists(CafeController::class, 'show'));
    }

    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CafeController::class, $this->makeController());
    }

    public function test_show_throws_not_found_exception_when_cafe_not_found(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $cafeRepo->method('findWithAnimals')->willReturn(null);

        $controller = new CafeController(
            menuService: $this->createStub(MenuServiceInterface::class),
            queryService: $this->createStub(ReviewQueryServiceInterface::class),
            reviewService: $this->createStub(ReviewServiceInterface::class),
            cafeRepo: $cafeRepo,
            favoriteModel: new Favorite($this->createStub(PDO::class)),
            cafeTransformer: new CafeTransformer(),
            animalTransformer: new AnimalTransformer(),
            passInclusionRepo: $this->createStub(PassInclusionRepositoryInterface::class),
        );

        $this->expectException(NotFoundException::class);

        $controller->show(new ServerRequest('GET', '/cafes/no-existe'), 'no-existe');
    }
}
