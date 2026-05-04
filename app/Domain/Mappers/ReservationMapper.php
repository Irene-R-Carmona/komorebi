<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\ReservationDTO;
use Override;

final readonly class ReservationMapper implements MapperInterface
{
    #[Override]
    public function toDTO(array $row): ReservationDTO
    {
        return new ReservationDTO(
            id: (int) $row['id'],
            uuid: (string) ($row['uuid'] ?? ''),
            cafe_id: (int) $row['cafe_id'],
            user_id: (int) $row['user_id'],
            date: (string) $row['reservation_date'],
            time: (string) $row['reservation_time'],
            guest_count: (int) ($row['guest_count'] ?? 1),
            status: (string) ($row['status'] ?? 'pending'),
            time_slot_id: isset($row['time_slot_id']) ? (int) $row['time_slot_id'] : null,
            pass_name: isset($row['pass_name']) ? (string) $row['pass_name'] : null,
            check_in_at: isset($row['check_in_at']) ? (string) $row['check_in_at'] : null,
            check_out_at: isset($row['check_out_at']) ? (string) $row['check_out_at'] : null,
            final_amount: isset($row['final_amount']) ? (float) $row['final_amount'] : null,
            payment_status: isset($row['payment_status']) ? (string) $row['payment_status'] : null,
            payment_method: isset($row['payment_method']) ? (string) $row['payment_method'] : null,
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
        );
    }
}
