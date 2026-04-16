<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests de Integración de ReviewService, ReviewQueryService y ReviewModerationService
 *
 * Valida operaciones con MySQL 8.4 real usando transacciones para aislamiento.
 * Estos tests NO usan mocks - ejecutan queries reales contra la BD.
 */

namespace Tests\Integration;

use App\Models\User;
use App\Repositories\CafeRepository;
use App\Repositories\ReviewRepository;
use App\Services\ReviewModerationService;
use App\Services\ReviewQueryService;
use App\Services\ReviewService;
use PDO;
use Tests\Support\BaseIntegrationTest;

final class ReviewIntegrationTest extends BaseIntegrationTest
{
    private ReviewService $service;
    private ReviewQueryService $queryService;
    private ReviewModerationService $moderationService;

    // IDs únicos para tests
    private const TEST_USER_ID = 99980;
    private const TEST_CAFE_ID = 99981;
    private const TEST_CATEGORY_ID = 99983;
    private const TEST_PASS_ID = 99984;
    private const TEST_RESERVATION_ID = 99985;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $reviewRepo = new ReviewRepository(self::$db);
        $cafeRepo = new CafeRepository(self::$db);
        $this->service = new ReviewService(new User(), $reviewRepo);
        $this->queryService = new ReviewQueryService($reviewRepo);
        $this->moderationService = new ReviewModerationService($reviewRepo, $cafeRepo);
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Limpiar datos previos
        self::$db->exec('DELETE FROM reservations WHERE id = ' . self::TEST_RESERVATION_ID);
        self::$db->exec('DELETE FROM reviews WHERE user_id = ' . self::TEST_USER_ID);
        self::$db->exec('DELETE FROM products WHERE id = ' . self::TEST_PASS_ID);
        self::$db->exec('DELETE FROM menu_categories WHERE id = ' . self::TEST_CATEGORY_ID);
        self::$db->exec('DELETE FROM users WHERE id = ' . self::TEST_USER_ID);
        self::$db->exec('DELETE FROM cafes WHERE id = ' . self::TEST_CAFE_ID);

        // Usuario de prueba
        self::$db->exec('
            INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . self::TEST_USER_ID . ",
                UUID(),
                'review-test@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Review Test User',
                NOW(),
                1
            )
        ");

        // Café de prueba
        self::$db->exec('
            INSERT INTO cafes (
                id, name, slug, location, category, animal_type,
                description, price_per_hour, opening_time, closing_time,
                capacity_max, is_active, has_reservations
            )
            VALUES (
                ' . self::TEST_CAFE_ID . ",
                'Test Café Review',
                'test-cafe-review',
                'Tokyo Test',
                'lounge',
                'cat',
                'Café para integration tests de reviews',
                2000,
                '09:00:00',
                '20:00:00',
                50,
                1,
                1
            )
        ");

        // Categoría de productos
        self::$db->exec('
            INSERT INTO menu_categories (id, name, slug)
            VALUES (
                ' . self::TEST_CATEGORY_ID . ",
                'Test Category',
                'test-category-reviews'
            )
        ");

        // Producto/Pase de prueba
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, slug, price,
                station, duration_minutes, is_active
            )
            VALUES (
                ' . self::TEST_PASS_ID . ',
                ' . self::TEST_CATEGORY_ID . ",
                'pass',
                'Test Pass Reviews',
                'test-pass-reviews',
                2000,
                'assembly',
                60,
                1
            )
        ");

        // Reserva completada (requisito para crear reviews)
        self::$db->exec('
            INSERT INTO reservations (
                id, user_id, cafe_id, pass_product_id, pass_name,
                pass_unit_price, pass_duration_minutes, reservation_date,
                reservation_time, guest_count, status
            )
            VALUES (
                ' . self::TEST_RESERVATION_ID . ',
                ' . self::TEST_USER_ID . ',
                ' . self::TEST_CAFE_ID . ',
                ' . self::TEST_PASS_ID . ",
                'Test Pass Reviews',
                2000,
                60,
                CURDATE(),
                '14:00:00',
                1,
                'completed'
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Integration Tests
    // ─────────────────────────────────────────────────────────────

    public function testCreateReviewInsertsIntoDatabaseCorrectly(): void
    {
        // ACT: Crear review con datos válidos
        $result = $this->service->createReview(
            self::TEST_USER_ID,
            self::TEST_CAFE_ID,
            5,
            'Excelente experiencia',
            'Muy buen café con gatos adorables. Recomendado para todos los amantes de los gatos.'
        );

        // ASSERT: Verificar éxito
        $this->assertTrue($result->ok, 'Create review should succeed: ' . ($result->error ?? ''));
        $this->assertArrayHasKey('id', $result->data);

        $reviewId = $result->data['id'];

        // ASSERT: Verificar que el registro existe en BD
        $stmt = self::$db->prepare('
            SELECT id, user_id, cafe_id, rating, title, body, status
            FROM reviews
            WHERE id = ?
        ');
        $stmt->execute([$reviewId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame(self::TEST_USER_ID, (int) $row['user_id']);
        $this->assertSame(self::TEST_CAFE_ID, (int) $row['cafe_id']);
        $this->assertSame(5, (int) $row['rating']);
        $this->assertSame('Excelente experiencia', $row['title']);
        $this->assertSame('pending', $row['status']); // Por defecto pending
    }

    public function testModerateReviewUpdatesStatusInDatabase(): void
    {
        // ARRANGE: Crear review primero
        $result = $this->service->createReview(
            self::TEST_USER_ID,
            self::TEST_CAFE_ID,
            4,
            'Buena experiencia',
            'Café agradable, gatos amigables.'
        );

        $this->assertTrue($result->ok);
        $reviewId = $result->data['id'];

        // ACT: Moderar review (aprobar)
        $moderateResult = $this->moderationService->moderateReview($reviewId, 'approved');

        // ASSERT: Verificar que se actualizó
        $this->assertTrue($moderateResult);

        // Verificar en BD
        $stmt = self::$db->prepare('SELECT status FROM reviews WHERE id = ?');
        $stmt->execute([$reviewId]);
        $status = $stmt->fetchColumn();

        $this->assertSame('approved', $status);
    }

    public function testGetReviewsByCafeReturnsDataFromDatabase(): void
    {
        // ARRANGE: Crear primera review
        $result1 = $this->service->createReview(
            self::TEST_USER_ID,
            self::TEST_CAFE_ID,
            5,
            'Primera review',
            'Excelente lugar'
        );
        $this->assertTrue($result1->ok);
        // Aprobar primera review
        $this->moderationService->moderateReview($result1->data['id'], 'approved');

        // Crear segundo usuario con reserva completada
        self::$db->exec('
            INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . (self::TEST_USER_ID + 1) . ",
                UUID(),
                'review-test2@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Review Test User 2',
                NOW(),
                1
            )
        ");

        // Crear reserva completada para segundo usuario
        self::$db->exec('
            INSERT INTO reservations (
                id, user_id, cafe_id, pass_product_id, pass_name,
                pass_unit_price, pass_duration_minutes, reservation_date,
                reservation_time, guest_count, status
            )
            VALUES (
                ' . (self::TEST_RESERVATION_ID + 1) . ',
                ' . (self::TEST_USER_ID + 1) . ',
                ' . self::TEST_CAFE_ID . ',
                ' . self::TEST_PASS_ID . ",
                'Test Pass Reviews',
                2000,
                60,
                CURDATE(),
                '15:00:00',
                1,
                'completed'
            )
        ");

        // Crear segunda review
        $result2 = $this->service->createReview(
            self::TEST_USER_ID + 1,
            self::TEST_CAFE_ID,
            4,
            'Segunda review',
            'Muy buen café, ambiente agradable y gatos amigables.'
        );
        $this->assertTrue($result2->ok, 'Create review failed: ' . ($result2->error ?? 'unknown error'));
        // Aprobar segunda review
        $this->moderationService->moderateReview($result2->data['id'], 'approved');

        // ACT: Obtener reviews del café
        $reviews = $this->queryService->getReviewsByCafeId(self::TEST_CAFE_ID);

        // ASSERT: Debe retornar las 2 reviews aprobadas
        $this->assertIsArray($reviews);
        $this->assertCount(2, $reviews);

        // Verificar estructura
        foreach ($reviews as $review) {
            $this->assertArrayHasKey('id', $review);
            $this->assertArrayHasKey('rating', $review);
            $this->assertArrayHasKey('title', $review);
            $this->assertArrayHasKey('body', $review);
            $this->assertSame(self::TEST_CAFE_ID, (int) $review['cafe_id']);
        }
    }

    public function testCalculateAverageRatingFromDatabase(): void
    {
        // ARRANGE: Crear reviews con diferentes ratings
        // Review 1: rating 5
        $result1 = $this->service->createReview(
            self::TEST_USER_ID,
            self::TEST_CAFE_ID,
            5,
            'Review 1',
            'Excelente experiencia, muy recomendable para visitar.'
        );
        $this->assertTrue($result1->ok, 'Create review 1 failed: ' . ($result1->error ?? 'unknown error'));
        // Aprobar review 1
        $this->moderationService->moderateReview($result1->data['id'], 'approved');

        // Crear usuario 2 con reserva completada
        self::$db->exec('
            INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . (self::TEST_USER_ID + 2) . ",
                UUID(),
                'review-test3@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Review Test User 3',
                NOW(),
                1
            )
        ");

        // Crear reserva completada para usuario 2
        self::$db->exec('
            INSERT INTO reservations (
                id, user_id, cafe_id, pass_product_id, pass_name,
                pass_unit_price, pass_duration_minutes, reservation_date,
                reservation_time, guest_count, status
            )
            VALUES (
                ' . (self::TEST_RESERVATION_ID + 2) . ',
                ' . (self::TEST_USER_ID + 2) . ',
                ' . self::TEST_CAFE_ID . ',
                ' . self::TEST_PASS_ID . ",
                'Test Pass Reviews',
                2000,
                60,
                CURDATE(),
                '16:00:00',
                1,
                'completed'
            )
        ");

        // Review 2: rating 3
        $result2 = $this->service->createReview(
            self::TEST_USER_ID + 2,
            self::TEST_CAFE_ID,
            3,
            'Review 2',
            'Buena experiencia en general, recomendado.'
        );
        $this->assertTrue($result2->ok, 'Create review 2 failed: ' . ($result2->error ?? 'unknown error'));
        // Aprobar review 2
        $this->moderationService->moderateReview($result2->data['id'], 'approved');

        // ACT: Calcular promedio
        $average = $this->queryService->calculateAverageRating(self::TEST_CAFE_ID);

        // ASSERT: Promedio debe ser 4.0 ((5 + 3) / 2)
        $this->assertIsFloat($average);
        $this->assertEqualsWithDelta(4.0, $average, 0.01);
    }
}
