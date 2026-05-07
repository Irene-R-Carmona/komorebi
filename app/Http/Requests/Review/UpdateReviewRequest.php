<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Core\Http\FormRequest;
use Override;

/**
 * Valida y sanitiza los datos para actualizar una reseña existente.
 * Todos los campos son opcionales; solo se validan si se envían.
 */
final class UpdateReviewRequest extends FormRequest
{
    #[Override]
    protected function rules(): array
    {
        return [
            'rating' => 'integer|in:1,2,3,4,5',
            'title' => 'max:100',
            'body' => 'min:10|max:5000',
        ];
    }

    #[Override]
    protected function sanitize(array $raw): array
    {
        $sanitized = [];

        if (isset($raw['rating']) && $raw['rating'] !== '') {
            $sanitized['rating'] = (string) ((int) $raw['rating']);
        }
        if (isset($raw['title'])) {
            $sanitized['title'] = \trim((string) $raw['title']);
        }
        if (isset($raw['body'])) {
            $sanitized['body'] = \trim((string) $raw['body']);
        }

        return $sanitized;
    }
}
