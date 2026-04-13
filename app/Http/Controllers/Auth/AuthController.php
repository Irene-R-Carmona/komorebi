<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Throwable;

/**
 * Controlador de Autenticación (PSR-7)
 *
 * Gestiona login, registro y logout.
 * Delega la lógica de negocio a AuthService.
 *
 * Seguridad (OWASP):
 * - CSRF en todas las acciones POST
 * - Mensajes de error genéricos (evita enumeración)
 * - Bloqueo temporal tras intentos fallidos (via AuthService)
 */
final class AuthController
{
    private AuthService $authService;
    private ResponseFactory $response;

    public function __construct(?AuthService $authService = null, ?ResponseFactory $response = null)
    {
        $this->authService = $authService ?? Container::make(AuthService::class);
        $this->response = $response ?? new ResponseFactory();
    }

    // ─────────────────────────────────────────────────────────────
    // Vistas
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /login
     *
     * @throws Throwable
     */
    public function showLogin(ServerRequestInterface $request): ?ResponseInterface
    {
        try {
            // Si ya está autenticado, redirigir a home
            if (Session::isAuthenticated()) {
                return $this->response->redirect('/');
            }

            View::render('auth/login', [
                'titulo' => 'Iniciar Sesión',
                'old' => ['email' => ''],
            ], ['auth.css']);

            return null;
        } catch (Throwable $e) {
            Logger::error('[AuthController::showLogin] Error', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * GET /registro
     */
    public function showRegister(ServerRequestInterface $request): ?ResponseInterface
    {
        if (Session::isAuthenticated()) {
            return $this->response->redirect('/');
        }

        View::render('auth/register', [
            'titulo' => 'Crear Cuenta',
            'old' => ['name' => '', 'email' => ''],
        ], ['auth.css']);

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Acciones POST
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /login
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function processLogin(ServerRequestInterface $request): ?ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        try {
            $form = LoginRequest::fromRequest($request);
            $form->validate($body);
            $data = $form->validated();
        } catch (ValidationException $e) {
            View::render('auth/login', [
                'titulo' => 'Iniciar Sesión',
                'errors' => $e->getErrors(),
                'old' => ['email' => $body['email'] ?? ''],
            ], ['auth.css']);
            return null;
        }

        $result = $this->authService->login($data['email'], $data['password']);

        if ($result->ok) {
            Flash::success('¡Bienvenido de vuelta!');
            $redirect = $result->data['redirect'] ?? '/';

            return $this->response->redirect($redirect);
        }

        // Error de credenciales: volver a mostrar el formulario
        View::render('auth/login', [
            'titulo' => 'Iniciar Sesión',
            'error' => $result->getMessage(),
            'old' => ['email' => $data['email']],
        ], ['auth.css']);

        return null;
    }

    /**
     * POST /registro
     *
     * @throws JsonException
     * @throws RandomException
     * @throws ValidationException
     */
    public function processRegister(ServerRequestInterface $request): ?ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        try {
            $form = RegisterRequest::fromRequest($request);
            $form->validate($body);
            $data = $form->validated();
        } catch (ValidationException $e) {
            View::render('auth/register', [
                'titulo' => 'Crear Cuenta',
                'errors' => $e->getErrors(),
                'old' => ['name' => $body['name'] ?? '', 'email' => $body['email'] ?? ''],
            ], ['auth.css']);
            return null;
        }

        $result = $this->authService->register(
            $data['name'],
            $data['email'],
            $data['password'],
            $data['password_confirmation'],
        );

        if ($result->ok) {
            Flash::success('¡Cuenta creada! Bienvenido a Komorebi.');

            return $this->response->redirect('/');
        }

        // Error de negocio: volver a mostrar el formulario
        View::render('auth/register', [
            'titulo' => 'Crear Cuenta',
            'error' => $result->getMessage(),
            'old' => ['name' => $data['name'], 'email' => $data['email']],
        ], ['auth.css']);

        return null;
    }

    /**
     * POST /logout
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        Csrf::verify();

        $this->authService->logout();

        Flash::info('Has cerrado sesión correctamente.');

        return $this->response->redirect('/');
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────
}
