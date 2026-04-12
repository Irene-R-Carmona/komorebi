<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Flash;
use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\Contracts\UserAccountServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\GamificationService;
use App\Services\ReservationService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Random\RandomException;

final class UserController
{
    private UserProfileServiceInterface $profileService;
    private UserAccountServiceInterface $accountService;
    private ReservationService $reservations;
    private ReviewQueryServiceInterface $reviews;
    private GamificationService $gamification;
    private ResponseFactory $response;

    public function __construct(
        ?UserProfileServiceInterface $profileService = null,
        ?UserAccountServiceInterface $accountService = null,
        ?ReservationService $reservations = null,
        ?ReviewQueryServiceInterface $reviews = null,
        ?GamificationService $gamification = null,
        ?ResponseFactory $response = null
    ) {
        $this->profileService = $profileService ?? Container::make(UserProfileServiceInterface::class);
        $this->accountService = $accountService ?? Container::make(UserAccountServiceInterface::class);
        $this->reservations = $reservations ?? Container::make(ReservationService::class);
        $this->reviews = $reviews ?? Container::make(ReviewQueryServiceInterface::class);
        $this->gamification = $gamification ?? new GamificationService();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * @throws NotFoundException
     */
    public function profile(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error('Necesitas iniciar sesión para continuar.');
            $returnTo = $_SERVER['REQUEST_URI'] ?? '/';
            if ($returnTo === '' || $returnTo[0] !== '/' || \preg_match('/[\r\n]/', $returnTo) || \str_starts_with($returnTo, '//')) {
                $returnTo = '/';
            }
            Session::set('redirect_after_login', $returnTo);
            return $this->response->redirect('/login');
        }

        $profile = $this->profileService->getProfile($userId);

        // Obtener próxima reserva activa (puede lanzar si falla la BD)
        $reservas = $this->reservations->getByUser($userId, 'active');
        $reservasCount = \count($reservas);
        $nextReservation = !empty($reservas) ? \reset($reservas) : null;

        // Obtener reseñas del usuario
        $userReviews = $this->reviews->listUserReviews($userId);

        $nivel = $this->gamification->calculateUserLevel($reservasCount);

        View::render('shared/user/profile', [
            'titulo' => 'Mi Perfil',
            'profile' => $profile,
            'nivel' => $nivel,
            'stats' => ['reservasCount' => $reservasCount],
            'nextReservation' => $nextReservation,
            'userReviews' => $userReviews,
            'flash' => Flash::consume(),
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
            Flash::error('Necesitas iniciar sesión para continuar.');
            return $this->response->redirect('/login');
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
        return $this->response->redirect('/perfil');
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
            Flash::error('Necesitas iniciar sesión para continuar.');
            return $this->response->redirect('/login');
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
            return $this->response->redirect('/perfil');
        }

        // UX: Confirmar cambio
        Flash::success('Tu contraseña se ha actualizado correctamente.');
        return $this->response->redirect('/perfil');
    }

    /**
     * POST /account/avatar/upload
     *
     * Subir avatar del usuario
     */
    public function uploadAvatar(ServerRequestInterface $request): ?ResponseInterface
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            return $this->response->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $files = $request->getUploadedFiles();
        $uploadedFile = $files['avatar'] ?? null;

        if (!($uploadedFile instanceof UploadedFileInterface) || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->response->json(['success' => false, 'message' => 'No se recibió ningún archivo válido'], 400);
        }

        // Validación de tipo MIME via ruta temporal del stream
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $tmpPath = (string) ($uploadedFile->getStream()->getMetadata('uri') ?? '');
        $finfo = \finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? \finfo_file($finfo, $tmpPath) : '';
        if ($finfo !== false) {
            \finfo_close($finfo);
        }

        if (!\in_array($mimeType, $allowedMimes, true)) {
            return $this->response->json(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG, PNG o WebP.'], 400);
        }

        // Validación de tamaño (2MB)
        $maxSize = 2 * 1024 * 1024;
        if (($uploadedFile->getSize() ?? 0) > $maxSize) {
            return $this->response->json(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 2MB.'], 400);
        }

        // Validación de extensión
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = \strtolower(\pathinfo($uploadedFile->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if (!\in_array($extension, $allowedExts, true)) {
            return $this->response->json(['success' => false, 'message' => 'Extensión de archivo no permitida.'], 400);
        }

        // Generar nombre único
        $filename = 'avatar_' . $userId . '_' . \time() . '.' . $extension;
        $uploadDir = __DIR__ . '/../../../storage/uploads/avatars/';

        // Crear directorio si no existe
        if (!\is_dir($uploadDir)) {
            \mkdir($uploadDir, 0755, true);
        }

        $uploadPath = $uploadDir . $filename;

        // Mover archivo con PSR-7
        try {
            $uploadedFile->moveTo($uploadPath);
        } catch (\RuntimeException) {
            return $this->response->json(['success' => false, 'message' => 'Error al guardar el archivo.'], 500);
        }

        // Actualizar en base de datos
        $avatarUrl = '/storage/uploads/avatars/' . $filename;
        $result = $this->profileService->updateAvatar($userId, $avatarUrl);

        if (!$result->ok) {
            // Eliminar archivo si falla la actualización en BD
            if (\file_exists($uploadPath)) {
                \unlink($uploadPath);
            }
            return $this->response->json(['success' => false, 'message' => $result->error ?? 'Error al actualizar el avatar.'], 500);
        }

        // Actualizar sesión
        $profile = $this->profileService->getProfile($userId);
        Session::set('user', $profile);

        return $this->response->json([
            'success' => true,
            'message' => 'Avatar actualizado correctamente',
            'avatar_url' => $avatarUrl,
        ]);
    }

    /**
     * POST /account/avatar/delete
     *
     * Eliminar avatar del usuario
     */
    public function deleteAvatar(ServerRequestInterface $request): ?ResponseInterface
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
    public function exportData(ServerRequestInterface $request): ?ResponseInterface
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error('Necesitas iniciar sesión para exportar tus datos.');
            return $this->response->redirect('/login');
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
