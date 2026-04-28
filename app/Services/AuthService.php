<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Result;
use App\Core\Session;
use App\Domain\DTO\UserDTO;
use App\Events\UserRegisteredEvent;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use DateTimeImmutable;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\RandomException;
use RuntimeException;

/**
 * Servicio de Autenticación
 */
final class AuthService extends BaseService implements AuthServiceInterface
{
    private UserRepositoryInterface $userRepo;
    private SessionManagementServiceInterface $sessionService;
    private RateLimitingServiceInterface $rateLimiter;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        UserRepositoryInterface $userRepo,
        SessionManagementServiceInterface $sessionService,
        RateLimitingServiceInterface $rateLimiter,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->userRepo = $userRepo;
        $this->sessionService = $sessionService;
        $this->rateLimiter = $rateLimiter;
        $this->eventDispatcher = $eventDispatcher;
    }

    // ─────────────────────────────────────────────────────────────
    // Login
    // ─────────────────────────────────────────────────────────────

    /**
     * Intenta autenticar al usuario.
     *
     * @param string $email
     * @param string $password
     * @return Result Data contiene ['redirect' => string] si exitoso
     * @throws RandomException
     */
    #[Override]
    public function login(string $email, string $password): Result
    {
        $email = \strtolower(\trim($email));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Validar entrada básica
        if ($error = $this->validateLoginInput($email, $password)) {
            return $error;
        }

        // Verificar rate limiting
        if ($error = $this->checkRateLimiting($email, $ipAddress)) {
            return $error;
        }

        $user = $this->userRepo->findByEmailWithCredentials($email);
        if ($error = $this->validateUser($user, $password, $email, $ipAddress, $userAgent)) {
            return $error;
        }

        // Realizar login exitoso
        return $this->performSuccessfulLogin($user, $email, $ipAddress);
    }

    // ─────────────────────────────────────────────────────────────
    // Registro
    // ─────────────────────────────────────────────────────────────

    /**
     * Registra un nuevo usuario.
     *
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $confirmPassword
     * @return Result Data contiene ['user_id' => int] si exitoso
     * @throws RandomException
     * @throws ValidationException
     */
    #[Override]
    public function register(string $name, string $email, string $password, string $confirmPassword): Result
    {
        $name = \trim($name);
        $email = \strtolower(\trim($email));

        // Validaciones
        if ($name === '' || \mb_strlen($name) > 100) {
            return Result::fail('Nombre inválido (1-100 caracteres).');
        }

        if (!\filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Result::fail('Email inválido.');
        }

        if (\mb_strlen($password) < 8) {
            return Result::fail('La contraseña debe tener al menos 8 caracteres.');
        }

        if ($password !== $confirmPassword) {
            return Result::fail('Las contraseñas no coinciden.');
        }

        if ($this->userRepo->emailExists($email)) {
            return Result::fail('Este email ya está registrado.');
        }

        try {
            $userId = $this->userRepo->create([
                'name' => $name,
                'email' => $email,
                'password' => \password_hash($password, PASSWORD_ARGON2ID),
            ]);

            // Disparar evento de registro
            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch(
                    new UserRegisteredEvent($userId, $email, $name, new DateTimeImmutable())
                );
            }

            // Auto-login tras registro (usar repositorio)
            $user = $this->userRepo->findById($userId);
            $this->createSession($user);

            return Result::ok(['user_id' => $userId]);
        } catch (RuntimeException $e) {
            return Result::fail($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Logout
    // ─────────────────────────────────────────────────────────────

    /**
     * Cierra la sesión del usuario.
     * @throws RandomException
     */
    #[Override]
    public function logout(): void
    {
        $user = Session::user();
        if ($user) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $this->sessionService->logAuthEvent(
                (int) $user['id'],
                'logout',
                $ipAddress,
                $userAgent,
                null,
                true
            );
        }

        Session::destroy();
        Csrf::regenerate();
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea sesión de usuario con caché de permisos.
     * @throws RandomException
     */
    private function createSession(?UserDTO $user): void
    {
        // IMPORTANTE: Asegurar que la sesión esté iniciada
        Session::start();

        if (Env::get('APP_ENV') === 'local') {
            Logger::error('[AuthService::createSession] START - Session ID: ' . \session_id(), []);
            Logger::error('[AuthService::createSession] User: ' . \json_encode($user), []);
        }

        if (!$user) {
            // No hay usuario válido para crear la sesión; abortar silenciosamente
            return;
        }

        $userId = $user->id;

        // Obtener roles del usuario via RBAC
        $roles = $this->userRepo->getRoles($userId) ?: [];
        $rolesCodes = \array_column($roles, 'slug');

        if (Env::get('APP_ENV') === 'local') {
            Logger::error('[AuthService::createSession] Roles: ' . \json_encode($rolesCodes), []);
        }

        // Crear ID de sesión
        $sessionId = \session_id();

        // Registrar sesión activa en BD
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $deviceName = $this->parseDeviceName($userAgent);

        $this->sessionService->createSession($userId, (string) $sessionId, $ipAddress, $userAgent, $deviceName);

        // Log de login
        $this->sessionService->logAuthEvent($userId, 'login', $ipAddress, $userAgent, $deviceName, true);

        // Actualizar last_login_at en usuarios
        $this->userRepo->update($userId, ['updated_at' => \date('Y-m-d H:i:s')]);

        // 1. Establecer datos básicos de usuario en sesión
        // Seleccionar rol principal (si existe) o 'user' por defecto
        $primaryRole = \is_string($rolesCodes[0] ?? null) ? $rolesCodes[0] : 'user';

        // Pasar la lista de roles al Session::setUser para popular 'user_roles'
        $roleValue = !empty($rolesCodes) ? $rolesCodes : [$primaryRole];

        // Pasar `role` como string para cumplir la firma de Session::setUser
        Session::setUser([
            'id' => $userId,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $primaryRole,
            'cafe_id' => $user->cafe_id,
        ]);

        // Guardar lista completa de roles por separado
        Session::set('user_roles', $roleValue);

        // 2. Cachear permisos del usuario
        Session::cacheUserPermissions($userId);

        // 3. Regenerar token CSRF tras login
        Csrf::regenerate();
    }

    /**
     * Parsear nombre del dispositivo desde User-Agent
     */
    private function parseDeviceName(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (\stripos($userAgent, 'Mobile') !== false) {
            return 'Mobile';
        }
        if (\stripos($userAgent, 'Tablet') !== false) {
            return 'Tablet';
        }
        if (\stripos($userAgent, 'Windows') !== false) {
            return 'Windows';
        }
        if (\stripos($userAgent, 'Mac') !== false) {
            return 'macOS';
        }
        if (\stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        }

        return 'Desktop';
    }

    /**
     * Verifica si el usuario actual está autenticado.
     */
    #[Override]
    public function check(): bool
    {
        return Session::isAuthenticated();
    }

    /**
     * Obtiene el usuario actual.
     */
    #[Override]
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return Session::user();
    }

    // ─────────────────────────────────────────────────────────────
    // Métodos privados de validación (refactoring complejidad)
    // ─────────────────────────────────────────────────────────────

    /**
     * Valida la entrada básica del login
     *
     * @param string $email
     * @param string $password
     * @return Result|null Retorna Result si hay error
     */
    private function validateLoginInput(string $email, string $password): ?Result
    {
        if ($email === '' || $password === '') {
            return Result::fail('Email y contraseña son requeridos.');
        }

        return null;
    }

    /**
     * Verifica rate limiting por IP y email
     *
     * @param string $email
     * @param string $ipAddress
     * @return Result|null Retorna Result si está bloqueado
     */
    private function checkRateLimiting(string $email, string $ipAddress): ?Result
    {
        $appEnv = Env::get('APP_ENV');
        // In CI/local tests we often run under CLI; skip rate limiting when running CLI to avoid interference.
        $isRunningCli = PHP_SAPI === 'cli';
        if ($appEnv === 'testing' || $isRunningCli) {
            return null;
        }
        // Rate limiting por IP
        $ipBlocked = $this->rateLimiter->isBlocked('login', $ipAddress);
        if (!empty($ipBlocked['blocked'])) {
            $minutes = isset($ipBlocked['minutes_remaining']) ? (int) $ipBlocked['minutes_remaining'] : 0;

            return Result::fail("Demasiados intentos desde tu IP. Intenta en {$minutes} minutos.");
        }

        // Rate limiting por email
        $emailBlocked = $this->rateLimiter->isBlocked('login', $email);
        if (!empty($emailBlocked['blocked'])) {
            $minutes = isset($emailBlocked['minutes_remaining']) ? (int) $emailBlocked['minutes_remaining'] : 0;

            return Result::fail("Demasiados intentos. Intenta en {$minutes} minutos.");
        }

        return null;
    }

    /**
     * Valida usuario, estado de cuenta y contraseña
     *
     * @param array|null  $user
     * @param string      $password
     * @param string      $email
     * @param string      $ipAddress
     * @param string|null $userAgent
     * @return Result|null Retorna Result si hay error
     */
    private function validateUser(?array $user, string $password, string $email, string $ipAddress, ?string $userAgent): ?Result
    {
        // Usuario no existe (mensaje genérico por seguridad)
        if (!$user) {
            $this->rateLimiter->recordAttempt('login', $email, $ipAddress);
            $this->rateLimiter->recordAttempt('login', $ipAddress);
            $this->sessionService->logAuthEvent(null, 'failed_login', $ipAddress, $userAgent, null, false, 'Usuario no encontrado');

            return Result::fail('Credenciales incorrectas.');
        }

        $userId = (int) $user['id'];

        // Cuenta bloqueada
        if ($this->userRepo->isLocked($user)) {
            $minutes = $this->userRepo->lockoutMinutesRemaining($user);
            $this->sessionService->logAuthEvent($userId, 'lockout', $ipAddress, $userAgent, null, false, "Bloqueado $minutes minutos");

            return Result::fail("Cuenta bloqueada temporalmente. Intenta en $minutes minutos.");
        }

        // Cuenta desactivada
        if (!$user['is_active']) {
            $this->sessionService->logAuthEvent($userId, 'failed_login', $ipAddress, $userAgent, null, false, 'Cuenta desactivada');

            return Result::fail('Tu cuenta está desactivada.');
        }

        // Verificar contraseña
        if (!$this->userRepo->verifyPassword($user, $password)) {
            $this->userRepo->registerFailedAttempt($userId);
            $this->rateLimiter->recordAttempt('login', $email, $ipAddress);
            $this->rateLimiter->recordAttempt('login', $ipAddress);
            $this->sessionService->logAuthEvent($userId, 'failed_login', $ipAddress, $userAgent, null, false, 'Contraseña incorrecta');

            return Result::fail('Credenciales incorrectas.');
        }

        return null;
    }

    /**
     * Realiza el login exitoso y determina redirección
     *
     * @param array  $user
     * @param string $email
     * @param string $ipAddress
     * @return Result
     * @throws RandomException
     */
    private function performSuccessfulLogin(?array $user, string $email, string $ipAddress): Result
    {
        if (!$user || !isset($user['id'])) {
            return Result::fail('Usuario no encontrado.');
        }

        $userId = (int) $user['id'];

        if (Env::get('APP_ENV') === 'local') {
            Logger::error('[AuthService::login] Password verified successfully for user ID: ' . $userId, []);
        }

        // Limpiar intentos fallidos
        $this->userRepo->clearLoginAttempts($userId);
        $this->rateLimiter->clearAttempts('login', $email);
        $this->rateLimiter->clearAttempts('login', $ipAddress);

        if (Env::get('APP_ENV') === 'local') {
            Logger::error('[AuthService::login] About to call createSession', []);
        }
        $this->createSession($this->userRepo->findById($userId));
        if (Env::get('APP_ENV') === 'local') {
            Logger::error('[AuthService::login] createSession completed', []);
        }

        // Determinar redirección basada en el rol del usuario
        $redirect = $this->determineRedirectUrl();

        return Result::ok(['redirect' => $redirect]);
    }

    /**
     * Determina la URL de redirección basada en el rol del usuario
     *
     * @return string
     */
    private function determineRedirectUrl(): string
    {
        $roles = Session::get('user_roles', []);
        $primaryRole = $roles[0] ?? 'user';

        // Mapeo de roles a sus páginas home
        $roleHomes = [
            // Preferir la ruta /admin/ (evita el redirect intermedio definido en routes.php)
            'admin' => '/admin/dashboard',
            'manager' => '/manager/dashboard',
            'supervisor' => '/supervisor/dashboard',
            'reception' => '/ops/reception',
            'kitchen' => '/ops/kitchen',
            'keeper' => '/keeper/dashboard',
            'user' => '/profile',
        ];

        // Usar el home del rol, o el redirect_after_login si existe y es válido
        $savedRedirect = Session::pull('redirect_after_login', null);
        $defaultHome = $roleHomes[$primaryRole] ?? '/';

        // Si hay un redirect guardado y no es una ruta de autenticación, usarlo.
        // Si apunta al backoffice, normalizar a la raíz /admin/ para evitar redirects intermedios.
        if (\is_string($savedRedirect) && $savedRedirect !== '' && !\str_starts_with($savedRedirect, '/login') && !\str_starts_with($savedRedirect, '/registro')) {
            if (\str_starts_with($savedRedirect, '/admin')) {
                return '/admin/';
            }

            return $savedRedirect;
        }

        return $defaultHome;
    }
}
