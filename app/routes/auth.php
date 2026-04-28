<?php

declare(strict_types=1);

use App\Core\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

/** @var \App\Core\Router $router */
/** @var \App\Core\MiddlewareFactory $mw */
/** @var \App\Core\Http\ResponseFactory $responseFactory */

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

    $r->get('/reservas/confirmacion/{id}', 'Shared\ReservationController@confirmation');
    $r->get('/reservas/mis-reservas', 'Shared\ReservationController@userReservations');
    $r->get('/user/waitlists', 'User\WaitlistController@index');
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
