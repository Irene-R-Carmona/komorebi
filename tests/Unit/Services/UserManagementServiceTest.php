<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * UserManagementService::validateUserData — la única lógica pura del servicio.
 * Validación de nombre, email, contraseña y rol_id tanto en modo creación como actualización.
 *
 * ¿Qué me quieres demostrar?
 * Que la validación devuelve Result::fail con errores específicos por campo,
 * y que el modo isUpdate=true permite omitir la contraseña.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambian las reglas de longitud (mínimo 2 chars nombre, 8 chars password),
 * si se elimina la validación de email, o si el resultado ok() ya no devuelve true.
 */

namespace Tests\Unit\Services;

use App\Services\UserManagementService;
use PHPUnit\Framework\TestCase;

final class UserManagementServiceTest extends TestCase
{
    private UserManagementService $service;

    protected function setUp(): void
    {
        $this->service = new UserManagementService($this->createStub(\PDO::class));
    }

    // ──────────────────────────────────────────────
    // validateUserData — camino feliz (creación)
    // ──────────────────────────────────────────────

    public function testValidarDatosValidosRetornaOk(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'Ana García',
            'email' => 'ana@komorebi.es',
            'password' => 'SecretPass1',
            'role_id' => 2,
        ]);

        $this->assertTrue($result->ok);
    }

    // ──────────────────────────────────────────────
    // validateUserData — nombre
    // ──────────────────────────────────────────────

    public function testValidarConNombreVacioRetornaFail(): void
    {
        $result = $this->service->validateUserData([
            'name' => '',
            'email' => 'ana@komorebi.es',
            'password' => 'SecretPass1',
            'role_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('name', $result->data['errors']);
    }

    public function testValidarConNombreDe1CaracterRetornaFail(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'A',
            'email' => 'ana@komorebi.es',
            'password' => 'SecretPass1',
            'role_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('name', $result->data['errors']);
    }

    // ──────────────────────────────────────────────
    // validateUserData — email
    // ──────────────────────────────────────────────

    public function testValidarConEmailInvalidoRetornaFail(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'Ana García',
            'email' => 'no-es-un-email',
            'password' => 'SecretPass1',
            'role_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('email', $result->data['errors']);
    }

    // ──────────────────────────────────────────────
    // validateUserData — contraseña
    // ──────────────────────────────────────────────

    public function testValidarConPasswordCortaRetornaFail(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'Ana García',
            'email' => 'ana@komorebi.es',
            'password' => 'corta',
            'role_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('password', $result->data['errors']);
    }

    public function testValidarActualizacionSinPasswordEsValido(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'Ana García',
            'email' => 'ana@komorebi.es',
            'password' => '',  // vacía pero isUpdate = true
            'role_id' => 2,
        ], isUpdate: true);

        // La contraseña vacía en modo update no debe bloquear la validación
        $this->assertFalse(isset($result->data['errors']['password']));
    }

    // ──────────────────────────────────────────────
    // validateUserData — role_id
    // ──────────────────────────────────────────────

    public function testValidarSinRoleIdRetornaFail(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'Ana García',
            'email' => 'ana@komorebi.es',
            'password' => 'SecretPass1',
        ]);

        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('role_id', $result->data['errors']);
    }

    public function testValidarConRoleIdNoNumericoRetornaFail(): void
    {
        $result = $this->service->validateUserData([
            'name' => 'Ana García',
            'email' => 'ana@komorebi.es',
            'password' => 'SecretPass1',
            'role_id' => 'admin',
        ]);

        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('role_id', $result->data['errors']);
    }
}
