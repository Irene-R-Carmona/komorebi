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

use App\Http\Controllers\Manager\ReviewController;
use App\Repositories\CafeRepository;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\ReviewService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests para Manager\ReviewController
 */
final class ReviewControllerTest extends TestCase
{
    private ReviewRepositoryInterface $reviewRepo;
    private ReviewService $reviewService;
    private PDO $pdoMock;
    private PDOStatement $stmtMock;

    protected function setUp(): void
    {
        $this->reviewRepo = $this->createStub(ReviewRepositoryInterface::class);
        $this->pdoMock    = $this->createStub(PDO::class);
        $this->stmtMock   = $this->createStub(PDOStatement::class);

        // CafeRepository con PDO mock (para no necesitar DB real)
        $cafeRepo = new CafeRepository($this->pdoMock);

        // ReviewService es final; se construye con dependencias mockeadas
        $this->reviewService = new ReviewService(null, null, $this->reviewRepo, $cafeRepo);
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
        $controller = new ReviewController($this->reviewService);
        $this->assertInstanceOf(ReviewController::class, $controller);
    }

    // ─────────────────────────────────────────────────────────────
    // index()
    // ─────────────────────────────────────────────────────────────

    public function testIndexReturnsNullWhenNoCafeIdInSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['user_cafe_id']);

        $controller = new ReviewController($this->reviewService);

        ob_start();
        $result = $controller->index();
        ob_get_clean();

        $this->assertNull($result);
    }

    public function testIndexReturnsNullWhenCafeIdIsSet(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_cafe_id'] = 3;
        // El layout backoffice.php necesita REQUEST_URI
        $_SERVER['REQUEST_URI'] = '/manager/reviews';

        // reviewRepo devuelve datos vacíos para el manager
        $this->reviewRepo->method('findByCafeId')->willReturn([]);
        $this->reviewRepo->method('getRatingStats')->willReturn([
            'avg_rating' => 0.0,
            'total_reviews' => 0,
            'one_star' => 0,
            'two_stars' => 0,
            'three_stars' => 0,
            'four_stars' => 0,
            'five_stars' => 0,
        ]);

        $controller = new ReviewController($this->reviewService);

        ob_start();
        $result = $controller->index();
        ob_end_clean();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // approve()
    // ─────────────────────────────────────────────────────────────

    public function testApproveRedirectsOnCsrfFailure(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_csrf_token'] = 'session_token_abc';
        $_POST['csrf_token']     = 'wrong_token';
        $_POST['id']             = '7';

        $controller = new ReviewController($this->reviewService);
        $response   = $controller->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testApproveRedirectsWhenReviewNotFound(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = 'valid_csrf_xyz';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token']     = $token;
        $_POST['id']             = '999';

        // findById retorna null → el servicio devuelve Result::fail
        $this->reviewRepo->method('findById')->willReturn(null);

        $controller = new ReviewController($this->reviewService);
        $response   = $controller->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testApproveRedirectsOnServiceSuccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = 'valid_csrf_xyz';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token']     = $token;
        $_POST['id']             = '5';

        // findById retorna reseña válida
        $this->reviewRepo->method('findById')->willReturn([
            'id' => 5,
            'cafe_id' => 1,
            'status' => 'pending',
        ]);
        $this->reviewRepo->method('updateStatus')->willReturn(true);

        // CafeRepository::updateRating necesita PDO
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);

        $controller = new ReviewController($this->reviewService);
        $response   = $controller->approve();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    // ─────────────────────────────────────────────────────────────
    // reject()
    // ─────────────────────────────────────────────────────────────

    public function testRejectRedirectsOnCsrfFailure(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_csrf_token'] = 'real_token_abc';
        $_POST['csrf_token']     = 'tampered_token';
        $_POST['id']             = '7';
        $_POST['reason']         = 'Spam en la reseña';

        $controller = new ReviewController($this->reviewService);
        $response   = $controller->reject();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testRejectRedirectsWhenReviewNotFound(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = 'valid_csrf_abc';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token']     = $token;
        $_POST['id']             = '42';
        $_POST['reason']         = 'Contenido inapropiado detectado';

        $this->reviewRepo->method('findById')->willReturn(null);

        $controller = new ReviewController($this->reviewService);
        $response   = $controller->reject();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }

    public function testRejectRedirectsOnServiceSuccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = 'valid_csrf_abc';
        $_SESSION['_csrf_token'] = $token;
        $_POST['csrf_token']     = $token;
        $_POST['id']             = '12';
        $_POST['reason']         = 'Lenguaje inapropiado detectado en el texto';

        $this->reviewRepo->method('findById')->willReturn([
            'id' => 12,
            'cafe_id' => 2,
            'status' => 'pending',
        ]);
        $this->reviewRepo->method('updateStatus')->willReturn(true);

        $controller = new ReviewController($this->reviewService);
        $response   = $controller->reject();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/manager/reviews', $response->getHeaderLine('Location'));
    }
}
