<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Core\Http\FormRequest;
use Override;

/**
 * Valida y sanitiza los datos del formulario de login.
 */
final class LoginRequest extends FormRequest
{
    #[Override]
    protected function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    #[Override]
    protected function sanitize(array $raw): array
    {
        return [
            'email' => \strtolower(\trim((string) ($raw['email'] ?? ''))),
            'password' => (string) ($raw['password'] ?? ''),
        ];
    }
}
