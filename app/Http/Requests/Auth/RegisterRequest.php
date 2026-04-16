<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Core\Http\FormRequest;

/**
 * Valida y sanitiza los datos del formulario de registro.
 */
final class RegisterRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'name' => 'required|max:50',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirmation' => 'required',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name' => \trim((string) ($raw['name'] ?? '')),
            'email' => \strtolower(\trim((string) ($raw['email'] ?? ''))),
            'password' => (string) ($raw['password'] ?? ''),
            'password_confirmation' => (string) ($raw['password_confirm'] ?? $raw['password_confirmation'] ?? ''),
        ];
    }
}
