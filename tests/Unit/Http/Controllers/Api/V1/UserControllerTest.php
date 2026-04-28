<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\UserController cumple el contrato PSR-7 para endpoints autenticados.
 *
 * ¿Qué me quieres demostrar?
 * Que profile(), stats() y reviews() retornan 401 sin user_id,
 * y 200 con la estructura correcta cuando el usuario está autenticado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la verificación de user_id, si cambia la estructura de la respuesta,
 * o si se deja de delegar en los servicios inyectados.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Transformers\ReviewTransformer;
use App\Http\Transformers\UserTransformer;
use App\Services\Contracts\GamificationServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(UserController::class)]
final class UserControllerTest extends ControllerTestCase
{
    private function makeController(
        ?UserProfileServiceInterface $profileService = null,
        ?ReservationServiceInterface $reservationService = null,
        ?GamificationServiceInterface $gamificationService = null,
        ?ReviewQueryServiceInterface $reviewQueryService = null,
    ): UserController {
        return new UserController(
            new ResponseFactory(),
            $profileService ?? $this->createStub(UserProfileServiceInterface::class),
            $reservationService ?? $this->createStub(ReservationServiceInterface::class),
            $gamificationService ?? $this->createStub(GamificationServiceInterface::class),
            $reviewQueryService ?? $this->createStub(ReviewQueryServiceInterface::class),
            new UserTransformer(),
            new ReviewTransformer(),
        );
    }

    // ─────────────────────────────────────────────────────────────
    // profile()
    // ─────────────────────────────────────────────────────────────

    public function test_profile_returns_401_when_no_user_id(): void
    {
        $response = $this->makeController()->profile(
            $this->makeGetRequest('/api/v1/user/profile')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_profile_returns_200_with_transformed_user(): void
    {
        $profileService = $this->createStub(UserProfileServiceInterface::class);
        $profileService->method('getProfile')->willReturn([
            'id' => 1,
            'uuid' => 'test-uuid-001',
            'name' => 'Test User',
            'email' => 'test@komorebi.es',
            'is_active' => true,
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $request = (new ServerRequest('GET', '/api/v1/user/profile'))
            ->withAttribute('user_id', 1);

        $response = $this->makeController(profileService: $profileService)->profile($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('email', $body['data']);
        $this->assertSame(1, $body['data']['id']);
    }

    // ─────────────────────────────────────────────────────────────
    // stats()
    // ─────────────────────────────────────────────────────────────

    public function test_stats_returns_401_when_no_user_id(): void
    {
        $response = $this->makeController()->stats(
            $this->makeGetRequest('/api/v1/user/stats')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_stats_returns_200_with_reservations_count_and_level(): void
    {
        $reservationService = $this->createStub(ReservationServiceInterface::class);
        $reservationService->method('getByUser')->willReturn([
            ['id' => 1], ['id' => 2], ['id' => 3],
        ]);

        $gamificationService = $this->createStub(GamificationServiceInterface::class);
        $gamificationService->method('calculateUserLevel')->willReturn([
            'level' => 1,
            'name' => 'Principiante',
            'next_level_at' => 5,
        ]);

        $request = (new ServerRequest('GET', '/api/v1/user/stats'))
            ->withAttribute('user_id', 1);

        $response = $this->makeController(
            reservationService: $reservationService,
            gamificationService: $gamificationService,
        )->stats($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertSame(3, $body['data']['reservations_count']);
        $this->assertArrayHasKey('level', $body['data']);
    }

    // ─────────────────────────────────────────────────────────────
    // reviews()
    // ─────────────────────────────────────────────────────────────

    public function test_reviews_returns_401_when_no_user_id(): void
    {
        $response = $this->makeController()->reviews(
            $this->makeGetRequest('/api/v1/user/reviews')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_reviews_returns_200_with_items_and_meta(): void
    {
        $queryService = $this->createStub(ReviewQueryServiceInterface::class);
        $queryService->method('listUserReviews')->willReturn([
            [
                'id' => 1, 'cafe_id' => 1, 'cafe_name' => 'Neko', 'rating' => 5,
                'title' => 'Genial', 'body' => 'Me encantó', 'status' => 'approved',
                'created_at' => '2024-01-01',
            ],
        ]);

        $request = (new ServerRequest('GET', '/api/v1/user/reviews'))
            ->withAttribute('user_id', 1);

        $response = $this->makeController(reviewQueryService: $queryService)->reviews($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('meta', $body['data']);
        $this->assertArrayHasKey('page', $body['data']['meta']);
        $this->assertArrayHasKey('has_next_page', $body['data']['meta']);
    }

    public function test_reviews_returns_empty_items_when_no_reviews(): void
    {
        $queryService = $this->createStub(ReviewQueryServiceInterface::class);
        $queryService->method('listUserReviews')->willReturn([]);

        $request = (new ServerRequest('GET', '/api/v1/user/reviews'))
            ->withAttribute('user_id', 2);

        $response = $this->makeController(reviewQueryService: $queryService)->reviews($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertCount(0, $body['data']['items']);
        $this->assertFalse($body['data']['meta']['has_next_page']);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(UserController::class, 'profile'));
        $this->assertTrue(\method_exists(UserController::class, 'stats'));
        $this->assertTrue(\method_exists(UserController::class, 'reviews'));
    }
}
