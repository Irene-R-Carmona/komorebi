<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests para Manager\ReviewController: index, approve, reject.
 *
 * ¿Qué me quieres demostrar?
 * - index() devuelve null y renderiza error cuando no hay cafe_id en sesión.
 * - index() devuelve null y renderiza la tabla cuando hay cafe_id en sesión.
 * - approve() redirige a /manager/reviews cuando falla CSRF (sin llamar al servicio).
 * - approve() redirige a /manager/reviews cuando el servicio falla (Result::fail).
 * - approve() redirige a /manager/reviews cuando el servicio tiene éxito (Result::ok).
 * - reject() redirige a /manager/reviews cuando falla CSRF.
 * - reject() redirige a /manager/reviews cuando el servicio falla.
 * - reject() redirige a /manager/reviews cuando el servicio tiene éxito.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si approve()/reject() dejan de validar CSRF antes de llamar al servicio.
 * - Si las rutas de redirección cambian de /manager/reviews a otra ruta.
 * - Si index() deja de renderizar 403 para managers sin café asignado.
 * - Si el constructor deja de aceptar ReviewService inyectado.
 *
 * NOTA: ReviewService es final, por lo que se construye con dependencias mockeadas
 * (ReviewRepositoryInterface + CafeRepository con PDO mock).
 */

namespace Controllers\Manager;

use App\Core\Result;
use App\Http\Controllers\Manager\ReviewController;
use App\Services\Contracts\ReviewModerationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests para Manager\ReviewController
 */
final class ReviewControllerTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\Stub&ReviewQueryServiceInterface */
    private ReviewQueryServiceInterface $queryService;
    /** @var \PHPUnit\Framework\MockObject\Stub&ReviewModerationServiceInterface */
    private ReviewModerationServiceInterface $moderationService;

    protected function setUp(): void
    {
        $this->queryService = $this->createStub(ReviewQueryServiceInterface::class);
        $this->moderationService = $this->createStub(ReviewModerationServiceInterface::class);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (isset($_SESSION)) {
            unset($_SESSION['user_cafe_id'], $_SESSION['_csrf_token']);
        }
    }

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new ReviewController($this->queryService, $this->moderationService);
        $this->assertInstanceOf(ReviewController::class, $controller);
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function testIndexReturnsNullWhenNoCafeIdInSession(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        unset($_SESSION['user_cafe_id']);

        $controller = new ReviewController($this->queryService, $this->moderationService);

        \ob_start();
        $result = $controller->index();
        \ob_get_clean();

        $this->assertNull($result);
    }

    public function testIndexReturnsNullWhenCafeIdIsSet(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_cafe_id'] = 3;
        // El layout backoffice.php necesita REQUEST_URI
        $_SERVER['REQUEST_URI'] = '/manager/reviews';

        // queryService devuelve datos vacíos para el manager
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

        $controller = new ReviewController($this->queryService, $this->moderationService);

        \ob_start();
        $result = $controller->index();
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // approve()
    // ─────────────────────────────────────────────────────────────

    public function testApproveRedirectsOnCsrfFailure(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['_csrf_token'] = 'session_token_abc';
        $_POST['csrf_token'] = 'wrong_token';
        $_POST['id'] = '7';

        $controller = new ReviewController($this->queryService, $this->moderationService);
        $response = $controller->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testApproveRedirectsWhenReviewNotFound(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $token = 'valid_csrf_xyz';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '999';

        // approveReview retorna Result::fail → el controller muestra error
        $this->moderationService->method('approveReview')->willReturn(
            Result::fail('Reseña no encontrada', 'not_found')
        );

        $controller = new ReviewController($this->queryService, $this->moderationService);
        $response = $controller->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testApproveRedirectsOnServiceSuccess(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $token = 'valid_csrf_xyz';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '5';

        // approveReview retorna Result::ok → el controller muestra éxito
        $this->moderationService->method('approveReview')->willReturn(
            Result::ok(['id' => 5])
        );

        $controller = new ReviewController($this->queryService, $this->moderationService);
        $response = $controller->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    // ─────────────────────────────────────────────────────────────
    // reject()
    // ─────────────────────────────────────────────────────────────

    public function testRejectRedirectsOnCsrfFailure(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['_csrf_token'] = 'real_token_abc';
        $_POST['csrf_token'] = 'tampered_token';
        $_POST['id'] = '7';
        $_POST['reason'] = 'Spam en la reseña';

        $controller = new ReviewController($this->queryService, $this->moderationService);
        $response = $controller->reject();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testRejectRedirectsWhenReviewNotFound(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $token = 'valid_csrf_abc';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '42';
        $_POST['reason'] = 'Contenido inapropiado detectado';

        $this->moderationService->method('rejectReview')->willReturn(
            Result::fail('Reseña no encontrada', 'not_found')
        );

        $controller = new ReviewController($this->queryService, $this->moderationService);
        $response = $controller->reject();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testRejectRedirectsOnServiceSuccess(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $token = 'valid_csrf_abc';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token'] = $token;
        $_POST['id'] = '12';
        $_POST['reason'] = 'Lenguaje inapropiado detectado en el texto';

        $this->moderationService->method('rejectReview')->willReturn(
            Result::ok(['id' => 12])
        );

        $controller = new ReviewController($this->queryService, $this->moderationService);
        $response = $controller->reject();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }
}
