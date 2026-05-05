<?php

declare(strict_types=1);

/**
 * Definición de rutas PSR-7/PSR-15 - Versión 12-Factor
 */

use App\Core\Cache;
use App\Core\Database;
use App\Core\Env;
use App\Core\Http\ResponseFactory;
use App\Core\MiddlewareFactory;
use App\Core\Queue;
use App\Core\Router;
use App\Core\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

$responseFactory = new ResponseFactory();
$mw = new MiddlewareFactory($responseFactory);
$router = new Router($responseFactory);
$router->setControllerNamespace('App\Http\Controllers');

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
$router->group(['prefix' => '/api/v1', 'middleware' => [$mw->requestLog(), $mw->cors(), $mw->rateLimit('api_public')]], function (Router $r): void {
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
    $baseDir = realpath(__DIR__ . '/../storage/uploads/animals');
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
// AUTENTICACIÓN (Middleware Guest)
// ============================================================================

/** @var array<int, MiddlewareInterface> $guestMiddleware */
$guestMiddleware = [$mw->guest()];
$router->group(['prefix' => '', 'middleware' => $guestMiddleware], function (Router $r) use ($mw) {
    $r->get('/login', 'Auth\AuthController@showLogin');
    $r->post('/login', 'Auth\AuthController@processLogin', [$mw->csrf(), $mw->rateLimit('login')]);
    $r->get('/registro', 'Auth\AuthController@showRegister');
    $r->post('/registro', 'Auth\AuthController@processRegister', [$mw->csrf(), $mw->rateLimit('registration')]);

    $r->get('/forgot-password', 'Auth\PasswordResetController@forgotPasswordForm');
    $r->post('/forgot-password', 'Auth\PasswordResetController@sendResetEmail', [$mw->csrf(), $mw->rateLimit('password_reset')]);
    $r->get('/reset-password', 'Auth\PasswordResetController@resetPasswordForm');
    $r->post('/reset-password', 'Auth\PasswordResetController@processReset', [$mw->csrf()]);
    $r->get('/verify-email', 'Auth\PasswordResetController@verifyEmail');
});

// ============================================================================
// USUARIOS AUTENTICADOS (Middleware Auth)
// ============================================================================

/** @var array<int, MiddlewareInterface> $authMiddleware */
$authMiddleware = [$mw->auth()];
$router->group(['middleware' => $authMiddleware], function (Router $r) use ($mw, $responseFactory) {
    $r->post('/logout', 'Auth\AuthController@logout', [$mw->csrf()]);
    $r->get('/profile', static fn(): ResponseInterface => $responseFactory->redirect('/perfil', 301));
    $r->get('/perfil', 'Shared\UserController@profile');
    $r->post('/profile/update', 'Shared\UserController@updateProfile', [$mw->csrf()]);
    $r->post('/profile/avatar', 'Shared\UserController@updateAvatar', [$mw->csrf()]);
    $r->get('/account/sessions', 'Auth\AccountController@sessions');
    $r->post('/account/sessions/revoke/{sessionId}', 'Auth\AccountController@revokeSession', [$mw->csrf()]);
    $r->post('/account/sessions/revoke-all', 'Auth\AccountController@revokeAllOther', [$mw->csrf()]);
    $r->get('/account/security', 'Auth\AccountController@security');
    $r->get('/account/change-password', 'Auth\AccountController@changePasswordForm');
    $r->post('/account/change-password', 'Auth\AccountController@processChangePassword', [$mw->csrf()]);
    $r->post('/account/delete', 'Auth\AccountController@deleteAccount', [$mw->csrf()]);

    // Avatar upload/delete
    $r->post('/account/avatar/upload', 'Shared\UserController@uploadAvatar', [$mw->csrf()]);
    $r->post('/account/avatar/delete', 'Shared\UserController@deleteAvatar', [$mw->csrf()]);

    $r->get('/reservas/confirmacion/{id}', 'Shared\ReservationController@confirmation');
    $r->get('/reservas/mis-reservas', 'Shared\ReservationController@userReservations');
    $r->get('/reservas/mis-reservas/{id}/cancelar', 'Shared\ReservationController@cancelConfirm');
    $r->post('/reservas/mis-reservas/{id}/cancel', 'Shared\ReservationController@cancelReservation', [$mw->csrf()]);
    $r->get('/account/delete', 'Auth\AccountController@showDeleteForm');
    $r->get('/user/waitlists', 'User\WaitlistController@index');
    $r->post('/user/waitlists/{id}/cancel', 'User\WaitlistController@cancel', [$mw->csrf()]);
    $r->get('/mis-favoritos', 'User\FavoriteController@index');
    $r->get('/carrito', 'User\CartController@index');
    $r->get('/loyalty/card', 'Public\LoyaltyController@card');
    $r->get('/reviews', static function () use ($responseFactory): ResponseInterface {
        return $responseFactory->redirect('/perfil#reviews', 301);
    });
    $r->post('/reviews', 'Shared\ReviewController@create', [$mw->csrf()]);
    $r->post('/reviews/update', 'Shared\ReviewController@update', [$mw->csrf()]);
    $r->post('/reviews/delete', 'Shared\ReviewController@delete', [$mw->csrf()]);
});

// ============================================================================
// API AUTENTICADA - rutas JSON (apiAuth = JSON 401, nunca redirige)
// ============================================================================

/** @var array<int, MiddlewareInterface> $apiAuthMiddleware */
$apiAuthMiddleware = [$mw->requestLog(), $mw->apiAuth()];
$router->group(['prefix' => '/api/v1', 'middleware' => $apiAuthMiddleware], function (Router $r) use ($mw) {
    $r->put('/favorites/{id}', 'Api\V1\FavoriteController@add', [$mw->csrf()]);
    $r->delete('/favorites/{id}', 'Api\V1\FavoriteController@remove', [$mw->csrf()]);
    $r->get('/favorites', 'Api\V1\FavoriteController@list');
    $r->post('/cart/items', 'Api\V1\CartController@add', [$mw->csrf()]);
    $r->delete('/cart/items/{id}', 'Api\V1\CartController@remove', [$mw->csrf()]);
    $r->patch('/cart/items/{id}', 'Api\V1\CartController@update', [$mw->csrf()]);
    $r->get('/cart', 'Api\V1\CartController@get');
    $r->delete('/cart/items', 'Api\V1\CartController@clear', [$mw->csrf()]);
    $r->get('/reservations/available', 'Api\V1\ReservationController@getAvailableSlots');
    $r->post('/reservations', 'Api\V1\ReservationController@create', [$mw->csrf(), $mw->idempotency()]);
    $r->post('/reservations/{id}/cancel', 'Api\V1\ReservationController@cancel', [$mw->csrf()]);
    $r->get('/reservations/{id}', 'Api\V1\ReservationController@show');
    $r->get('/user/reservations', 'Api\V1\ReservationController@userReservations');
    $r->get('/loyalty/validate/{code}', 'Api\V1\LoyaltyController@validateCode');
    $r->post('/loyalty/use', 'Api\V1\LoyaltyController@use', [$mw->csrf()]);
    $r->post('/loyalty/redeem', 'Api\V1\LoyaltyController@redeem', [$mw->csrf()]);
    // Waitlist join — requiere autenticación para evitar IDOR con user_id (S1-02)
    $r->post('/waitlists', 'Api\V1\WaitlistController@join', [$mw->csrf()]);

    // Perfil de usuario (FASE 2)
    $r->get('/user/profile', 'Api\V1\UserController@profile');
    $r->get('/user/stats', 'Api\V1\UserController@stats');
    $r->get('/user/reviews', 'Api\V1\UserController@reviews');
    $r->get('/user/avatar-options', 'Api\V1\UserController@avatarOptions');

    // Reseñas (FASE 2)
    $r->post('/reviews', 'Api\V1\ReviewController@create', [$mw->csrf()]);
    $r->put('/reviews/{id}', 'Api\V1\ReviewController@update', [$mw->csrf()]);
    $r->delete('/reviews/{id}', 'Api\V1\ReviewController@delete', [$mw->csrf()]);
});

// ============================================================================
// BACKOFFICE (FEATURE_BACKOFFICE) — admin / manager / supervisor
// ============================================================================

if (Env::get('FEATURE_BACKOFFICE', '1') === '1') {

    // ============================================================================
    // BACKOFFICE - ADMIN
    // ============================================================================

    $router->get('/admin', function () use ($responseFactory) {
        return $responseFactory->redirect('/admin/dashboard', 302);
    });

    /** @var array<int, MiddlewareInterface> $adminMiddleware */
    $adminMiddleware = [$mw->auth(), $mw->role('admin')];
    $router->group(['prefix' => '/admin', 'middleware' => $adminMiddleware], function (Router $r) use ($mw) {
        $r->get('/dashboard', 'Admin\DashboardController@index');
        $r->get('/users', 'Admin\UserController@index');
        $r->get('/users/create', 'Admin\UserController@create');
        $r->get('/users/{id}/edit', 'Admin\UserController@edit');

        $r->get('/roles', 'Admin\RoleController@index');

        $r->get('/cafes', 'Admin\CafeController@index');

        $r->get('/menu', 'Admin\MenuController@index');
        $r->get('/menu/create', 'Admin\MenuController@create');
        $r->get('/menu/{id}/edit', 'Admin\MenuController@edit');

        $r->get('/reviews', 'Admin\ReviewController@index');

        $r->get('/reservations', 'Admin\ReservationController@index');
        $r->get('/reservations/{id}', 'Admin\ReservationController@show');

        $r->get('/waitlists', 'Admin\WaitlistController@index');
        $r->get('/waitlists/{id}', 'Admin\WaitlistController@show');
        $r->post('/waitlists/{id}/cancel', 'Admin\WaitlistController@cancel', [$mw->csrf()]);

        $r->get('/animals', 'Admin\AnimalController@index');
        $r->get('/animals/create', 'Admin\AnimalController@create');
        $r->post('/animals', 'Admin\AnimalController@store', [$mw->csrf()]);
        $r->get('/animals/{id}/edit', 'Admin\AnimalController@edit');
        $r->post('/animals/{id}', 'Admin\AnimalController@update', [$mw->csrf()]);
        $r->post('/animals/{id}/delete', 'Admin\AnimalController@delete', [$mw->csrf()]);

        $r->get('/settings', 'Admin\SystemController@settings');
        $r->get('/logs', 'Admin\SystemController@logs');
        $r->get('/data-viewer', 'Admin\DataViewerController@index');

        $r->get('/logs/audit', 'Admin\AuditLogController@index');
        $r->get('/logs/auth', 'Admin\AuthLogController@index');

        $r->get('/newsletter', 'Admin\NewsletterController@index');
        $r->get('/loyalty', 'Admin\LoyaltyController@index');

        $r->get('/reports', 'Admin\ReportController@index');
        $r->get('/reports/export', 'Admin\ReportController@exportReportes');
    });

    // ============================================================================
    // BACKOFFICE - MANAGER
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $managerMiddleware */
    $managerMiddleware = [$mw->auth(), $mw->role(['admin', 'manager'])];
    $router->group(['prefix' => '/manager', 'middleware' => $managerMiddleware], function (Router $r) use ($mw) {
        $r->get('/dashboard', 'Manager\DashboardController@index');
        $r->get('/reservations', 'Manager\ReservationController@index');
        $r->get('/reviews', 'Manager\ReviewController@index');

        // Staff Management — vistas web (GETs)
        $r->get('/staff', 'Manager\StaffController@index', [$mw->ownsCafe()]);
        $r->get('/staff/{id:\d+}', 'Manager\StaffController@show', [$mw->ownsCafe()]);

        $r->get('/reports', 'Manager\ReportController@index');
        $r->get('/reports/export', 'Manager\ReportController@exportReportes');

        // Café Management — vista web (GET)
        $r->get('/cafe', 'Manager\CafeController@show', [$mw->ownsCafe()]);

        // Gestión de productos del café — vista web (GET)
        $r->get('/products', 'Manager\ProductController@index', [$mw->ownsCafe()]);
    });

    // ============================================================================
    // BACKOFFICE - SUPERVISOR
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $supervisorMiddleware */
    $supervisorMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'supervisor'])];
    $router->group(['prefix' => '/supervisor', 'middleware' => $supervisorMiddleware], function (Router $r) use ($mw) {
        $r->get('/dashboard', 'Supervisor\SupervisorController@index');
        $r->get('/assignments', 'Supervisor\SupervisorController@assignments');
        $r->post('/assignments', 'Supervisor\SupervisorController@createAssignment', [$mw->csrf()]);
    });

    // API Manager - Dashboard
    $router->get('/api/v1/manager/stats', 'Api\V1\ManagerController@stats', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/revenue/weekly', 'Api\V1\ManagerController@weeklyRevenue', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/animals/top', 'Api\V1\ManagerController@topAnimals', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/reservations/status', 'Api\V1\ManagerController@reservationStatus', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);

    // API Manager — mutaciones AJAX (FASE 4C)
    /** @var array<int, MiddlewareInterface> $managerApiMiddleware */
    $managerApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])];
    $router->group(['prefix' => '/api/v1/manager', 'middleware' => $managerApiMiddleware], function (Router $r) use ($mw): void {
        // Café
        $r->put('/cafe/capacity', 'Manager\CafeController@updateCapacity', [$mw->csrf(), $mw->ownsCafe()]);
        $r->put('/cafe/schedule', 'Manager\CafeController@updateSchedule', [$mw->csrf(), $mw->ownsCafe()]);
        $r->put('/cafe/settings', 'Manager\CafeController@updateSettings', [$mw->csrf(), $mw->ownsCafe()]);
        // Products
        $r->post('/products', 'Manager\ProductController@create', [$mw->csrf(), $mw->ownsCafe()]);
        $r->put('/products/{id}', 'Manager\ProductController@update', [$mw->csrf(), $mw->ownsCafe()]);
        $r->patch('/products/{id}/toggle', 'Manager\ProductController@toggleAvailability', [$mw->csrf(), $mw->ownsCafe()]);
        $r->delete('/products/{id}', 'Manager\ProductController@delete', [$mw->csrf(), $mw->ownsCafe()]);
        // Staff mutations
        $r->post('/staff/assign-shift', 'Manager\StaffController@assignShift', [$mw->csrf(), $mw->ownsCafe()]);
        $r->post('/staff/edit-permissions', 'Manager\StaffController@editPermissions', [$mw->csrf(), $mw->ownsCafe()]);
        $r->get('/staff/performance/{id}', 'Manager\StaffController@viewPerformance', [$mw->ownsCafe()]);
        // Reviews
        $r->post('/reviews/{id}/approve', 'Api\V1\Admin\ReviewApiController@approve', [$mw->csrf()]);
        $r->post('/reviews/{id}/reject', 'Api\V1\Admin\ReviewApiController@reject', [$mw->csrf()]);
    });

    // API Supervisor
    $router->post('/api/v1/supervisor/assignments', 'Api\V1\SupervisorController@assign', [$mw->cors(), $mw->apiAuth(), $mw->csrf()]);
    $router->get('/api/v1/supervisor/assignments', 'Api\V1\SupervisorController@list', [$mw->cors(), $mw->apiAuth()]);

    // API Tokens — gestión de Bearer tokens (requiere sesión activa para generarlos)
    $router->group(
        ['prefix' => '/api/v1/tokens', 'middleware' => [$mw->cors(), $mw->apiAuth()]],
        function (Router $r) use ($mw): void {
            $r->get('', 'Api\V1\TokenController@list');
            $r->post('', 'Api\V1\TokenController@create', [$mw->csrf()]);
            $r->delete('/{id}', 'Api\V1\TokenController@revoke', [$mw->csrf()]);
        }
    );

    // ============================================================================
    // API ADMIN — mutaciones AJAX
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $adminApiMiddleware */
    $adminApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin'])];
    $router->group(['prefix' => '/api/v1/admin', 'middleware' => $adminApiMiddleware], function (Router $r) use ($mw): void {
        // Users
        $r->post('/users', 'Api\V1\Admin\UserApiController@create', [$mw->csrf()]);
        $r->put('/users/{id}', 'Api\V1\Admin\UserApiController@update', [$mw->csrf()]);
        $r->delete('/users/{id}', 'Api\V1\Admin\UserApiController@delete', [$mw->csrf()]);
        $r->patch('/users/{id}/status', 'Api\V1\Admin\UserApiController@toggleActive', [$mw->csrf()]);

        // Cafes
        $r->post('/cafes', 'Api\V1\Admin\CafeApiController@create', [$mw->csrf()]);
        $r->put('/cafes/{id}', 'Api\V1\Admin\CafeApiController@update', [$mw->csrf()]);
        $r->delete('/cafes/{id}', 'Api\V1\Admin\CafeApiController@delete', [$mw->csrf()]);
        $r->patch('/cafes/{id}/status', 'Api\V1\Admin\CafeApiController@toggleStatus', [$mw->csrf()]);

        // Menu / Products
        $r->post('/menu', 'Api\V1\Admin\MenuApiController@create', [$mw->csrf()]);
        $r->put('/menu/{id}', 'Api\V1\Admin\MenuApiController@update', [$mw->csrf()]);
        $r->delete('/menu/{id}', 'Api\V1\Admin\MenuApiController@delete', [$mw->csrf()]);
        $r->patch('/menu/{id}/toggle', 'Api\V1\Admin\MenuApiController@toggleAvailability', [$mw->csrf()]);

        // Reviews
        $r->post('/reviews/{id}/approve', 'Api\V1\Admin\ReviewApiController@approve', [$mw->csrf()]);
        $r->post('/reviews/{id}/reject', 'Api\V1\Admin\ReviewApiController@reject', [$mw->csrf()]);
        $r->delete('/reviews/{id}', 'Api\V1\Admin\ReviewApiController@delete', [$mw->csrf()]);

        // Reservations
        $r->post('/reservations/{id}/confirm', 'Api\V1\Admin\ReservationApiController@confirm', [$mw->csrf()]);
        $r->post('/reservations/{id}/cancel', 'Api\V1\Admin\ReservationApiController@cancel', [$mw->csrf()]);

        // Settings
        $r->get('/settings', 'Api\V1\Admin\SystemApiController@getSettingsData');
        $r->put('/settings/{group}', 'Api\V1\Admin\SystemApiController@updateSettingsGroup', [$mw->csrf()]);
        $r->post('/settings/test-email', 'Api\V1\Admin\SystemApiController@testEmail', [$mw->csrf()]);

        // Logs — Audit (GET, sin CSRF)
        $r->get('/logs/audit', 'Api\V1\Admin\LogApiController@auditLogs');
        $r->get('/logs/audit/export', 'Api\V1\Admin\LogApiController@auditExport');

        // Logs — Auth (GET, sin CSRF)
        $r->get('/logs/auth', 'Api\V1\Admin\LogApiController@authLogs');
        $r->get('/logs/auth/suspicious-count', 'Api\V1\Admin\LogApiController@authSuspiciousCount');
        $r->get('/logs/auth/export', 'Api\V1\Admin\LogApiController@authExport');

        // Cache & Security
        $r->post('/cache/clear', 'Api\V1\Admin\SystemApiController@clearCache', [$mw->csrf()]);
        $r->post('/security/block-ip', 'Api\V1\Admin\LogApiController@blockIp', [$mw->csrf()]);

        // Newsletter
        $r->delete('/newsletter/subscribers/{email}', 'Api\V1\Admin\NewsletterApiController@delete', [$mw->csrf()]);
        $r->get('/newsletter/export', 'Api\V1\Admin\NewsletterApiController@export');

        // Loyalty
        $r->get('/loyalty/stats', 'Api\V1\Admin\LoyaltyApiController@stats');
        $r->get('/loyalty/cards', 'Api\V1\Admin\LoyaltyApiController@cards');
        $r->get('/loyalty/catalog', 'Api\V1\Admin\LoyaltyApiController@catalog');
        $r->patch('/loyalty/catalog/{id}/toggle', 'Api\V1\Admin\LoyaltyApiController@toggleCatalogItem', [$mw->csrf()]);
        $r->get('/loyalty/redemptions', 'Api\V1\Admin\LoyaltyApiController@redemptions');
    });
} // end FEATURE_BACKOFFICE

// ============================================================================
// BACKOFFICE - OPS (FEATURE_OPS) — reception / kitchen
// ============================================================================

if (Env::get('FEATURE_OPS', '1') === '1') {

    // ============================================================================
    // BACKOFFICE - RECEPTION
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $receptionMiddleware */
    $receptionMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'supervisor', 'reception'])];
    $router->group(['prefix' => '/ops/reception', 'middleware' => $receptionMiddleware], function (Router $r): void {
        $r->get('', 'Reception\ReceptionController@index');
        $r->get('/reservations', 'Reception\ReceptionController@todayReservations');
    });

    // ============================================================================
    // BACKOFFICE - KITCHEN
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $kitchenMiddleware */
    $kitchenMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'kitchen', 'supervisor'])];
    $router->group(['prefix' => '/ops/kitchen', 'middleware' => $kitchenMiddleware], function (Router $r): void {
        $r->get('', 'Kitchen\KitchenController@index');
        $r->get('/history', 'Kitchen\KitchenController@history');
        $r->get('/orders', 'Kitchen\KitchenController@activeOrders');
    });

    // API Ops — Reception + Kitchen (FASE 4D)
    /** @var array<int, MiddlewareInterface> $opsReceptionApiMiddleware */
    $opsReceptionApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager', 'supervisor', 'reception'])];
    $router->group(['prefix' => '/api/v1/ops/reception', 'middleware' => $opsReceptionApiMiddleware], function (Router $r) use ($mw): void {
        $r->get('/reservations', 'Api\V1\Ops\ReceptionApiController@todayReservations');
        $r->post('/reservations/{id}/checkin', 'Api\V1\Ops\ReceptionApiController@checkIn', [$mw->csrf()]);
        $r->post('/reservations/{id}/checkout', 'Api\V1\Ops\ReceptionApiController@checkOut', [$mw->csrf()]);
    });

    /** @var array<int, MiddlewareInterface> $opsKitchenApiMiddleware */
    $opsKitchenApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager', 'kitchen', 'supervisor'])];
    $router->group(['prefix' => '/api/v1/ops/kitchen', 'middleware' => $opsKitchenApiMiddleware], function (Router $r) use ($mw): void {
        $r->get('/orders', 'Api\V1\Ops\KitchenApiController@activeOrders');
        $r->post('/orders/{id}/complete', 'Api\V1\Ops\KitchenApiController@completeOrder', [$mw->csrf()]);
    });
} // end FEATURE_OPS

// ============================================================================
// BACKOFFICE - KEEPER (FEATURE_KEEPER)
// ============================================================================

if (Env::get('FEATURE_KEEPER', '1') === '1') {

    /** @var array<int, MiddlewareInterface> $keeperMiddleware */
    $keeperMiddleware = [$mw->auth(), $mw->role(['admin', 'keeper']), $mw->ownsCafe()];

    // Dashboard del keeper: accesible a manager y supervisor sin restricción de café
    $router->get('/keeper/dashboard', 'Keeper\AnimalDashboardController@dashboard', [
        $mw->auth(),
        $mw->role(['admin', 'keeper', 'manager', 'supervisor']),
    ]);

    $router->group(['prefix' => '/keeper', 'middleware' => $keeperMiddleware], function (Router $r) use ($mw) {
        $r->get('/animals', 'Keeper\AnimalDashboardController@index');
        $r->get('/animals/{id}', 'Keeper\AnimalDashboardController@show');

        // Health Checks - Sistema de chequeos diarios de salud (flujo web)
        $r->get('/health-checks', 'Keeper\HealthCheckController@index');
        $r->get('/health-checks/create/{animalId}', 'Keeper\HealthCheckController@create');
        $r->post('/health-checks', 'Keeper\HealthCheckController@store', [$mw->csrf()]);
        $r->get('/health-checks/{checkId}', 'Keeper\HealthCheckController@show');
        $r->get('/health-checks/history/{animalId}', 'Keeper\HealthCheckController@history');

        // Incidentes de animales - flujo web
        $r->get('/incidents', 'Keeper\AnimalIncidentController@index');
        $r->get('/incidents/create', 'Keeper\AnimalIncidentController@create');
        $r->post('/incidents', 'Keeper\AnimalIncidentController@store', [$mw->csrf()]);
        $r->get('/incidents/{id}', 'Keeper\AnimalIncidentController@show');
        $r->get('/incidents/{id}/edit', 'Keeper\AnimalIncidentController@edit');
        $r->post('/incidents/{id}', 'Keeper\AnimalIncidentController@update', [$mw->csrf()]);
        $r->post('/incidents/{incidentId}/resolve', 'Keeper\AnimalIncidentController@resolve', [$mw->csrf()]);

        // Turnos del keeper
        $r->get('/schedule', 'Keeper\ScheduleController@index');
    });

    // API Keeper — mutaciones AJAX (FASE 4B)
    /** @var array<int, MiddlewareInterface> $keeperApiMiddleware */
    $keeperApiMiddleware = [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'keeper'])];
    $router->group(['prefix' => '/api/v1/keeper', 'middleware' => $keeperApiMiddleware], function (Router $r) use ($mw): void {
        $r->post('/animals/{id}/photo', 'Api\V1\KeeperApiController@uploadPhoto');
        $r->post('/animals/{id}/care-log', 'Api\V1\KeeperApiController@createCareLog', [$mw->csrf()]);
        $r->patch('/animals/{id}/health', 'Api\V1\KeeperApiController@updateHealth', [$mw->csrf()]);
        $r->patch('/animals/{id}/toggle', 'Api\V1\KeeperApiController@toggleActive', [$mw->csrf()]);
        $r->post('/incidents', 'Api\V1\KeeperApiController@createIncident', [$mw->csrf()]);
        $r->patch('/incidents/{id}/resolve', 'Api\V1\KeeperApiController@resolveIncident', [$mw->csrf()]);
    });
} // end FEATURE_KEEPER

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
        'di_compiled' => is_file(__DIR__ . '/../storage/cache/di/CompiledContainer.php'),
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

$router->get('/error/400', 'Shared\ErrorController@badRequest');
$router->get('/error/401', 'Shared\ErrorController@unauthorized');
$router->get('/error/404', 'Shared\ErrorController@notFound');
$router->get('/error/403', 'Shared\ErrorController@forbidden');
$router->get('/error/500', 'Shared\ErrorController@serverError');
$router->get('/error/419', 'Shared\ErrorController@pageExpired');
$router->get('/error/429', 'Shared\ErrorController@rateLimited');
$router->get('/error/503', 'Shared\ErrorController@serviceUnavailable');
$router->get('/redirect', 'Shared\ErrorController@redirect');

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

return $router;
