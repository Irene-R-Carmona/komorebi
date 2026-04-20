<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\EmailVerificationServiceInterface;
use App\Services\Contracts\PasswordResetServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Resteo de Contraseña
 *
 * Gestiona recuperación de contraseña y cambios de email.
 */
final class PasswordResetController
{
    private AuthServiceInterface $authService;
    private PasswordResetServiceInterface $passwordResetService;
    private EmailVerificationServiceInterface $emailVerificationService;
    private ResponseFactory $response;

    public function __construct(
        ?AuthServiceInterface $authService = null,
        ?PasswordResetServiceInterface $passwordResetService = null,
        ?EmailVerificationServiceInterface $emailVerificationService = null,
        ?ResponseFactory $response = null
    ) {
        $this->authService = $authService ?? Container::make(AuthService::class);
        $this->passwordResetService = $passwordResetService ?? Container::make(PasswordResetServiceInterface::class);
        $this->emailVerificationService = $emailVerificationService ?? Container::make(EmailVerificationServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /auth/forgot-password
     * Mostrar formulario de olvido de contraseña
     */
    public function forgotPasswordForm(ServerRequestInterface $request): ?ResponseInterface
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
    public function sendResetEmail(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $email = $body['email'] ?? '';

        if (!$email) {
            Flash::error('Email es requerido.');

            return $this->response->redirect('/auth/forgot-password');
        }

        $serverParams = $request->getServerParams();
        $ipAddress = $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $serverParams['HTTP_USER_AGENT'] ?? null;

        try {
            $result = $this->passwordResetService->requestPasswordReset($email, $ipAddress, $userAgent);

            // Mensaje genérico por seguridad (siempre mostrar éxito por seguridad)
            Flash::success('Si el email existe, recibirás instrucciones para recuperar tu contraseña.');

            return $this->response->redirect('/auth/forgot-password');
        } catch (\Throwable $e) {
            // Loguear para diagnóstico y evitar que una excepción externa provoque 500 no gestionado
            \App\Core\Logger::error('[PasswordReset] ' . $e->getMessage(), ['exception' => (string) $e]);

            Flash::error('Ocurrió un error procesando la solicitud. Por favor intenta más tarde.');

            return $this->response->redirect('/auth/forgot-password');
        }
    }

    /**
     * GET /auth/reset-password
     * Mostrar formulario de reset con token
     */
    public function resetPasswordForm(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($this->authService->check()) {
            return $this->response->redirect('/');
        }

        $token = $request->getQueryParams()['token'] ?? '';

        if (!$token) {
            Flash::error('Token inválido o expirado.');

            return $this->response->redirect('/auth/forgot-password');
        }

        // Validar token sin consumirlo
        $validation = $this->passwordResetService->validatePasswordResetToken($token);

        if ($validation->error !== null) {
            Flash::error($validation->error);

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
    public function processReset(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $token = $body['token'] ?? '';
        $newPassword = $body['new_password'] ?? '';
        $confirmPassword = $body['confirm_password'] ?? '';

        if (!$token) {
            Flash::error('Token inválido.');

            return $this->response->redirect('/auth/forgot-password');
        }

        $result = $this->passwordResetService->resetPasswordWithToken($token, $newPassword, $confirmPassword);

        if ($result->ok) {
            Flash::success('Contraseña actualizada exitosamente. Por favor inicia sesión.');

            return $this->response->redirect('/auth/login');
        }

        Flash::error($result->error);

        return $this->response->redirect('/auth/reset-password?token=' . \urlencode($token));
    }

    /**
     * GET /auth/verify-email
     * Verificar email con token
     */
    public function verifyEmail(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';

        if (!$token) {
            Flash::error('Token de verificación inválido.');

            return $this->response->redirect('/');
        }

        $result = $this->emailVerificationService->verifyEmailToken($token);

        if ($result->ok) {
            Flash::success('Email verificado exitosamente.');

            // Si está autenticado, redirigir a account
            if ($this->authService->check()) {
                return $this->response->redirect('/account');
            }

            // Si no, redirigir a login
            return $this->response->redirect('/auth/login');
        }

        Flash::error($result->error);

        return $this->response->redirect('/');
    }

    /**
     * POST /account/email/send-verification
     * Reenviar email de verificación
     *
     * @throws JsonException
     * @throws RandomException
     */
    public function resendVerificationEmail(ServerRequestInterface $request): ResponseInterface
    {
        $redirect = $this->requireAuth();
        if ($redirect !== null) {
            return $redirect;
        }
        Csrf::verify();

        $user = Session::user();
        $userId = (int) $user['id'];

        $result = $this->emailVerificationService->sendVerificationEmail($userId);

        if ($result->ok) {
            Flash::success('Email de verificación enviado. Revisa tu bandeja de entrada.');
        } else {
            Flash::error($result->error);
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
