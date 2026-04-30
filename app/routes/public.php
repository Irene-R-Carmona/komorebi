<?php

declare(strict_types=1);

use App\Core\Cache;
use App\Core\Database;
use App\Core\Queue;
use App\Core\Router;
use App\Core\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** @var \App\Core\Router $router */
/** @var \App\Core\MiddlewareFactory $mw */
/** @var \App\Core\Http\ResponseFactory $responseFactory */

$renderView = static function (string $template, array $data = [], array $styles = [], ?string $layout = 'main') use ($responseFactory): ResponseInterface {
    ob_start();
    View::render($template, $data, $styles, $layout);
    $content = ob_get_clean();

    return $responseFactory->html($content);
};

// ============================================================================
// RUTAS PÚBLICAS
// ============================================================================

$router->get('/', 'Public\HomeController@index');
$router->get('/cafes', 'Public\CafeController@index');
$router->get('/cafes/{slug}', 'Public\CafeController@show');
$router->get('/menu', 'Public\MenuController@index');
$router->get('/quiz', 'Public\QuizController@index');
$router->post('/quiz/resultado', 'Public\QuizController@resultado', [$mw->csrf()]);

// Páginas estáticas
$router->get('/historia', 'Public\PageController@historia');
$router->get('/faq', 'Public\PageController@faq');
$router->get('/contacto', 'Public\PageController@contacto');

// Páginas legales
$router->get('/legal/privacidad', function () use ($renderView) {
    return $renderView('legal/privacy', ['titulo' => 'Política de Privacidad'], ['static-pages.css']);
});

$router->get('/legal/cookies', function () use ($renderView) {
    return $renderView('legal/cookies', ['titulo' => 'Política de Cookies'], ['static-pages.css']);
});

$router->get('/legal/terminos', function () use ($renderView) {
    return $renderView('legal/terms', ['titulo' => 'Términos y Condiciones'], ['static-pages.css']);
});

// Newsletter
$router->get('/newsletter/verify', 'Public\NewsletterController@verify');
$router->get('/newsletter/unsubscribe', 'Public\NewsletterController@unsubscribe');

// Reservas públicas
$router->get('/reservar', 'Shared\ReservationController@index');
$router->get('/reservas', 'Shared\ReservationController@index');

// API pública — todas bajo /api/v1/
$router->group(['prefix' => '/api/v1', 'middleware' => [$mw->requestLog(), $mw->cors()]], function (Router $r): void {
    $r->get('/menu/alergenos', 'Api\V1\MenuController@allergens');
    $r->get('/menu/productos', 'Api\V1\MenuController@products');
    $r->get('/menu/products/{id}', 'Api\V1\MenuController@getProduct');

    $r->get('/holidays', 'Api\V1\HolidayController@getHolidays');
    $r->get('/holidays/{date}', 'Api\V1\HolidayController@checkHoliday');
    $r->get('/time-slots/available', 'Api\V1\TimeSlotController@available');
    $r->get('/time-slots/stats', 'Api\V1\TimeSlotController@stats');

    // Waitlist API — position y confirm son públicas (token como auth)
    $r->get('/waitlists/{token}', 'Api\V1\WaitlistController@position');
    $r->post('/waitlists/{token}/confirmations', 'Api\V1\WaitlistController@confirm');

    // Cafés y pases (FASE 2) — cacheables públicamente
    $r->get('/cafes', 'Api\V1\CafeController@index');
    $r->get('/cafes/{slug}', 'Api\V1\CafeController@show');
    $r->get('/passes', 'Api\V1\PassController@index');
});

// Waitlist Views
$router->get('/waitlist/status/{token}', 'Public\WaitlistViewController@status');
$router->get('/waitlist/confirm/{token}', 'Public\WaitlistViewController@confirmView');
$router->post('/waitlist/confirm/{token}', 'Public\WaitlistViewController@confirmSubmit', [$mw->csrf()]);

// Cookies API, cart guest y newsletter — todos bajo /api/v1/
$router->group(['prefix' => '/api/v1', 'middleware' => [$mw->requestLog()]], function (Router $r): void {
    $r->patch('/cookies', 'Api\V1\CookieController@consent');
    $r->get('/cookies/filters', 'Api\V1\CookieController@getFilters');
    $r->put('/cookies/filters', 'Api\V1\CookieController@saveFilters');
    $r->delete('/cookies/filters', 'Api\V1\CookieController@clearFilters');
    $r->put('/cookies/dietary', 'Api\V1\CookieController@saveDietary');
    $r->post('/cookies/recently-viewed', 'Api\V1\CookieController@addRecentlyViewed');
    $r->get('/cookies/recently-viewed/data', 'Api\V1\CookieController@getRecentlyViewedData');
    $r->delete('/cookies/recently-viewed', 'Api\V1\CookieController@clearRecentlyViewed');
    $r->get('/cookies/newsletter-prompted', 'Api\V1\CookieController@newsletterPrompted');
    $r->post('/cookies/newsletter-prompted', 'Api\V1\CookieController@markNewsletterPrompted');
    $r->get('/cart/guest', 'Api\V1\CartController@guest');
    $r->post('/newsletter/subscriptions', 'Api\V1\NewsletterApiController@subscribe');
});

// Fotos de animales — servicio público de ficheros (sin auth)
$router->get('/uploads/animals/{filename}', function (ServerRequestInterface $request) use ($responseFactory): ResponseInterface {
    $filename = (string) ($request->getAttribute('filename') ?? '');

    // 1. Solo caracteres seguros — sin path traversal
    if ($filename === '' || !preg_match('/^[a-zA-Z0-9_\-.]+$/', $filename)) {
        return $responseFactory->createResponse(404);
    }

    // 2. Solo extensiones de imagen permitidas
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return $responseFactory->createResponse(404);
    }

    // 3. Path seguro contra traversal
    $baseDir = realpath(__DIR__ . '/../../storage/uploads/animals');
    if ($baseDir === false) {
        return $responseFactory->createResponse(404);
    }
    $filepath = $baseDir . \DIRECTORY_SEPARATOR . $filename;
    $realFile = realpath($filepath);
    if ($realFile === false || !str_starts_with($realFile, $baseDir . \DIRECTORY_SEPARATOR)) {
        return $responseFactory->createResponse(404);
    }
    if (!is_file($realFile)) {
        return $responseFactory->createResponse(404);
    }

    // 4. Servir con Content-Type correcto + cache
    $content = file_get_contents($realFile);
    if ($content === false) {
        return $responseFactory->createResponse(500);
    }

    $contentTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    $response = $responseFactory->createResponse(200)
        ->withHeader('Content-Type', $contentTypes[$ext])
        ->withHeader('Cache-Control', 'public, max-age=86400')
        ->withHeader('Content-Length', (string) strlen($content));
    $response->getBody()->write($content);

    return $response;
});

// ============================================================================
// CORS Preflight — OPTIONS catch-all para /api/v1/
// ============================================================================

$corsOnly = [$mw->cors()];
$router->options('/api/v1/{resource}', fn () => '', $corsOnly);
$router->options('/api/v1/{resource}/{id}', fn () => '', $corsOnly);
$router->options('/api/v1/{resource}/{sub}/{id}', fn () => '', $corsOnly);

// ============================================================================
// HEALTH CHECK
// ============================================================================

$router->get('/health', function () use ($responseFactory) {
    $status = 'healthy';
    $checks = [];
    $httpCode = 200;

    try {
        $db = Database::getConnection();
        $db->query('SELECT 1');
        $checks['database'] = 'ok';
    } catch (Throwable) {
        $checks['database'] = 'error';
        $status = 'unhealthy';
        $httpCode = 503;
    }

    try {
        $redis = Cache::getRedis();
        $checks['redis'] = $redis->ping() ? 'ok' : 'error';
    } catch (Throwable) {
        $checks['redis'] = 'error';
        $status = 'unhealthy';
        $httpCode = 503;
    }

    try {
        $queueSize = Queue::size();
        $failedSize = Queue::size('failed');
        $queueStatus = 'ok';

        if ($failedSize > 50) {
            $queueStatus = 'degraded';
            $status = $status === 'healthy' ? 'degraded' : $status;
            $httpCode = $httpCode === 200 ? 503 : $httpCode;
        }

        $checks['queue'] = ['status' => $queueStatus, 'pending_jobs' => $queueSize, 'failed_jobs' => $failedSize];
    } catch (Throwable $e) {
        $checks['queue'] = ['status' => 'degraded', 'error' => $e->getMessage()];
    }

    // --- Runtime metrics (FrankenPHP Worker Mode + OPcache + PHP-DI) --------
    $opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
    $checks['runtime'] = [
        'worker_mode' => function_exists('frankenphp_handle_request'),
        'php_version' => PHP_VERSION,
        'opcache_enabled' => is_array($opcacheStatus) && ($opcacheStatus['opcache_enabled'] ?? false),
        'jit_active' => is_array($opcacheStatus) && ($opcacheStatus['jit']['on'] ?? false),
        'di_compiled' => is_file(__DIR__ . '/../../storage/cache/di/CompiledContainer.php'),
        'preloaded_scripts' => is_array($opcacheStatus)
            ? (int) ($opcacheStatus['preload_statistics']['num_cached_scripts'] ?? 0)
            : 0,
    ];

    return $responseFactory->json([
        'status' => $status,
        'timestamp' => date('c'),
        'version' => \App\Core\Env::get('APP_VERSION', 'unknown'),
        'checks' => $checks,
    ], $httpCode);
});

// ============================================================================
// ERROR HANDLERS
// ============================================================================

$router->get('/error/404', 'Shared\ErrorController@notFound');
$router->get('/error/403', 'Shared\ErrorController@forbidden');
$router->get('/error/500', 'Shared\ErrorController@serverError');

$router->setNotFoundHandler(function () use ($responseFactory): ResponseInterface {
    $requestedPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    ob_start();
    View::render('errors/404', [
        'titulo' => '404 - Página no encontrada',
        'requestedPath' => $requestedPath,
        'suggestedLink' => ['href' => '/', 'label' => 'Volver al inicio'],
    ], [], 'errors');
    $html = ob_get_clean();

    return $responseFactory->html($html ?: '', 404);
});
