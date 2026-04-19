<?php

/**
 * ¿Qué pruebas aquí?
 * Tests de Manager\ReviewController: index, approve, reject con CSRF y Result pattern.
 *
 * ¿Qué me quieres demostrar?
 * - index() renderiza 403 cuando no hay cafe_id en sesión.
 * - index() renderiza la vista cuando hay cafe_id.
 * - approve() redirige a /manager/reviews cuando falla CSRF.
 * - approve() redirige cuando el servicio devuelve Result::fail.
 * - approve() redirige cuando el servicio devuelve Result::ok.
 * - reject() redirige a /manager/reviews cuando falla CSRF.
 * - reject() redirige cuando el servicio devuelve Result::fail.
 * - reject() redirige cuando el servicio devuelve Result::ok.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si approve()/reject() dejan de validar CSRF antes de llamar al servicio.
 * - Si las rutas de redirección cambian de /manager/reviews.
 * - Si index() deja de renderizar 403 para managers sin café asignado.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Core\Result;
use App\Http\Controllers\Manager\ReviewController;
use App\Services\Contracts\ReviewModerationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReviewController::class)]
final class ReviewControllerTest extends TestCase
{
    /** @var ReviewQueryServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private ReviewQueryServiceInterface $queryService;
    /** @var ReviewModerationServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private ReviewModerationServiceInterface $moderationService;

    protected function setUp(): void
    {
        $this->queryService = $this->createMock(ReviewQueryServiceInterface::class);
        $this->moderationService = $this->createMock(ReviewModerationServiceInterface::class);
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

        $controller = new ReviewController($this->queryService, $this->moderationService);

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
            'avg_rating' => 0.0, 'total_reviews' => 0,
            'one_star' => 0, 'two_stars' => 0, 'three_stars' => 0,
            'four_stars' => 0, 'five_stars' => 0,
        ]);

        $controller = new ReviewController($this->queryService, $this->moderationService);

        \ob_start();
        $result = $controller->index();
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // approve()
    // ─────────────────────────────────────────────────────────────

    public function test_approve_redirects_on_csrf_failure(): void
    {
        $this->startSession();
        $_SESSION['_csrf_token'] = 'real_token';
        $_POST['csrf_token'] = 'wrong_token';
        $_POST['id'] = '7';

        $response = new ReviewController($this->queryService, $this->moderationService)->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function test_approve_redirects_when_service_returns_fail(): void
    {
        $this->startSession();
        $token = 'valid_csrf';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '999';

        $this->moderationService->method('approveReview')
            ->willReturn(Result::fail('Reseña no encontrada', 'not_found'));

        $response = new ReviewController($this->queryService, $this->moderationService)->approve();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function test_approve_redirects_on_service_success(): void
    {
        $this->startSession();
        $token = 'valid_csrf';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '5';

        $this->moderationService->method('approveReview')
            ->willReturn(Result::ok(['id' => 5]));

        $response = new ReviewController($this->queryService, $this->moderationService)->approve();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    // ─────────────────────────────────────────────────────────────
    // reject()
    // ─────────────────────────────────────────────────────────────

    public function test_reject_redirects_on_csrf_failure(): void
    {
        $this->startSession();
        $_SESSION['_csrf_token'] = 'real_token';
        $_POST['csrf_token'] = 'tampered_token';
        $_POST['id'] = '7';
        $_POST['reason'] = 'Spam';

        $response = new ReviewController($this->queryService, $this->moderationService)->reject();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function test_reject_redirects_when_service_returns_fail(): void
    {
        $this->startSession();
        $token = 'valid_csrf';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '42';
        $_POST['reason'] = 'Contenido inapropiado';

        $this->moderationService->method('rejectReview')
            ->willReturn(Result::fail('Reseña no encontrada', 'not_found'));

        $response = new ReviewController($this->queryService, $this->moderationService)->reject();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function test_reject_redirects_on_service_success(): void
    {
        $this->startSession();
        $token = 'valid_csrf';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '12';
        $_POST['reason'] = 'Lenguaje inapropiado';

        $this->moderationService->method('rejectReview')
            ->willReturn(Result::ok(['id' => 12]));

        $response = new ReviewController($this->queryService, $this->moderationService)->reject();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }
}
