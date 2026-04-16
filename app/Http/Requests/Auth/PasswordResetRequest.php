<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Core\Http\FormRequest;

/**
 * Valida y sanitiza el formulario de solicitud de restablecimiento de contraseña.
 */
final class PasswordResetRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'email' => \strtolower(\trim((string) ($raw['email'] ?? ''))),
        ];
    }
}
