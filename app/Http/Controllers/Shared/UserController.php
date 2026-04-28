<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Container;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Domain\AvatarOptions;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\Contracts\GamificationServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\UserAccountServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

final class UserController
{
    private const string ROUTE_LOGIN  = '/login';
    private const string ROUTE_PERFIL = '/perfil';
    private const string MSG_AUTH     = 'Necesitas iniciar sesión para continuar.';

    private UserProfileServiceInterface $profileService;
    private UserAccountServiceInterface $accountService;
    private ReservationServiceInterface $reservations;
    private ReviewQueryServiceInterface $reviews;
    private GamificationServiceInterface $gamification;
    private ResponseFactory $response;

    public function __construct(
        ?UserProfileServiceInterface $profileService = null,
        ?UserAccountServiceInterface $accountService = null,
        ?ReservationServiceInterface $reservations = null,
        ?ReviewQueryServiceInterface $reviews = null,
        ?GamificationServiceInterface $gamification = null,
        ?ResponseFactory $response = null
    ) {
        $this->profileService = $profileService ?? Container::make(UserProfileServiceInterface::class);
        $this->accountService = $accountService ?? Container::make(UserAccountServiceInterface::class);
        $this->reservations = $reservations ?? Container::make(ReservationServiceInterface::class);
        $this->reviews = $reviews ?? Container::make(ReviewQueryServiceInterface::class);
        $this->gamification = $gamification ?? Container::make(GamificationServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    public function profile(): ?ResponseInterface
    {
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error(self::MSG_AUTH);
            $returnTo = $_SERVER['REQUEST_URI'] ?? '/';
            if ($returnTo === '' || $returnTo[0] !== '/' || \preg_match('/[\r\n]/', $returnTo) || \str_starts_with($returnTo, '//')) {
                $returnTo = '/';
            }
            Session::set('redirect_after_login', $returnTo);

            return $this->response->redirect(self::ROUTE_LOGIN);
        }

        $rawProfile = $this->profileService->getProfile($userId);
        $reservationList = $this->reservations->getByUser($userId);
        $reservationCount = \count($reservationList);

        View::render('shared/user/profile', [
            'titulo'        => 'Mi Perfil',
            'flash'         => Flash::consume(),
            'profile'       => [
                'name'       => (string) ($rawProfile['name'] ?? ''),
                'email'      => (string) ($rawProfile['email'] ?? ''),
                'avatar_url' => isset($rawProfile['avatar']) ? (string) $rawProfile['avatar'] : null,
                'created_at' => (string) ($rawProfile['created_at'] ?? ''),
            ],
            'stats'         => [
                'reservations_count' => $reservationCount,
                'level'              => $this->gamification->calculateUserLevel($reservationCount),
            ],
            'avatarOptions' => AvatarOptions::toList(),
        ], ['profile.css', 'reviews.css']);

        return null;
    }

    /**
     * @throws ValidationException
     * @throws NotFoundException
     * @throws RandomException
     * @throws JsonException
     */
    public function update(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error(self::MSG_AUTH);

            return $this->response->redirect(self::ROUTE_LOGIN);
        }

        $body = (array) $request->getParsedBody();
        $name = \trim((string) ($body['name'] ?? ''));
        $email = \strtolower(\trim((string) ($body['email'] ?? '')));

        $result = $this->profileService->updateProfile($userId, $name, $email);

        if (!$result->ok) {
            throw ValidationException::fromArray($result->data ?? ['error' => $result->error ?? 'No se pudo actualizar el perfil.']);
        }

        // Actualizar sesión
        $profile = $this->profileService->getProfile($userId);
        Session::set('user', $profile);

        Flash::success('Tu perfil se ha actualizado con éxito.');

        return $this->response->redirect(self::ROUTE_PERFIL);
    }

    /**
     * POST /perfil/password
     *
     * Cambia la contraseña del usuario actual.
     */
    public function changePassword(ServerRequestInterface $request): ?ResponseInterface
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            // Backup defensivo (middleware 'auth' ya verificó)
            Flash::error(self::MSG_AUTH);

            return $this->response->redirect(self::ROUTE_LOGIN);
        }

        // RECOPILACIÓN DE INPUTS
        $body = (array) $request->getParsedBody();
        $current = \trim((string) ($body['current_password'] ?? ''));
        $new = \trim((string) ($body['new_password'] ?? ''));
        $confirm = \trim((string) ($body['new_password_confirm'] ?? ''));

        $result = $this->accountService->changePassword($userId, $current, $new, $confirm);

        if (!$result->ok) {
            // Error en validación: mostrar genérico (no revelar si current es válida)
            Flash::error($result->error ?? 'No se pudo cambiar la contraseña.');

            return $this->response->redirect(self::ROUTE_PERFIL);
        }

        // UX: Confirmar cambio
        Flash::success('Tu contraseña se ha actualizado correctamente.');

        return $this->response->redirect(self::ROUTE_PERFIL);
    }

    /**
     * POST /account/avatar/upload
     *
     * Seleccionar avatar preset del usuario.
     * Body JSON: {avatar_id: string, csrf_token: string}
     */
    public function uploadAvatar(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = Session::userId();
        if ($userId === null) {
            return $this->response->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $body     = (array) ($request->getParsedBody() ?? []);
        $avatarId = \trim((string) ($body['avatar_id'] ?? ''));

        return $this->processAvatarUpdate($userId, $avatarId);
    }

    private function processAvatarUpdate(int $userId, string $avatarId): ResponseInterface
    {
        if (!AvatarOptions::isValid($avatarId)) {
            return $this->response->json(['success' => false, 'message' => 'Avatar no válido'], 422);
        }

        $avatarUrl = AvatarOptions::toUrl($avatarId);
        $result    = $this->profileService->updateAvatar($userId, $avatarUrl);

        if (!$result->ok) {
            return $this->response->json(['success' => false, 'message' => $result->error ?? 'Error al actualizar el avatar.'], 500);
        }

        Session::set('user', $this->profileService->getProfile($userId));

        return $this->response->json([
            'ok'   => true,
            'data' => ['avatar_id' => $avatarId, 'avatar_url' => $avatarUrl],
        ]);
    }

    /**
     * POST /account/avatar/delete
     *
     * Eliminar avatar del usuario
     */
    public function deleteAvatar(): ?ResponseInterface
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            return $this->response->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        // Obtener avatar actual
        $profile = $this->profileService->getProfile($userId);
        $currentAvatar = $profile['avatar'] ?? null;

        // Eliminar de base de datos
        $result = $this->profileService->updateAvatar($userId, null);

        if (!$result->ok) {
            return $this->response->json(['success' => false, 'message' => $result->error ?? 'Error al eliminar el avatar.'], 500);
        }

        // Eliminar archivo físico si existe y es local
        if ($currentAvatar !== null && \str_starts_with($currentAvatar, '/storage/uploads/')) {
            $filePath = __DIR__ . '/../../../' . \ltrim($currentAvatar, '/');
            if (\file_exists($filePath)) {
                \unlink($filePath);
            }
        }

        // Actualizar sesión
        $profile = $this->profileService->getProfile($userId);
        Session::set('user', $profile);

        return $this->response->json([
            'success' => true,
            'message' => 'Avatar eliminado correctamente',
        ]);
    }

    /**
     * GET /account/export-data
     *
     * Exportar todos los datos personales del usuario (GDPR Art. 20 - Portabilidad)
     * @throws NotFoundException
     * @throws JsonException
     */
    public function exportData(): ?ResponseInterface
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error('Necesitas iniciar sesión para exportar tus datos.');

            return $this->response->redirect(self::ROUTE_LOGIN);
        }

        // Recopilar todos los datos personales
        $profile = $this->profileService->getProfile($userId);
        $reservations = $this->reservations->getByUser($userId, 'all');
        $reviews = $this->reviews->listUserReviews($userId);

        // Construir estructura JSON con todos los datos
        $exportData = [
            'export_date' => \date('Y-m-d H:i:s'),
            'user' => [
                'id' => $profile['id'],
                'name' => $profile['name'],
                'email' => $profile['email'],
                'created_at' => $profile['created_at'],
                'roles' => $profile['roles'] ?? [],
            ],
            'reservations' => \array_map(static function ($r) {
                // Mapear conforme al esquema de BD: reservation_date, reservation_time, guest_count
                return [
                    'id' => $r['id'],
                    'date' => $r['reservation_date'] ?? null,
                    'time' => $r['reservation_time'] ?? null,
                    'guests' => isset($r['guest_count']) ? (int) $r['guest_count'] : 1,
                    'status' => $r['status'],
                    'created_at' => $r['created_at'],
                ];
            }, $reservations),
            'reviews' => \array_map(static function ($rev) {
                return [
                    'id' => $rev['id'],
                    'rating' => $rev['rating'],
                    'comment' => $rev['comment'],
                    'created_at' => $rev['created_at'],
                ];
            }, $reviews),
            'gdpr_notice' => 'Este archivo contiene todos sus datos personales almacenados en Komorebi Café según GDPR Art. 20.',
        ];

        $json = \json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stream = $this->response->createStream($json);

        return $this->response->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="komorebi-cafe-data-' . \date('Y-m-d') . '.json"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withBody($stream);
    }
}
