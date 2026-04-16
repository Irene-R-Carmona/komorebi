<?php

declare(strict_types=1);

namespace App\Http\Requests\Reservation;

use App\Core\Http\FormRequest;

/**
 * Valida y sanitiza los datos para crear una nueva reserva.
 */
final class CreateReservationRequest extends FormRequest
{
    #[\Override]
    protected function rules(): array
    {
        return [
            'date' => 'required|regex:^\d{4}-\d{2}-\d{2}$',
            'time' => 'required|regex:^([01]\d|2[0-3]):[0-5]\d$',
            'guest_count' => 'required|integer',
        ];
    }

    #[\Override]
    protected function sanitize(array $raw): array
    {
        return [
            'date' => \trim((string) ($raw['date'] ?? '')),
            'time' => \trim((string) ($raw['time'] ?? '')),
            'guest_count' => (string) ((int) ($raw['guest_count'] ?? 0)),
        ];
    }
}
