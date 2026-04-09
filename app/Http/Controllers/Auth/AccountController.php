<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\AccountDeletionService;
use App\Services\AuthService;
use App\Services\FileUploadService;
use App\Services\UserService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

/**
 * Controlador de Cuenta de Usuario
 *
 * Gestiona sesiones activas, historial de autenticación y ajustes de seguridad.
 */
final class AccountController
{
    private AccountDeletionService $accountDeletionService;
    private AuthService $authService;
    private FileUploadService $fileUploadService;
    private ResponseFactory $response;
    private UserService $userService;

    public function __construct(
        ?AuthService $authService = null,
        ?FileUploadService $fileUploadService = null,
        ?UserService $userService = null,
        ?ResponseFactory $response = null,
        ?AccountDeletionService $accountDeletionService = null
    ) {
        $this->accountDeletionService = $accountDeletionService ?? new AccountDeletionService();
        $this->authService = $authService ?? new AuthService();
        $this->fileUploadService = $fileUploadService ?? new FileUploadService();
        $this->userService = $userService ?? new UserService();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /account/sessions
     * Listar sesiones activas del usuario
     */
    public function sessions(): void
    {
        $this->requireAuth();

        $user = Session::user();
        $userId = (int) $user['id'];

        $sessions = $this->authService->getActiveSessions($userId);

        View::render('account/sessions', [
            'sessions' => $sessions,
            'csrf_token' => Csrf::token(),
        ]);
    }

    /**
     * POST /account/sessions/revoke/:sessionId
     * Revocar una sesión específica
     */
    public function revokeSession(int $sessionId): void
    {
        $this->requireAuth();

        $user = Session::user();
        $userId = (int) $user['id'];

        if ($this->authService->revokeSession($userId, $sessionId)) {
            Flash::success('Sesión revocada exitosamente.');
        } else {
            Flash::error('No se pudo revocar la sesión.');
        }

        \header('Location: /account/sessions');
        exit;
    }

    /**
     * POST /account/sessions/revoke-all
     * Revocar todas las demás sesiones
     */
    public function revokeAllOther(): void
    {
        $this->requireAuth();

        $user = Session::user();
        $userId = (int) $user['id'];
        $currentSessionId = \session_id();

        $revoked = $this->authService->revokeAllOtherSessions($userId, $currentSessionId);

        Flash::success("Se revocaron $revoked sesiones.");

        \header('Location: /account/sessions');
        exit;
    }

    /**
     * GET /account/security
     * Ver historial de seguridad y login
     */
    public function security(): void
    {
        $this->requireAuth();

        $user = Session::user();
        $userId = (int) $user['id'];

        $authHistory = $this->authService->getAuthHistory($userId, 30);

        View::render('account/security', [
            'auth_history' => $authHistory,
        ]);
    }

    /**
     * GET /account/change-password
     * Mostrar formulario de cambio de contraseña
     */
    public function changePasswordForm(): void
    {
        $this->requireAuth();

        View::render('account/change-password', [
            'csrf_token' => Csrf::token(),
        ]);
    }

    /**
     * POST /account/change-password
     * Procesar cambio de contraseña
     */
    public function changePassword(): void
    {
        $this->requireAuth();

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            Flash::error('Todos los campos son requeridos.');
            \header('Location: /account/change-password');
            exit;
        }

        $result = $this->userService->changePassword(
            Session::userId(),
            $currentPassword,
            $newPassword,
            $confirmPassword
        );

        if ($result->isOk()) {
            Flash::success('Contraseña actualizada exitosamente. Por seguridad, se revocaron otras sesiones.');
        } else {
            Flash::error($result->error);
            \header('Location: /account/change-password');
            exit;
        }

        \header('Location: /account/security');
        exit;
    }

    /**
     * Verificar que el usuario esté autenticado
     */
    private function requireAuth(): void
    {
        if (!$this->authService->check()) {
            Flash::error('Debes iniciar sesión.');
            \header('Location: /auth/login');
            exit;
        }
    }

    /**
     * POST /account/delete
     * Eliminar la cuenta del usuario actual (soft delete + anonimización GDPR)
     */
    public function deleteAccount(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();

        $user = Session::user();
        $userId = (int) $user['id'];

        $result = $this->accountDeletionService->deleteAndAnonymize($userId);

        if (!$result->ok) {
            Flash::error($result->getMessage());
            return $this->response->redirect('/account');
        }

        // Destruir la sesión (mismo patrón que AuthController::logout)
        $this->authService->logout();

        Flash::success('Tu cuenta ha sido eliminada correctamente.');

        return $this->response->redirect('/');
    }

    /**
     * POST /account/avatar/upload
     * Subir avatar del usuario
     * @throws JsonException
     * @throws RandomException
     */
    public function uploadAvatar(): ResponseInterface
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response->problem(Result::fail('Método no permitido', 'method_not_allowed'), 405);
        }

        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            return $this->response->problem(Result::fail('No se seleccionó ningún archivo', 'no_file'), 422);
        }

        $user = Session::user();
        $userId = (int) $user['id'];

        // Subir avatar
        $result = $this->fileUploadService->uploadAvatar($_FILES['avatar'], $userId);

        if ($result->isFail()) {
            return $this->response->problem(Result::fail($result->getMessage() ?? 'Error', 'upload_failed'), 422);
        }

        // Actualizar URL del avatar en la base de datos
        $updateResult = $this->userService->updateAvatar($userId, $result->data);

        if ($updateResult->isFail()) {
            $this->fileUploadService->deleteFile($result->data);
            return $this->response->problem(Result::fail('Error al actualizar el avatar', 'server_error'), 500);
        }

        $user['avatar'] = $result->data;
        Session::set('user', $user);

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'Avatar actualizado exitosamente',
            'avatar' => $result->data,
        ]]);
    }

    /**
     * DELETE /account/avatar
     * Eliminar avatar del usuario
     * @throws JsonException
     * @throws RandomException
     */
    public function deleteAvatar(): ResponseInterface
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response->problem(Result::fail('Método no permitido', 'method_not_allowed'), 405);
        }

        if (!Csrf::validate()) {
            throw ValidationException::withMessage('Token de seguridad inválido', 419);
        }

        $user = Session::user();
        $userId = (int) $user['id'];

        // Eliminar avatar del filesystem (si existe)
        $this->fileUploadService->deleteAvatar($userId);

        // Actualizar BD (eliminar referencia)
        $updateResult = $this->userService->updateAvatar($userId, null);

        if ($updateResult->isFail()) {
            return $this->response->problem(Result::fail('Error al eliminar el avatar', 'server_error'), 500);
        }

        $user['avatar'] = null;
        Session::set('user', $user);

        return $this->response->json(['ok' => true, 'data' => [
            'message' => 'Avatar eliminado exitosamente',
        ]]);
    }
}
