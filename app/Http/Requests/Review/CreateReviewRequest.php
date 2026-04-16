<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Core\Http\FormRequest;

/**
 * Valida y sanitiza los datos para crear una nueva reseña.
 */
final class CreateReviewRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'cafe_id' => 'required|integer',
            'rating' => 'required|integer|in:1,2,3,4,5',
            'title' => 'required|max:100',
            'body' => 'required|min:10|max:5000',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'cafe_id' => (string) ((int) ($raw['cafe_id'] ?? 0)),
            'rating' => (string) ((int) ($raw['rating'] ?? 0)),
            'title' => \trim((string) ($raw['title'] ?? '')),
            'body' => \trim((string) ($raw['body'] ?? '')),
        ];
    }
}
