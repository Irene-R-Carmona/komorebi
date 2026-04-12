<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para el servicio core de autenticación.
 *
 * Cubre login, registro, logout y verificación de sesión activa.
 */
interface AuthServiceInterface
{
    /**
     * Autenticar usuario con email y contraseña.
     *
     * La implementación obtiene IP y User-Agent del contexto HTTP.
     *
     * @return Result<array<string,mixed>>
     */
    public function login(string $email, string $password): Result;

    /**
     * Registrar nuevo usuario.
     *
     * @return Result<array<string,int>>
     */
    public function register(string $name, string $email, string $password, string $confirmPassword): Result;

    /**
     * Cerrar la sesión del usuario autenticado.
     */
    public function logout(): void;

    /**
     * Verificar si hay un usuario autenticado en la sesión.
     */
    public function check(): bool;

    /**
     * Obtener los datos del usuario autenticado.
     *
     * @return array<string,mixed>|null
     */
    public function user(): ?array;
}
