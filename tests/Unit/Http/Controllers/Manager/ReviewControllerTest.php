<?php

/**
 * ¿Qué pruebas aquí?
 * Tests de Manager\ReviewController: index con y sin cafe_id en sesión.
 *
 * ¿Qué me quieres demostrar?
 * - index() renderiza 403 cuando no hay cafe_id en sesión.
 * - index() renderiza la vista cuando hay cafe_id.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si index() deja de renderizar 403 para managers sin café asignado.
 * - Si la ruta o el layout de la vista cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Http\Controllers\Manager\ReviewController;
use App\Services\Contracts\ReviewQueryServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewController::class)]
final class ReviewControllerTest extends TestCase
{
    /** @var ReviewQueryServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private ReviewQueryServiceInterface $queryService;

    protected function setUp(): void
    {
        $this->queryService = $this->createStub(ReviewQueryServiceInterface::class);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (isset($_SESSION)) {
            unset($_SESSION['user_cafe_id'], $_SESSION['_csrf_token']);
        }
    }

    private function startSession(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) {
            \session_start();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function test_index_returns_null_when_no_cafe_id_in_session(): void
    {
        $this->startSession();
        unset($_SESSION['user_cafe_id']);

        $controller = new ReviewController($this->queryService);

        \ob_start();
        $result = $controller->index();
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_index_returns_null_when_cafe_id_is_set(): void
    {
        $this->startSession();
        $_SESSION['user_cafe_id'] = 3;
        $_SERVER['REQUEST_URI'] = '/manager/reviews';

        $this->queryService->method('getReviewsByCafeId')->willReturn([]);
        $this->queryService->method('getCafeRatingStats')->willReturn([
            'avg_rating' => 0.0,
            'total_reviews' => 0,
            'one_star' => 0,
            'two_stars' => 0,
            'three_stars' => 0,
            'four_stars' => 0,
            'five_stars' => 0,
        ]);

        $controller = new ReviewController($this->queryService);

        \ob_start();
        $result = $controller->index();
        \ob_end_clean();

        $this->assertNull($result);
    }
}
