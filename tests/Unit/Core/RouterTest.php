<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace {
    final class SingleParamController
    {
        public function show(int $id): array
        {
            return ['id' => $id];
        }
    }

    final class ArrayParamController
    {
        public function show(array $params): array
        {
            return $params;
        }
    }

    final class MismatchController
    {
        // Firma int $id, string $category (orden distinta a la ruta)
        public function handle(int $id, string $category): array
        {
            return ['id' => $id, 'category' => $category];
        }
    }
}

namespace {

    use App\Core\Router;
    use App\Exceptions\MiddlewareException;
    use App\Exceptions\RouterException;
    use App\Exceptions\RouterParameterException;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;

    #[CoversClass(Router::class)]
    final class RouterTest extends TestCase
    {
        private Router $router;

        protected function setUp(): void
        {
            $this->router = new Router();
            // Usar el namespace donde declaramos los fixtures
            $this->router->setControllerNamespace('Tests\\RouterFixtures');
        }

        /**
         * @throws RouterParameterException
         * @throws MiddlewareException
         * @throws RouterException
         * @throws ReflectionException
         */
        public function testSingleParamIsPassedAsInt(): void
        {
            $this->router->get('/items/{id}', 'SingleParamController@show');

            $result = $this->router->dispatch('/items/123', 'GET');

            $this->assertIsArray($result);
            $this->assertSame(123, $result['id']);
            $this->assertIsInt($result['id']);
        }

        /**
         * @throws RouterParameterException
         * @throws MiddlewareException
         * @throws ReflectionException
         * @throws RouterException
         */
        public function testArrayParamControllerReceivesAssocArray(): void
        {
            $this->router->get('/cafes/{slug}', 'ArrayParamController@show');

            $result = $this->router->dispatch('/cafes/my-slug', 'GET');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('slug', $result);
            $this->assertSame('my-slug', $result['slug']);
        }

        /**
         * @throws RouterParameterException
         * @throws MiddlewareException
         * @throws ReflectionException
         * @throws RouterException
         */
        public function testMismatchRouteMapsByNameToMethodSignature(): void
        {
            // Route has category first, id second. Method expects (int $id, string $category)
            $this->router->get('/mismatch/{category}/{id}', 'MismatchController@handle');

            $result = $this->router->dispatch('/mismatch/coffee/42', 'GET');

            $this->assertIsArray($result);
            $this->assertSame(42, $result['id']);
            $this->assertSame('coffee', $result['category']);
        }
    }
}
