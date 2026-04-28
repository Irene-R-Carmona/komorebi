<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\ReviewController cumple el contrato PSR-7 para la API de reseñas.
 *
 * ¿Qué me quieres demostrar?
 * Que create(), update() y delete() retornan 401 sin user_id,
 * 422 con payload inválido, y los códigos correctos (201, 200, 204) en flujo feliz.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la verificación de user_id, si cambia la validación de campos,
 * o si cambia el código de estado de las respuestas exitosas.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Domain\DTO\ReviewDTO;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Transformers\ReviewTransformer;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReviewController::class)]
final class ReviewControllerTest extends ControllerTestCase
{
    private function makeController(
        ?ReviewServiceInterface $reviewService = null,
        ?ReviewQueryServiceInterface $reviewQueryService = null,
    ): ReviewController {
        return new ReviewController(
            new ResponseFactory(),
            $reviewService ?? $this->createStub(ReviewServiceInterface::class),
            $reviewQueryService ?? $this->createStub(ReviewQueryServiceInterface::class),
            new ReviewTransformer(),
        );
    }

    // ─────────────────────────────────────────────────────────────
    // create()
    // ─────────────────────────────────────────────────────────────

    public function test_create_returns_401_when_no_user_id(): void
    {
        $response = $this->makeController()->create(
            new ServerRequest('POST', '/api/v1/reviews')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_create_returns_422_when_cafe_id_missing(): void
    {
        $request = (new ServerRequest('POST', '/api/v1/reviews'))
            ->withAttribute('user_id', 1)
            ->withParsedBody(['rating' => 5, 'title' => 'Genial']);

        $this->assertSame(422, $this->makeController()->create($request)->getStatusCode());
    }

    public function test_create_returns_422_when_rating_invalid(): void
    {
        $request = (new ServerRequest('POST', '/api/v1/reviews'))
            ->withAttribute('user_id', 1)
            ->withParsedBody(['cafe_id' => 1, 'rating' => 6, 'title' => 'Genial']);

        $this->assertSame(422, $this->makeController()->create($request)->getStatusCode());
    }

    public function test_create_returns_422_when_title_empty(): void
    {
        $request = (new ServerRequest('POST', '/api/v1/reviews'))
            ->withAttribute('user_id', 1)
            ->withParsedBody(['cafe_id' => 1, 'rating' => 4, 'title' => '']);

        $this->assertSame(422, $this->makeController()->create($request)->getStatusCode());
    }

    public function test_create_returns_201_on_success_with_location_header(): void
    {
        $reviewService = $this->createStub(ReviewServiceInterface::class);
        $reviewService->method('createReview')->willReturn(
            Result::ok(['id' => 42, 'cafe_id' => 1, 'rating' => 5, 'title' => 'Genial', 'body' => ''])
        );

        $request = (new ServerRequest('POST', '/api/v1/reviews'))
            ->withAttribute('user_id', 1)
            ->withParsedBody(['cafe_id' => 1, 'rating' => 5, 'title' => 'Genial', 'body' => 'Excelente']);

        $response = $this->makeController(reviewService: $reviewService)->create($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('/api/v1/reviews/42', $response->getHeaderLine('Location'));
    }

    public function test_create_returns_422_when_service_fails(): void
    {
        $reviewService = $this->createStub(ReviewServiceInterface::class);
        $reviewService->method('createReview')->willReturn(
            Result::fail('Ya tienes una reseña en este café', 'duplicate_review')
        );

        $request = (new ServerRequest('POST', '/api/v1/reviews'))
            ->withAttribute('user_id', 1)
            ->withParsedBody(['cafe_id' => 1, 'rating' => 4, 'title' => 'Genial']);

        $response = $this->makeController(reviewService: $reviewService)->create($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────
    // update()
    // ─────────────────────────────────────────────────────────────

    public function test_update_returns_401_when_no_user_id(): void
    {
        $request = (new ServerRequest('PUT', '/api/v1/reviews/5'))
            ->withAttribute('id', 5);

        $this->assertSame(401, $this->makeController()->update($request)->getStatusCode());
    }

    public function test_update_returns_404_when_review_id_is_zero(): void
    {
        $request = (new ServerRequest('PUT', '/api/v1/reviews/0'))
            ->withAttribute('user_id', 1);

        $this->assertSame(404, $this->makeController()->update($request)->getStatusCode());
    }

    public function test_update_returns_422_when_rating_invalid(): void
    {
        $request = (new ServerRequest('PUT', '/api/v1/reviews/5'))
            ->withAttribute('user_id', 1)
            ->withAttribute('id', 5)
            ->withParsedBody(['rating' => 0, 'title' => 'Bueno']);

        $this->assertSame(422, $this->makeController()->update($request)->getStatusCode());
    }

    public function test_update_returns_200_on_success(): void
    {
        $reviewService = $this->createStub(ReviewServiceInterface::class);
        $reviewService->method('updateReview')->willReturn(Result::ok());

        $queryService = $this->createStub(ReviewQueryServiceInterface::class);
        $queryService->method('getReview')->willReturn(new ReviewDTO(
            id: 5,
            user_id: 1,
            cafe_id: 1,
            cafe_name: 'Neko',
            user_name: 'Test User',
            rating: 4,
            title: 'Actualizado',
            body: 'Texto actualizado',
            status: 'approved',
            created_at: '2024-01-01',
        ));

        $request = (new ServerRequest('PUT', '/api/v1/reviews/5'))
            ->withAttribute('user_id', 1)
            ->withAttribute('id', 5)
            ->withParsedBody(['rating' => 4, 'title' => 'Actualizado', 'body' => 'Texto actualizado']);

        $response = $this->makeController($reviewService, $queryService)->update($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────
    // delete()
    // ─────────────────────────────────────────────────────────────

    public function test_delete_returns_401_when_no_user_id(): void
    {
        $request = (new ServerRequest('DELETE', '/api/v1/reviews/5'))
            ->withAttribute('id', 5);

        $this->assertSame(401, $this->makeController()->delete($request)->getStatusCode());
    }

    public function test_delete_returns_404_when_review_id_is_zero(): void
    {
        $request = (new ServerRequest('DELETE', '/api/v1/reviews/0'))
            ->withAttribute('user_id', 1);

        $this->assertSame(404, $this->makeController()->delete($request)->getStatusCode());
    }

    public function test_delete_returns_204_on_success(): void
    {
        $reviewService = $this->createStub(ReviewServiceInterface::class);
        $reviewService->method('deleteReview')->willReturn(Result::ok());

        $request = (new ServerRequest('DELETE', '/api/v1/reviews/5'))
            ->withAttribute('user_id', 1)
            ->withAttribute('id', 5);

        $response = $this->makeController(reviewService: $reviewService)->delete($request);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReviewController::class, 'create'));
        $this->assertTrue(\method_exists(ReviewController::class, 'update'));
        $this->assertTrue(\method_exists(ReviewController::class, 'delete'));
    }
}
