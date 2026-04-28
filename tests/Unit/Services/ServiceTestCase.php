<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\DTO\CafeDTO;
use App\Domain\DTO\ProductDTO;
use App\Domain\DTO\ReviewDTO;
use App\Domain\DTO\UserDTO;
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
}
