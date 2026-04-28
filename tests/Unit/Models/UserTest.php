<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Constantes del modelo User (MAX_LOGIN_ATTEMPTS, LOCKOUT_MINUTES, VALID_ROLES).
 * ¿Qué me quieres demostrar? Que los valores canónicos de las constantes no cambian por accidente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se modifican los valores de las constantes.
 */
final class UserTest extends TestCase
{
    public function testMaxLoginAttemptsConstant(): void
    {
        $this->assertSame(5, User::MAX_LOGIN_ATTEMPTS);
    }

    public function testLockoutMinutesConstant(): void
    {
        $this->assertSame(15, User::LOCKOUT_MINUTES);
    }

    public function testValidRolesContainsExpectedRoles(): void
    {
        $this->assertContains('user', User::VALID_ROLES);
        $this->assertContains('admin', User::VALID_ROLES);
        $this->assertContains('manager', User::VALID_ROLES);
        $this->assertContains('reception', User::VALID_ROLES);
        $this->assertContains('kitchen', User::VALID_ROLES);
        $this->assertContains('keeper', User::VALID_ROLES);
        $this->assertContains('supervisor', User::VALID_ROLES);
    }
}