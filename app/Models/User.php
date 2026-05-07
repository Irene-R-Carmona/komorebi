<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Constantes del dominio User.
 *
 * La lógica de datos ha sido migrada a UserRepository.
 */
final class User
{
    /** Intentos máximos antes de bloqueo temporal */
    public const int MAX_LOGIN_ATTEMPTS = 5;

    /** Minutos de bloqueo tras superar intentos */
    public const int LOCKOUT_MINUTES = 15;

    /** Roles válidos del sistema (canónicos) */
    public const array VALID_ROLES = ['user', 'reception', 'kitchen', 'keeper', 'manager', 'supervisor', 'admin'];
}
