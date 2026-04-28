<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\DTO\CafeDTO;
use App\Domain\DTO\LoyaltyCardDTO;
use App\Domain\DTO\ProductDTO;
use App\Domain\DTO\ReservationDTO;
use App\Domain\DTO\ReviewDTO;
use App\Domain\DTO\UserDTO;
use App\Domain\DTO\WaitlistEntryDTO;
use PHPUnit\Framework\TestCase;

/**
 * Clase base para tests de servicios.
 *
 * Centraliza la construcción de DTOs de prueba (CafeDTO, ProductDTO, ReviewDTO, UserDTO)
 * para evitar duplicación en AvailabilityServiceTest, ReviewServiceTest y
 * ReservationTimeSlotServiceTest.
 */
abstract class ServiceTestCase extends TestCase
{
    // ──── Helpers de fecha ────────────────────────────────────────────────────

    /**
     * Fecha válida +7 días (dentro del rango de reserva de 30 días).
     */
    protected function validFutureDate(): string
    {
        return (new \DateTime('+7 days'))->format('Y-m-d');
    }

    // ──── DTO factories ───────────────────────────────────────────────────────

    protected function makeCafe(
        bool $isActive = true,
        bool $hasReservations = true,
        int $capacityMax = 10,
        string $category = 'cat_cafe',
        string $animalType = 'cat'
    ): CafeDTO {
        return new CafeDTO(
            id: 1,
            slug: 'test-cafe',
            name: 'Test Café',
            japanese_name: null,
            description: null,
            location: 'Tokyo',
            category: $category,
            animal_type: $animalType,
            price_per_hour: 1000.0,
            capacity_max: $capacityMax,
            rating_avg: 4.5,
            opening_time: '09:00',
            closing_time: '21:00',
            timezone: 'Asia/Tokyo',
            is_active: $isActive,
            has_reservations: $hasReservations,
            image_url: null,
        );
    }

    protected function makePass(
        bool $isActive = true,
        string $productType = 'pass',
        int $minPax = 1,
        ?int $maxPax = 4,
        int $duration = 60,
        ?string $targetCafeTypes = null,
        ?string $targetAnimalTypes = null
    ): ProductDTO {
        return new ProductDTO(
            id: 1,
            name: 'Pass 1h',
            slug: 'pass-1h',
            description: null,
            price: 1000.0,
            category_id: 1,
            category_name: 'Passes',
            allergens: [],
            is_active: $isActive,
            image_url: null,
            product_type: $productType,
            min_pax: $minPax,
            max_pax: $maxPax,
            duration_minutes: $duration,
            attributes: null,
            target_cafe_types: $targetCafeTypes,
            target_animal_types: $targetAnimalTypes,
            stock_quantity: null,
        );
    }

    protected function makeReview(int $userId = 1): ReviewDTO
    {
        return new ReviewDTO(
            id: 10,
            user_id: $userId,
            cafe_id: 1,
            cafe_name: 'Test Café',
            user_name: 'Test User',
            rating: 4,
            title: 'Buen sitio',
            body: 'Me gustó mucho',
            status: 'approved',
            created_at: '2025-01-01 00:00:00',
        );
    }

    protected function makeActiveUser(int $id = 1): UserDTO
    {
        return new UserDTO(
            id: $id,
            uuid: '',
            name: 'Test User',
            email: 'test@test.com',
            avatar: null,
            roles: [],
            is_active: true,
            cafe_id: null,
            created_at: '',
        );
    }

    protected function makeReservation(array $overrides = []): ReservationDTO
    {
        $defaults = [
            'id'             => 1,
            'uuid'           => 'b1b2c3d4-0000-0000-0000-000000000001',
            'cafe_id'        => 1,
            'user_id'        => 1,
            'date'           => $this->validFutureDate(),
            'time'           => '11:00:00',
            'guest_count'    => 2,
            'status'         => 'confirmed',
            'time_slot_id'   => null,
            'pass_name'      => null,
            'check_in_at'    => null,
            'check_out_at'   => null,
            'final_amount'   => null,
            'payment_status' => null,
            'payment_method' => null,
            'notes'          => null,
        ];

        $data = \array_merge($defaults, $overrides);

        return ReservationDTO::fromArray($data);
    }

    protected function makeLoyaltyCard(array $overrides = []): LoyaltyCardDTO
    {
        $defaults = [
            'id'                     => 1,
            'user_id'                => 1,
            'stamps'                 => 3,
            'current_tier'           => 'bronze',
            'visits_count'           => 3,
            'total_rewards_redeemed' => 0,
            'last_stamp_at'          => '2025-04-01 10:00:00',
            'created_at'             => '2025-01-01 00:00:00',
            'updated_at'             => '2025-04-01 10:00:00',
        ];

        $data = \array_merge($defaults, $overrides);

        return LoyaltyCardDTO::fromArray($data);
    }

    protected function makeWaitlistEntry(array $overrides = []): WaitlistEntryDTO
    {
        $defaults = [
            'id'               => 1,
            'token'            => 'wl-token-0001',
            'status'           => 'waiting',
            'position'         => 1,
            'time_slot_id'     => 10,
            'user_id'          => 1,
            'slot_date'        => '2025-06-20',
            'slot_time'        => '11:00:00',
            'cafe_name'        => 'Komorebi Madrid',
            'guest_count'      => 2,
            'contact_email'    => 'user@test.com',
            'expires_at'       => null,
            'special_requests' => null,
        ];

        $data = \array_merge($defaults, $overrides);

        return WaitlistEntryDTO::fromArray($data);
    }
}
