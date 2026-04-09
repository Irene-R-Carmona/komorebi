<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Role.
 * ¿Qué me quieres demostrar? Que Role solo acepta los roles definidos en el sistema.
 * ¿Qué va a fallar en este test si se cambia el código? Si se añade o elimina un rol sin actualizar el VO.
 */

use App\Core\ValueObjects\Role;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testAdminRoleIsAccepted(): void
    {
        $role = new Role('admin');
        $this->assertSame('admin', $role->getValue());
    }

    public function testManagerRoleIsAccepted(): void
    {
        $role = new Role('manager');
        $this->assertSame('manager', $role->getValue());
    }

    public function testUserRoleIsAccepted(): void
    {
        $role = new Role('user');
        $this->assertSame('user', $role->getValue());
    }

    public function testInvalidRoleThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Role('superadmin');
    }

    public function testEmptyRoleThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Role('');
    }

    public function testAllValidRolesAreAccessible(): void
    {
        $this->assertContains('admin', Role::VALID_ROLES);
        $this->assertContains('manager', Role::VALID_ROLES);
        $this->assertContains('user', Role::VALID_ROLES);
        $this->assertContains('reception', Role::VALID_ROLES);
        $this->assertContains('kitchen', Role::VALID_ROLES);
        $this->assertContains('keeper', Role::VALID_ROLES);
        $this->assertContains('supervisor', Role::VALID_ROLES);
    }
}
