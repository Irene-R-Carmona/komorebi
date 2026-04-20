<?php

/**
 * ¿Qué pruebas aquí?
 * Smoke test de Admin\RoleController: verifica métodos RBAC esperados y construcción.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador expone index(), createRole(), updateRole() y los métodos de permisos,
 * y que acepta ResponseFactory inyectada.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se renombra o elimina alguno de los métodos públicos de gestión RBAC.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Admin\RoleController;
use App\Models\Permission;
use App\Models\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(RoleController::class)]
final class RoleControllerTest extends ControllerTestCase
{
    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(RoleController::class, 'index'));
        $this->assertTrue(\method_exists(RoleController::class, 'createRole'));
        $this->assertTrue(\method_exists(RoleController::class, 'updateRole'));
        $this->assertTrue(\method_exists(RoleController::class, 'deleteRole'));
        $this->assertTrue(\method_exists(RoleController::class, 'grantPermission'));
        $this->assertTrue(\method_exists(RoleController::class, 'revokePermission'));
        $this->assertTrue(\method_exists(RoleController::class, 'getPermissions'));
    }

    public function test_instance_can_be_created_with_response_factory(): void
    {
        $controller = new RoleController(
            response: new ResponseFactory(),
            roleModel: new Role(),
            permissionModel: new Permission(),
        );
        $this->assertInstanceOf(RoleController::class, $controller);
    }

    public function test_instance_can_be_created_without_arguments(): void
    {
        $controller = new RoleController(
            roleModel: new Role(),
            permissionModel: new Permission(),
        );
        $this->assertInstanceOf(RoleController::class, $controller);
    }
}
