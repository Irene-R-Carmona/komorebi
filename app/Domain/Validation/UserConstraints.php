<?php

declare(strict_types=1);

namespace App\Domain\Validation;

/**
 * Restricciones de dominio para usuarios.
 *
 * Centraliza los límites de validación de usuario para evitar
 * valores mágicos dispersos por los servicios.
 */
final class UserConstraints
{
    /** Longitud mínima de contraseña (caracteres). */
    public const int PASSWORD_MIN_LENGTH = 8;

    /** Longitud máxima de nombre de usuario (caracteres). */
    public const int NAME_MAX_LENGTH = 100;

    /** Longitud mínima de nombre de usuario (caracteres). */
    public const int NAME_MIN_LENGTH = 2;
}
