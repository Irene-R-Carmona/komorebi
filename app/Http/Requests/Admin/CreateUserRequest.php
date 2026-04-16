<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Core\Http\FormRequest;

/**
 * Valida y sanitiza los datos para crear un nuevo usuario (backoffice).
 */
final class CreateUserRequest extends FormRequest
{
    private const string VALID_ROLES = 'admin,manager,supervisor,reception,kitchen,keeper,user';

    #[\Override]
    protected function rules(): array
    {
        return [
            'name' => 'required|max:50',
            'email' => 'required|email',
            'role' => 'required|in:' . self::VALID_ROLES,
            'password' => 'required|min:8',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name' => \trim((string) ($raw['name'] ?? '')),
            'email' => \strtolower(\trim((string) ($raw['email'] ?? ''))),
            'role' => \trim((string) ($raw['role'] ?? '')),
            'password' => (string) ($raw['password'] ?? ''),
        ];
    }
}
