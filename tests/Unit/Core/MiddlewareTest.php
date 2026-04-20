<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

use App\Core\Middleware;
use App\Core\Session;
use App\Exceptions\MiddlewareException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests para Middleware RBAC (can, role, auth)
 */
#[CoversClass(Middleware::class)]
final class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Asegurar que la sesión está iniciada y limpia para tests
        Session::start();
        $_SESSION = [];
        // Evita que el TTL check dispare fetchUserFromDb() en tests unitarios
        $_SESSION['_user_verified_at'] = time();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─────────────────────────────────────────────────────────────
    // Permisos (can)
    // ─────────────────────────────────────────────────────────────

    public function testCanWithPermissionPasses(): void
    {
        // Simular sesión con user_id, roles y cache de permisos
        $_SESSION['user_id'] = 1;
        $_SESSION['user_roles'] = ['user'];
        $_SESSION['user_permissions'] = [
            'user.profile.view' => true,
            'reservation.create' => true,
        ];

        // No debe lanzar excepción
        Middleware::can('reservation.create');

        $this->assertTrue(true);
    }

    public function testCanWithoutPermissionThrows(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_roles'] = ['user'];
        $_SESSION['user_permissions'] = [
            'user.profile.view' => true,
        ];

        $this->expectException(MiddlewareException::class);

        Middleware::can('admin.users.delete');
    }

    public function testCanWithWildcardPermissionPasses(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_roles'] = ['admin'];
        $_SESSION['user_permissions'] = ['*' => true]; // Admin bypasses permission check

        // Admin puede acceder a cualquier permiso
        Middleware::can('admin.users.delete');
        Middleware::can('reservation.cancel');

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // Autenticación (auth)
    // ─────────────────────────────────────────────────────────────

    public function testAuthWithAuthenticatedUserPasses(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_roles'] = ['user'];  // evita consulta a BD
        $_SESSION['user_permissions'] = [];         // sin permisos extra

        // No debe lanzar excepción
        Middleware::auth();

        $this->assertTrue(true);
    }

    public function testAuthWithGuestThrows(): void
    {
        // No hay user_id en sesión
        unset($_SESSION['user_id']);

        $this->expectException(MiddlewareException::class);

        Middleware::auth();
    }

    // ─────────────────────────────────────────────────────────────
    // Edge cases
    // ─────────────────────────────────────────────────────────────

    public function testCanWithEmptyPermissionsArrayThrows(): void
    {
        // Usar user_id sin permisos en la BD para evitar dependencia de datos reales
        $_SESSION['user_id'] = 999999;
        $_SESSION['user_roles'] = ['user'];
        $_SESSION['user_permissions'] = []; // Sin permisos

        $this->expectException(MiddlewareException::class);

        Middleware::can('reservation.create');
    }
}
