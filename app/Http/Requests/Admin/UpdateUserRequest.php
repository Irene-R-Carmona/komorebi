<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Core\Http\FormRequest;

/**
 * Valida y sanitiza los datos para actualizar un usuario existente (backoffice).
 */
final class UpdateUserRequest extends FormRequest
{
    private const string VALID_ROLES = 'admin,manager,supervisor,reception,kitchen,keeper,user';

    #[\Override]
    protected function rules(): array
    {
        return [
            'name'  => 'required|max:50',
            'email' => 'required|email',
            'role'  => 'required|in:' . self::VALID_ROLES,
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'name'  => trim((string) ($raw['name'] ?? '')),
            'email' => strtolower(trim((string) ($raw['email'] ?? ''))),
            'role'  => trim((string) ($raw['role'] ?? '')),
        ];
    }
}
