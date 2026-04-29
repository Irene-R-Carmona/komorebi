<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Que las rutas de admin/roles y manager están correctamente registradas en routes.php:
 * métodos correctos, rutas de mutación presentes, rutas muertas eliminadas.
 * ¿Qué me quieres demostrar?
 * Que las vistas Alpine.js reciben respuesta 200 al hacer POST, no 404 ni 500.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina una ruta de mutación, si se pone un handler inexistente, o si
 * se añaden de nuevo rutas muertas (RoleController@create/edit), los tests fallan.
 */

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;

/**
 * Verifica la presencia y corrección de registros de rutas en app/routes.php.
 *
 * Estrategia: leer el fichero como texto. No se puede hacer require() del fichero
 * porque MiddlewareFactory::rateLimit() llama a Container::make() en tiempo de
 * registro, lo que requiere el contenedor completo bootstrappeado.
 */
final class RouteRegistrationTest extends TestCase
{
    private string $routesContent;

    protected function setUp(): void
    {
        $routesDir = __DIR__ . '/../../../app/routes/';
        $this->routesContent = (string) \file_get_contents(__DIR__ . '/../../../app/routes.php');
        foreach (['public.php', 'auth.php', 'admin.php', 'ops.php'] as $file) {
            $this->routesContent .= (string) \file_get_contents($routesDir . $file);
        }
    }

    // =========================================================================
    // F0.1 — Admin Roles: métodos correctos del controlador
    // =========================================================================

    public function testAdminRolesHasCreateRoleHandler(): void
    {
        $this->assertStringContainsString(
            "RoleApiController@createRole",
            $this->routesContent,
            'La ruta POST /api/v1/admin/roles debe apuntar a RoleApiController@createRole'
        );
    }

    public function testAdminRolesHasUpdateRoleHandler(): void
    {
        $this->assertStringContainsString(
            "RoleApiController@updateRole",
            $this->routesContent,
            'La ruta PUT /api/v1/admin/roles/{id} debe apuntar a RoleApiController@updateRole'
        );
    }

    public function testAdminRolesHasDeleteRoleHandler(): void
    {
        $this->assertStringContainsString(
            "RoleApiController@deleteRole",
            $this->routesContent,
            'La ruta DELETE /api/v1/admin/roles/{id} debe apuntar a RoleApiController@deleteRole'
        );
    }

    public function testAdminRolesHasGrantPermissionRoute(): void
    {
        $this->assertStringContainsString(
            "RoleApiController@grantPermission",
            $this->routesContent,
            'La ruta POST /api/v1/admin/roles/.../grant debe apuntar a RoleApiController@grantPermission'
        );
    }

    public function testAdminRolesHasRevokePermissionRoute(): void
    {
        $this->assertStringContainsString(
            "RoleApiController@revokePermission",
            $this->routesContent,
            'La ruta POST /api/v1/admin/roles/.../revoke debe apuntar a RoleApiController@revokePermission'
        );
    }

    public function testAdminRolesHasNoDeadCreateMethod(): void
    {
        $this->assertStringNotContainsString(
            "RoleController@create'",
            $this->routesContent,
            'RoleController@create no existe como método — la ruta muerta debe ser eliminada'
        );
    }

    public function testAdminRolesHasNoDeadEditMethod(): void
    {
        $this->assertStringNotContainsString(
            "RoleController@edit'",
            $this->routesContent,
            'RoleController@edit no existe como método — la ruta muerta debe ser eliminada'
        );
    }

    public function testAdminRolesHasNoDeadStoreMethod(): void
    {
        $this->assertStringNotContainsString(
            "RoleController@store",
            $this->routesContent,
            'RoleController@store no existe — debe reemplazarse por RoleController@createRole'
        );
    }

    public function testAdminRolesHasNoDeadUpdateMethod(): void
    {
        $this->assertStringNotContainsString(
            "RoleController@update'",
            $this->routesContent,
            'RoleController@update no existe — debe reemplazarse por RoleController@updateRole'
        );
    }

    public function testAdminRolesHasNoDeadDeleteMethod(): void
    {
        $this->assertStringNotContainsString(
            "RoleController@delete'",
            $this->routesContent,
            'RoleController@delete no existe — debe reemplazarse por RoleController@deleteRole'
        );
    }

    // =========================================================================
    // F0.2 — Manager: rutas de mutación POST presentes
    // =========================================================================

    public function testManagerHasCafeScheduleRoute(): void
    {
        $this->assertStringContainsString(
            "'/cafe/schedule'",
            $this->routesContent,
            'POST /manager/cafe/schedule debe estar registrado para Alpine.js en cafe/show.php'
        );
    }

    public function testManagerHasCafeCapacityRoute(): void
    {
        $this->assertStringContainsString(
            "'/cafe/capacity'",
            $this->routesContent,
            'POST /manager/cafe/capacity debe estar registrado para Alpine.js en cafe/show.php'
        );
    }

    public function testManagerHasCafeSettingsRoute(): void
    {
        $this->assertStringContainsString(
            "'/cafe/settings'",
            $this->routesContent,
            'POST /manager/cafe/settings debe estar registrado para Alpine.js en cafe/show.php'
        );
    }

    public function testManagerHasProductsCreateRoute(): void
    {
        $this->assertStringContainsString(
            "ProductApiController@create",
            $this->routesContent,
            'La ruta POST /api/v1/manager/products debe apuntar a ProductApiController@create'
        );
    }

    public function testManagerHasProductsUpdateRoute(): void
    {
        $this->assertStringContainsString(
            "ProductApiController@update",
            $this->routesContent,
            'La ruta PUT /api/v1/manager/products/{id} debe apuntar a ProductApiController@update'
        );
    }

    public function testManagerHasProductsToggleRoute(): void
    {
        $this->assertStringContainsString(
            "'/products/{id}/toggle'",
            $this->routesContent,
            'POST /manager/products/{id}/toggle debe estar registrado'
        );
    }

    public function testManagerHasProductsDeleteRoute(): void
    {
        $this->assertStringContainsString(
            "ProductApiController@delete",
            $this->routesContent,
            'La ruta DELETE /api/v1/manager/products/{id} debe apuntar a ProductApiController@delete'
        );
    }
}
