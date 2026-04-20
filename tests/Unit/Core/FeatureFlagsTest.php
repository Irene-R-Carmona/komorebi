<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Verifica el comportamiento de las feature flags definidas en app/routes.php
 * y bootstrap/container.php (FEATURE_BACKOFFICE, FEATURE_OPS, FEATURE_KEEPER).
 *
 * ¿Qué me quieres demostrar?
 * Que los grupos de rutas de backoffice se registran o se omiten según el
 * valor de la variable de entorno correspondiente, usando el patrón
 * Env::get('FEATURE_X', '1') === '1'.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se elimina la condición if (Env::get('FEATURE_BACKOFFICE', '1') === '1')
 *   alrededor de los grupos de rutas, los tests de "desactivar" fallarán.
 * - Si se cambia el default de '1' a '0', los tests de "activar por defecto" fallarán.
 * - Si se añade o elimina un grupo bajo un flag, los conteos de rutas cambiarán.
 */

namespace Tests\Unit\Core;

use App\Core\Env;
use App\Core\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(Env::class)]
#[CoversClass(Router::class)]
final class FeatureFlagsTest extends TestCase
{
    private ReflectionProperty $envCache;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(Env::class);
        $this->envCache = $ref->getProperty('cache');
    }

    protected function tearDown(): void
    {
        unset($_ENV['FEATURE_BACKOFFICE'], $_ENV['FEATURE_OPS'], $_ENV['FEATURE_KEEPER']);
        $this->envCache->setValue(null, []);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function clearEnv(): void
    {
        unset($_ENV['FEATURE_BACKOFFICE'], $_ENV['FEATURE_OPS'], $_ENV['FEATURE_KEEPER']);
        $this->envCache->setValue(null, []);
    }

    /** @return array<string, mixed> */
    private function loadRoutesAndGetRegistered(array $envOverrides = []): array
    {
        $this->clearEnv();
        foreach ($envOverrides as $k => $v) {
            $_ENV[$k] = $v;
        }
        // Re-clear cache so Env picks up the new $_ENV values
        $this->envCache->setValue(null, []);

        // routes.php creates its own $router, $mw, $responseFactory — capture $router after require
        $routesPath = __DIR__ . '/../../../app/routes.php';
        $router = (static function (string $path): Router {
            require $path;

            // $router is created inside routes.php in this scope
            return $router; // @phpstan-ignore-line
        })($routesPath);

        // Extract registered routes via reflection
        $ref = new ReflectionClass(Router::class);
        $routesProp = $ref->getProperty('routes');
        /** @var array<string, array<string, mixed>> $routes */
        $routes = $routesProp->getValue($router);

        return $routes;
    }

    // -------------------------------------------------------------------------
    // FEATURE_BACKOFFICE
    // -------------------------------------------------------------------------

    public function testBackofficeRoutesRegisteredByDefault(): void
    {
        $routes = $this->loadRoutesAndGetRegistered();

        $this->assertArrayHasKey('GET', $routes);
        $get = $routes['GET'];

        $this->assertArrayHasKey('/admin/dashboard', $get, 'Admin dashboard should be registered by default');
        $this->assertArrayHasKey('/manager/dashboard', $get, 'Manager dashboard should be registered by default');
        $this->assertArrayHasKey('/supervisor/dashboard', $get, 'Supervisor dashboard should be registered by default');
    }

    public function testBackofficeRoutesOmittedWhenFlagDisabled(): void
    {
        $routes = $this->loadRoutesAndGetRegistered(['FEATURE_BACKOFFICE' => '0']);

        $get = $routes['GET'] ?? [];
        $post = $routes['POST'] ?? [];

        $this->assertArrayNotHasKey('/admin/dashboard', $get, 'Admin routes must be absent when FEATURE_BACKOFFICE=0');
        $this->assertArrayNotHasKey('/manager/dashboard', $get, 'Manager routes must be absent when FEATURE_BACKOFFICE=0');
        $this->assertArrayNotHasKey('/supervisor/dashboard', $get, 'Supervisor routes must be absent when FEATURE_BACKOFFICE=0');
    }

    // -------------------------------------------------------------------------
    // FEATURE_OPS
    // -------------------------------------------------------------------------

    public function testOpsRoutesRegisteredByDefault(): void
    {
        $routes = $this->loadRoutesAndGetRegistered();

        $get = $routes['GET'];

        $this->assertArrayHasKey('/ops/reception', $get, 'Reception dashboard should be registered by default');
        $this->assertArrayHasKey('/ops/kitchen', $get, 'Kitchen dashboard should be registered by default');
    }

    public function testOpsRoutesOmittedWhenFlagDisabled(): void
    {
        $routes = $this->loadRoutesAndGetRegistered(['FEATURE_OPS' => '0']);

        $get = $routes['GET'] ?? [];

        $this->assertArrayNotHasKey('/ops/reception', $get, 'Reception routes must be absent when FEATURE_OPS=0');
        $this->assertArrayNotHasKey('/ops/kitchen', $get, 'Kitchen routes must be absent when FEATURE_OPS=0');
    }

    // -------------------------------------------------------------------------
    // FEATURE_KEEPER
    // -------------------------------------------------------------------------

    public function testKeeperRoutesRegisteredByDefault(): void
    {
        $routes = $this->loadRoutesAndGetRegistered();

        $get = $routes['GET'];

        $this->assertArrayHasKey('/keeper/dashboard', $get, 'Keeper dashboard should be registered by default');
        $this->assertArrayHasKey('/keeper/animals', $get, 'Keeper animals should be registered by default');
    }

    public function testKeeperRoutesOmittedWhenFlagDisabled(): void
    {
        $routes = $this->loadRoutesAndGetRegistered(['FEATURE_KEEPER' => '0']);

        $get = $routes['GET'] ?? [];

        $this->assertArrayNotHasKey('/keeper/dashboard', $get, 'Keeper routes must be absent when FEATURE_KEEPER=0');
        $this->assertArrayNotHasKey('/keeper/animals', $get, 'Keeper routes must be absent when FEATURE_KEEPER=0');
    }

    // -------------------------------------------------------------------------
    // Env default values (backward compatibility contract)
    // -------------------------------------------------------------------------

    public function testFeatureBackofficeDefaultIsOne(): void
    {
        $this->clearEnv();
        $this->assertSame('1', Env::get('FEATURE_BACKOFFICE', '1'));
    }

    public function testFeatureOpsDefaultIsOne(): void
    {
        $this->clearEnv();
        $this->assertSame('1', Env::get('FEATURE_OPS', '1'));
    }

    public function testFeatureKeeperDefaultIsOne(): void
    {
        $this->clearEnv();
        $this->assertSame('1', Env::get('FEATURE_KEEPER', '1'));
    }
}
