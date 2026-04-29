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

    final class FloatParamController
    {
        public function get(float $amount): array
        {
            return ['amount' => $amount];
        }
    }

    final class BoolParamController
    {
        public function get(bool $active): array
        {
            return ['active' => $active];
        }
    }

    final class DefaultParamController
    {
        public function get(string $name = 'World'): string
        {
            return "Hello $name";
        }
    }

    final class NullableParamController
    {
        public function get(?string $value): string
        {
            return $value ?? 'was-null';
        }
    }

    final class RequestAwareController
    {
        public function get(\Psr\Http\Message\ServerRequestInterface $request): string
        {
            return 'request-received';
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

        public function testExactRouteMatchWithNoParams(): void
        {
            $this->router->get('/about', function ($req) { return 'about-page'; });

            $result = $this->router->dispatch('/about', 'GET');

            self::assertSame('about-page', $result);
        }

        public function testDispatchReturns404ResponseForUnknownRoute(): void
        {
            $result = $this->router->dispatch('/this-does-not-exist', 'GET');

            self::assertIsString($result);
            self::assertStringContainsString('404', $result);
        }

        public function testSetNotFoundHandlerUsesCustomHandler(): void
        {
            $this->router->setNotFoundHandler(function () { return 'custom-not-found'; });

            $result = $this->router->dispatch('/nonexistent', 'GET');

            self::assertSame('custom-not-found', $result);
        }

        public function testClosureHandlerCanReturnString(): void
        {
            $this->router->get('/ping', function ($req) { return 'pong'; });

            $result = $this->router->dispatch('/ping', 'GET');

            self::assertSame('pong', $result);
        }

        public function testClosureHandlerCanReturnNullGives204EmptyBody(): void
        {
            $this->router->get('/nothing', function ($req) { return null; });

            $result = $this->router->dispatch('/nothing', 'GET');

            self::assertSame('', $result);
        }

        public function testClosureHandlerCanReturnResponseInterfaceDirectly(): void
        {
            $mockBody = $this->createStub(\Psr\Http\Message\StreamInterface::class);
            $mockBody->method('__toString')->willReturn('direct-body');

            $mockResponse = $this->createStub(\Psr\Http\Message\ResponseInterface::class);
            $mockResponse->method('getBody')->willReturn($mockBody);
            $mockResponse->method('getHeaderLine')->willReturn('');

            $this->router->get('/direct', function ($req) use ($mockResponse) { return $mockResponse; });

            $result = $this->router->dispatch('/direct', 'GET');

            self::assertSame('direct-body', $result);
        }

        public function testClosureHandlerReturningInvalidTypeThrowsRouterException(): void
        {
            $this->router->get('/bad-type', function ($req) { return new \stdClass(); });

            $this->expectException(RouterException::class);
            $this->router->dispatch('/bad-type', 'GET');
        }

        public function testDispatchThrowsForUnknownController(): void
        {
            $this->router->get('/unknown', 'NonExistentControllerXYZ@method');

            $this->expectException(RouterException::class);
            $this->router->dispatch('/unknown', 'GET');
        }

        public function testDispatchThrowsForInvalidHandlerStringWithoutAt(): void
        {
            $this->router->get('/bad-handler', 'not-a-valid-handler');

            $this->expectException(RouterException::class);
            $this->router->dispatch('/bad-handler', 'GET');
        }

        public function testDispatchThrowsForMethodNotFoundOnController(): void
        {
            $this->router->get('/no-method', 'SingleParamController@nonExistentMethod');

            $this->expectException(RouterException::class);
            $this->router->dispatch('/no-method', 'GET');
        }

        public function testPostRouteDispatchesCorrectly(): void
        {
            $this->router->post('/create', function ($req) { return ['created' => true]; });

            $result = $this->router->dispatch('/create', 'POST');

            self::assertSame(['created' => true], $result);
        }

        public function testFloatParamIsCastCorrectly(): void
        {
            $this->router->get('/price/{amount}', 'FloatParamController@get');

            $result = $this->router->dispatch('/price/19.99', 'GET');

            self::assertIsArray($result);
            self::assertSame(19.99, $result['amount']);
        }

        public function testBoolParamIsCastCorrectly(): void
        {
            $this->router->get('/status/{active}', 'BoolParamController@get');

            $result = $this->router->dispatch('/status/true', 'GET');

            self::assertIsArray($result);
            self::assertTrue($result['active']);
        }

        public function testDefaultParamUsedWhenMissingFromRoute(): void
        {
            $this->router->get('/greet', 'DefaultParamController@get');

            $result = $this->router->dispatch('/greet', 'GET');

            self::assertSame('Hello World', $result);
        }

        public function testNullableParamIsNullWhenMissingFromRoute(): void
        {
            $this->router->get('/optional', 'NullableParamController@get');

            $result = $this->router->dispatch('/optional', 'GET');

            self::assertSame('was-null', $result);
        }

        public function testServerRequestInterfaceParamIsInjected(): void
        {
            $this->router->get('/request-test', 'RequestAwareController@get');

            $result = $this->router->dispatch('/request-test', 'GET');

            self::assertSame('request-received', $result);
        }

        public function testGroupPrefixIsPrependedToRoutes(): void
        {
            $this->router->group(['prefix' => '/api'], function (Router $r): void {
                $r->get('/users', function ($req) { return ['users' => []]; });
            });

            $result = $this->router->dispatch('/api/users', 'GET');

            self::assertSame(['users' => []], $result);
        }

        public function testNormalizePathStripsTrailingSlash(): void
        {
            $this->router->get('/about', function ($req) { return 'about-page'; });

            $result = $this->router->dispatch('/about/', 'GET');

            self::assertSame('about-page', $result);
        }
    }
}
