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

// Ruta alternativa histórica para formulario de recuperación (renderiza el mismo controlador)
$router->get('/auth/forgot-password', 'Auth\PasswordResetController@forgotPasswordForm');

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
$router->post('/newsletter/subscribe', 'Public\NewsletterController@subscribe', [$mw->rateLimit('newsletter')]);
$router->get('/newsletter/verify', 'Public\NewsletterController@verify');
$router->get('/newsletter/unsubscribe', 'Public\NewsletterController@unsubscribe');

// Reservas públicas
$router->get('/reservar', 'Shared\ReservationController@index');
$router->get('/reservas', 'Shared\ReservationController@index');

// API pública — todas bajo /api/v1/
$router->group(['prefix' => '/api/v1', 'middleware' => [$mw->cors()]], function (Router $r): void {
    $r->get('/menu/alergenos', 'Api\V1\MenuController@allergens');
    $r->get('/menu/productos', 'Api\V1\MenuController@products');
    $r->post('/menu/view-product', 'Api\V1\MenuController@viewProduct');

    $r->get('/holidays', 'Api\V1\HolidayController@getHolidays');
    $r->get('/holidays/check', 'Api\V1\HolidayController@checkHoliday');
    $r->get('/time-slots/available', 'Api\V1\TimeSlotController@available');
    $r->get('/time-slots/stats', 'Api\V1\TimeSlotController@stats');

    // Waitlist API
    $r->post('/waitlist/join', 'Api\V1\WaitlistController@join');
    $r->get('/waitlist/position/{token}', 'Api\V1\WaitlistController@position');
    $r->post('/waitlist/confirm/{token}', 'Api\V1\WaitlistController@confirm');
});

// Waitlist Views
$router->get('/waitlist/status/{token}', 'Public\WaitlistViewController@status');
$router->get('/waitlist/confirm/{token}', 'Public\WaitlistViewController@confirmView');
$router->post('/waitlist/confirm/{token}', 'Public\WaitlistViewController@confirmSubmit');

// Cookies API, cart guest y newsletter — todos bajo /api/v1/
$router->group(['prefix' => '/api/v1'], function (Router $r): void {
    $r->post('/cookies/accept', 'Api\V1\CookieController@accept');
    $r->post('/cookies/reject', 'Api\V1\CookieController@reject');
    $r->post('/cookies/update', 'Api\V1\CookieController@update');
    $r->post('/cookies/save-filters', 'Api\V1\CookieController@saveFilters');
    $r->get('/cookies/get-filters', 'Api\V1\CookieController@getFilters');
    $r->post('/cookies/clear-filters', 'Api\V1\CookieController@clearFilters');
    $r->post('/cookies/save-dietary', 'Api\V1\CookieController@saveDietary');
    $r->post('/cookies/recently-viewed/add', 'Api\V1\CookieController@addRecentlyViewed');
    $r->get('/cookies/recently-viewed/data', 'Api\V1\CookieController@getRecentlyViewedData');
    $r->delete('/cookies/recently-viewed/clear', 'Api\V1\CookieController@clearRecentlyViewed');
    $r->get('/cookies/newsletter-prompted', 'Api\V1\CookieController@newsletterPrompted');
    $r->post('/cookies/newsletter-prompted', 'Api\V1\CookieController@markNewsletterPrompted');
    $r->get('/cart/guest', 'Api\V1\CartController@guest');
    $r->post('/newsletter/subscribe', 'Api\V1\NewsletterApiController@subscribe');
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
$router->group(['middleware' => $authMiddleware], function (Router $r) use ($mw) {
    $r->post('/logout', 'Auth\AuthController@logout', [$mw->csrf()]);
    $r->get('/profile', 'Shared\UserController@profile');
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

    $r->post('/reservas/crear', 'Shared\ReservationController@create', [$mw->csrf()]);
    $r->get('/reservas/confirmacion/{id}', 'Shared\ReservationController@confirmation');
    $r->get('/reservas/mis-reservas', 'Shared\ReservationController@userReservations');
    $r->post('/reservas/{id}/cancelar', 'Shared\ReservationController@cancel', [$mw->csrf()]);
    $r->get('/user/waitlists', 'User\WaitlistController@index');
    $r->get('/mis-favoritos', 'User\FavoriteController@index');
    $r->get('/carrito', 'User\CartController@index');
    $r->get('/loyalty/card', 'Public\LoyaltyController@card');
    $r->get('/reviews', 'Shared\ReviewController@index');
    $r->post('/reviews', 'Shared\ReviewController@create', [$mw->csrf()]);
    $r->post('/reviews/update', 'Shared\ReviewController@update', [$mw->csrf()]);
    $r->post('/reviews/delete', 'Shared\ReviewController@delete', [$mw->csrf()]);
});

// ============================================================================
// API AUTENTICADA - rutas JSON (apiAuth = JSON 401, nunca redirige)
// ============================================================================

/** @var array<int, MiddlewareInterface> $apiAuthMiddleware */
$apiAuthMiddleware = [$mw->apiAuth()];
$router->group(['prefix' => '/api/v1', 'middleware' => $apiAuthMiddleware], function (Router $r) use ($mw) {
    $r->post('/favorites/toggle', 'Api\V1\FavoriteController@toggle', [$mw->csrf()]);
    $r->get('/favorites', 'Api\V1\FavoriteController@list');
    $r->post('/cart/add', 'Api\V1\CartController@add', [$mw->csrf()]);
    $r->post('/cart/remove', 'Api\V1\CartController@remove', [$mw->csrf()]);
    $r->post('/cart/update', 'Api\V1\CartController@update', [$mw->csrf()]);
    $r->get('/cart', 'Api\V1\CartController@get');
    $r->post('/cart/clear', 'Api\V1\CartController@clear', [$mw->csrf()]);
    $r->get('/reservations/available', 'Api\V1\ReservationController@getAvailableSlots');
    $r->post('/reservations/create', 'Api\V1\ReservationController@create', [$mw->csrf()]);
    $r->get('/loyalty/validate/{code}', 'Api\V1\LoyaltyController@validateCode');
    $r->post('/loyalty/use', 'Api\V1\LoyaltyController@use', [$mw->csrf()]);
    $r->post('/loyalty/redeem', 'Api\V1\LoyaltyController@redeem', [$mw->csrf()]);
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
        $r->post('/users', 'Admin\UserController@store', [$mw->csrf()]);
        $r->get('/users/{id}/edit', 'Admin\UserController@edit');
        $r->post('/users/{id}', 'Admin\UserController@update', [$mw->csrf()]);
        $r->post('/users/{id}/delete', 'Admin\UserController@delete', [$mw->csrf()]);
        $r->post('/users/{id}/toggle-status', 'Admin\UserController@toggleStatus', [$mw->csrf()]);

        $r->get('/roles', 'Admin\RoleController@index');
        $r->get('/roles/create', 'Admin\RoleController@create');
        $r->post('/roles', 'Admin\RoleController@store', [$mw->csrf()]);
        $r->get('/roles/{id}/edit', 'Admin\RoleController@edit');
        $r->post('/roles/{id}', 'Admin\RoleController@update', [$mw->csrf()]);
        $r->post('/roles/{id}/delete', 'Admin\RoleController@delete', [$mw->csrf()]);

        $r->get('/cafes', 'Admin\CafeController@index');
        $r->post('/cafes/create', 'Admin\CafeController@create', [$mw->csrf()]);
        $r->post('/cafes/{id}/edit', 'Admin\CafeController@update', [$mw->csrf()]);
        $r->post('/cafes/{id}/toggle-active', 'Admin\CafeController@toggleStatus', [$mw->csrf()]);
        $r->post('/cafes/{id}/delete', 'Admin\CafeController@delete', [$mw->csrf()]);

        $r->get('/menu', 'Admin\MenuController@index');
        $r->get('/menu/create', 'Admin\MenuController@create');
        $r->post('/menu', 'Admin\MenuController@store', [$mw->csrf()]);
        $r->get('/menu/{id}/edit', 'Admin\MenuController@edit');
        $r->post('/menu/{id}', 'Admin\MenuController@update', [$mw->csrf()]);
        $r->post('/menu/{id}/toggle', 'Admin\MenuController@toggleAvailability', [$mw->csrf()]);
        $r->post('/menu/{id}/delete', 'Admin\MenuController@delete', [$mw->csrf()]);

        $r->get('/reviews', 'Admin\ReviewController@index');
        $r->post('/reviews/{id}/approve', 'Admin\ReviewController@approve', [$mw->csrf()]);
        $r->post('/reviews/{id}/reject', 'Admin\ReviewController@reject', [$mw->csrf()]);
        $r->post('/reviews/{id}/delete', 'Admin\ReviewController@delete', [$mw->csrf()]);

        $r->get('/reservations', 'Admin\ReservationController@index');
        $r->get('/reservations/{id}', 'Admin\ReservationController@show');
        $r->post('/reservations/{id}/confirm', 'Admin\ReservationController@confirm', [$mw->csrf()]);
        $r->post('/reservations/{id}/cancel', 'Admin\ReservationController@cancel', [$mw->csrf()]);

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
        $r->post('/settings', 'Admin\SystemController@updateSettingsGroup', [$mw->csrf()]);
        $r->get('/logs', 'Admin\SystemController@logs');
        $r->get('/data-viewer', 'Admin\DataViewerController@index');

        $r->get('/logs/audit', 'Admin\AuditLogController@index');
        $r->get('/logs/audit/export', 'Admin\AuditLogController@export');
        $r->get('/logs/auth', 'Admin\AuthLogController@index');
        $r->get('/logs/auth/suspicious-count', 'Admin\AuthLogController@suspiciousCount');
        $r->get('/logs/auth/export', 'Admin\AuthLogController@export');
        $r->post('/security/block-ip', 'Admin\AuthLogController@blockIpStub', [$mw->csrf()]);

        $r->get('/reports', 'Admin\ReportController@index');
        $r->get('/reports/export', 'Admin\ReportController@exportReportes');

        $r->post('/cache/clear', 'Admin\SystemController@clearCache', [$mw->csrf()]);
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
        $r->post('/reviews/{id:\d+}/approve', 'Manager\ReviewController@approve', [$mw->csrf()]);
        $r->post('/reviews/{id:\d+}/reject', 'Manager\ReviewController@reject', [$mw->csrf()]);

        // Staff Management (requiere ownership sobre café asignado)
        $r->get('/staff', 'Manager\StaffController@index', [$mw->ownsCafe()]);
        $r->get('/staff/{id:\d+}', 'Manager\StaffController@show', [$mw->ownsCafe()]);
        $r->post('/staff/assign-shift', 'Manager\StaffController@assignShift', [$mw->ownsCafe(), $mw->csrf()]);
        $r->post('/staff/edit-permissions', 'Manager\StaffController@editPermissions', [$mw->ownsCafe(), $mw->csrf()]);
        $r->get('/staff/performance/{id:\d+}', 'Manager\StaffController@viewPerformance', [$mw->ownsCafe()]);

        $r->get('/reports', 'Manager\ReportController@index');
        $r->get('/reports/export', 'Manager\ReportController@exportReportes');

        // Café Management (requiere ownership sobre café asignado)
        $r->get('/cafe', 'Manager\CafeController@show', [$mw->ownsCafe()]);
        $r->post('/cafe/capacity', 'Manager\CafeController@updateCapacity', [$mw->ownsCafe(), $mw->csrf()]);
        $r->post('/cafe/schedule', 'Manager\CafeController@updateSchedule', [$mw->ownsCafe(), $mw->csrf()]);
        $r->post('/cafe/settings', 'Manager\CafeController@updateSettings', [$mw->ownsCafe(), $mw->csrf()]);

        // Gestión de productos del café
        $r->get('/products', 'Manager\ProductController@index', [$mw->ownsCafe()]);
        $r->post('/products/create', 'Manager\ProductController@create', [$mw->ownsCafe(), $mw->csrf()]);
        $r->post('/products/{id:\d+}/update', 'Manager\ProductController@update', [$mw->ownsCafe(), $mw->csrf()]);
        $r->post('/products/{id:\d+}/toggle', 'Manager\ProductController@toggleAvailability', [$mw->ownsCafe(), $mw->csrf()]);
        $r->post('/products/{id:\d+}/delete', 'Manager\ProductController@delete', [$mw->ownsCafe(), $mw->csrf()]);
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
    $router->get('/api/v1/manager/dashboard/stats', 'Api\V1\ManagerController@stats', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/dashboard/weekly-revenue', 'Api\V1\ManagerController@weeklyRevenue', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/dashboard/top-animals', 'Api\V1\ManagerController@topAnimals', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);
    $router->get('/api/v1/manager/dashboard/reservation-status', 'Api\V1\ManagerController@reservationStatus', [$mw->cors(), $mw->apiAuth(), $mw->apiRole(['admin', 'manager'])]);

    // API Supervisor
    $router->post('/api/v1/supervisor/assign', 'Api\V1\SupervisorController@assign', [$mw->cors(), $mw->apiAuth(), $mw->csrf()]);
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
    $router->group(['prefix' => '/ops/reception', 'middleware' => $receptionMiddleware], function (Router $r) use ($mw) {
        $r->get('', 'Reception\ReceptionController@index');
        $r->get('/reservations', 'Reception\ReceptionController@todayReservations');
        $r->post('/reservations/{id}/checkin', 'Reception\ReceptionController@checkIn', [$mw->csrf()]);
        $r->post('/reservations/{id}/checkout', 'Reception\ReceptionController@checkOut', [$mw->csrf()]);
    });

    // ============================================================================
    // BACKOFFICE - KITCHEN
    // ============================================================================

    /** @var array<int, MiddlewareInterface> $kitchenMiddleware */
    $kitchenMiddleware = [$mw->auth(), $mw->role(['admin', 'manager', 'kitchen', 'supervisor'])];
    $router->group(['prefix' => '/ops/kitchen', 'middleware' => $kitchenMiddleware], function (Router $r) use ($mw) {
        $r->get('', 'Kitchen\KitchenController@index');
        $r->get('/history', 'Kitchen\KitchenController@history');
        $r->get('/orders', 'Kitchen\KitchenController@activeOrders');
        $r->post('/orders/{id}/complete', 'Kitchen\KitchenController@completeOrder', [$mw->csrf()]);
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
        $r->post('/animals/{id}/feeding', 'Keeper\AnimalCareController@recordFeeding', [$mw->csrf()]);
        $r->post('/animals/{id}/health', 'Keeper\AnimalCareController@recordHealth', [$mw->csrf()]);

        // Health Checks - Sistema de chequeos diarios de salud
        $r->get('/health-checks', 'Keeper\HealthCheckController@index');
        $r->get('/health-checks/create/{animalId}', 'Keeper\HealthCheckController@create');
        $r->post('/health-checks', 'Keeper\HealthCheckController@store', [$mw->csrf()]);
        $r->get('/health-checks/{checkId}', 'Keeper\HealthCheckController@show');
        $r->get('/health-checks/history/{animalId}', 'Keeper\HealthCheckController@history');

        // Toggle de estado activo/descansando de un animal
        $r->post('/animals/{id}/toggle', 'Keeper\AnimalCareController@toggleActive', [$mw->csrf()]);

        // Incidentes de animales - flujo web
        $r->get('/incidents', 'Keeper\AnimalIncidentController@index');
        $r->get('/incidents/create', 'Keeper\AnimalIncidentController@create');
        $r->post('/incidents', 'Keeper\AnimalIncidentController@store', [$mw->csrf()]);
        $r->get('/incidents/{id}', 'Keeper\AnimalIncidentController@show');
        $r->post('/incidents/{id}/resolve', 'Keeper\AnimalIncidentController@resolve', [$mw->csrf()]);
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
        $checks['queue'] = ['status' => 'ok', 'pending_jobs' => $queueSize];
    } catch (Throwable $e) {
        $checks['queue'] = ['status' => 'degraded', 'error' => $e->getMessage()];
    }

    return $responseFactory->json([
        'status' => $status,
        'timestamp' => date('c'),
        'version' => '1.0.0',
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

return $router;
