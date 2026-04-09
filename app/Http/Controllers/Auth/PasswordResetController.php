<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

/**
 * Controlador de Resteo de Contraseña
 *
 * Gestiona recuperación de contraseña y cambios de email.
 */
final class PasswordResetController
{
    private AuthService $authService;
    private ResponseFactory $response;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
        $this->response = new ResponseFactory();
    }

    /**
     * GET /auth/forgot-password
     * Mostrar formulario de olvido de contraseña
     */
    public function forgotPasswordForm(): ?ResponseInterface
    {
        if ($this->authService->check()) {
            return $this->response->redirect('/');
        }

        View::render('auth/forgot-password', [
            'csrf_token' => Csrf::token(),
        ]);
        return null;
    }

    /**
     * POST /auth/forgot-password
     * Procesar solicitud de reset de contraseña
     *
     * @throws RandomException
     * @throws JsonException
     */
    public function sendResetEmail(): ResponseInterface
    {
        $email = $_POST['email'] ?? '';

        if (!$email) {
            Flash::error('Email es requerido.');
            return $this->response->redirect('/auth/forgot-password');
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $result = $this->authService->requestPasswordReset($email, $ipAddress, $userAgent);

            // Mensaje genérico por seguridad (siempre mostrar éxito por seguridad)
            Flash::success('Si el email existe, recibirás instrucciones para recuperar tu contraseña.');

            return $this->response->redirect('/auth/forgot-password');
        } catch (\Throwable $e) {
            // Loguear para diagnóstico y evitar que una excepción externa provoque 500 no gestionado
            \App\Core\Logger::error('[PasswordReset] ' . $e->getMessage(), ['exception' => (string) $e]);
            // También volcar a php-error.log para trazas históricas
            @error_log("[PasswordReset] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 3, __DIR__ . '/../../storage/logs/php-error.log');

            Flash::error('Ocurrió un error procesando la solicitud. Por favor intenta más tarde.');
            return $this->response->redirect('/auth/forgot-password');
        }
    }

    /**
     * GET /auth/reset-password
     * Mostrar formulario de reset con token
     */
    public function resetPasswordForm(): ?ResponseInterface
    {
        if ($this->authService->check()) {
            return $this->response->redirect('/');
        }

        $token = $_GET['token'] ?? '';

        if (!$token) {
            Flash::error('Token inválido o expirado.');
            return $this->response->redirect('/auth/forgot-password');
        }

        // Validar token sin consumirlo
        $validation = $this->authService->validatePasswordResetToken($token);

        if ($validation->isFail()) {
            Flash::error($validation->getMessage());
            return $this->response->redirect('/auth/forgot-password');
        }

        View::render('auth/reset-password', [
            'token' => \htmlspecialchars($token),
            'csrf_token' => Csrf::token(),
        ]);
        return null;
    }

    /**
     * POST /auth/reset-password
     * Procesar cambio de contraseña
     */
    public function processReset(): ResponseInterface
    {
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$token) {
            Flash::error('Token inválido.');
            return $this->response->redirect('/auth/forgot-password');
        }

        $result = $this->authService->resetPasswordWithToken($token, $newPassword, $confirmPassword);

        if ($result->ok) {
            Flash::success('Contraseña actualizada exitosamente. Por favor inicia sesión.');
            return $this->response->redirect('/auth/login');
        }

        Flash::error($result->getMessage());
        return $this->response->redirect('/auth/reset-password?token=' . \urlencode($token));
    }

    /**
     * GET /auth/verify-email
     * Verificar email con token
     */
    public function verifyEmail(): ResponseInterface
    {
        $token = $_GET['token'] ?? '';

        if (!$token) {
            Flash::error('Token de verificación inválido.');
            return $this->response->redirect('/');
        }

        $result = $this->authService->verifyEmailToken($token);

        if ($result->ok) {
            Flash::success('Email verificado exitosamente.');

            // Si está autenticado, redirigir a account
            if ($this->authService->check()) {
                return $this->response->redirect('/account');
            }

            // Si no, redirigir a login
            return $this->response->redirect('/auth/login');
        }

        Flash::error($result->getMessage());
        return $this->response->redirect('/');
    }

    /**
     * POST /account/email/send-verification
     * Reenviar email de verificación
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function resendVerificationEmail(): ResponseInterface
    {
        $redirect = $this->requireAuth();
        if ($redirect !== null) {
            return $redirect;
        }
        Csrf::verify();

        $user = Session::user();
        $userId = (int) $user['id'];

        $result = $this->authService->sendVerificationEmail($userId);

        if ($result->ok) {
            Flash::success('Email de verificación enviado. Revisa tu bandeja de entrada.');
        } else {
            Flash::error($result->getMessage());
        }

        return $this->response->redirect('/account');
    }

    /**
     * Verificar que el usuario esté autenticado
     */
    private function requireAuth(): ?ResponseInterface
    {
        if (!$this->authService->check()) {
            Flash::error('Debes iniciar sesión.');
            return $this->response->redirect('/auth/login');
        }
        return null;
    }
}
