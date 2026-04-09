<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Flash;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\GamificationService;
use App\Services\ReservationService;
use App\Services\ReviewService;
use App\Services\UserService;
use JsonException;
use Random\RandomException;

final class UserController
{
    private UserService $users;
    private ReservationService $reservations;
    private ReviewService $reviews;
    private GamificationService $gamification;

    public function __construct(
        ?UserService $users = null,
        ?ReservationService $reservations = null,
        ?ReviewService $reviews = null,
        ?GamificationService $gamification = null
    ) {
        $this->users = $users ?? new UserService();
        $this->reservations = $reservations ?? new ReservationService();
        $this->reviews = $reviews ?? new ReviewService();
        $this->gamification = $gamification ?? new GamificationService();
    }

    /**
     * @throws NotFoundException
     */
    public function profile(): void
    {
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error('Necesitas iniciar sesión para continuar.');
            $returnTo = $_SERVER['REQUEST_URI'] ?? '/';
            if ($returnTo === '' || $returnTo[0] !== '/' || \preg_match('/[\r\n]/', $returnTo) || \str_starts_with($returnTo, '//')) {
                $returnTo = '/';
            }
            Session::set('redirect_after_login', $returnTo);
            \header('Location: /login');
            exit;
        }

        $profile = $this->users->getProfile($userId);

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
    }

    /**
     * @throws ValidationException
     * @throws NotFoundException
     * @throws RandomException
     * @throws JsonException
     */
    public function update(): void
    {
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error('Necesitas iniciar sesión para continuar.');
            \header('Location: /login');
            exit;
        }

        $name = \trim((string) ($_POST['name'] ?? ''));
        $email = \strtolower(\trim((string) ($_POST['email'] ?? '')));

        $result = $this->users->updateProfile($userId, $name, $email);

        if (!$result->ok) {
            throw ValidationException::fromArray($result->data ?? ['error' => $result->error ?? 'No se pudo actualizar el perfil.']);
        }

        // Actualizar sesión
        $profile = $this->users->getProfile($userId);
        Session::set('user', $profile);

        Flash::success('Tu perfil se ha actualizado con éxito.');
        \header('Location: /perfil');
        exit;
    }

    /**
     * POST /perfil/password
     *
     * Cambia la contraseña del usuario actual.
     */
    public function changePassword(): void
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            // Backup defensivo (middleware 'auth' ya verificó)
            Flash::error('Necesitas iniciar sesión para continuar.');
            \header('Location: /login');
            exit;
        }

        // RECOPILACIÓN DE INPUTS
        $current = \trim((string) ($_POST['current_password'] ?? ''));
        $new = \trim((string) ($_POST['new_password'] ?? ''));
        $confirm = \trim((string) ($_POST['new_password_confirm'] ?? ''));

        $result = $this->users->changePassword($userId, $current, $new, $confirm);

        if (!$result->ok) {
            // Error en validación: mostrar genérico (no revelar si current es válida)
            Flash::error($result->error ?? 'No se pudo cambiar la contraseña.');
            \header('Location: /perfil');
            exit;
        }

        // UX: Confirmar cambio
        Flash::success('Tu contraseña se ha actualizado correctamente.');
        \header('Location: /perfil');
        exit;
    }

    /**
     * POST /account/avatar/upload
     *
     * Subir avatar del usuario
     */
    public function uploadAvatar(): void
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            \header('Content-Type: application/json');
            \http_response_code(401);
            echo \json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }

        // Validar que se haya enviado un archivo
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            \header('Content-Type: application/json');
            \http_response_code(400);
            echo \json_encode(['success' => false, 'message' => 'No se recibió ningún archivo válido']);
            exit;
        }

        $file = $_FILES['avatar'];

        // Validación de tipo MIME
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = \finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = \finfo_file($finfo, $file['tmp_name']);
        \finfo_close($finfo);

        if (!\in_array($mimeType, $allowedMimes, true)) {
            \header('Content-Type: application/json');
            \http_response_code(400);
            echo \json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG, PNG o WebP.']);
            exit;
        }

        // Validación de tamaño (2MB)
        $maxSize = 2 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            \header('Content-Type: application/json');
            \http_response_code(400);
            echo \json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 2MB.']);
            exit;
        }

        // Validación de extensión
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!\in_array($extension, $allowedExts, true)) {
            \header('Content-Type: application/json');
            \http_response_code(400);
            echo \json_encode(['success' => false, 'message' => 'Extensión de archivo no permitida.']);
            exit;
        }

        // Generar nombre único
        $filename = 'avatar_' . $userId . '_' . \time() . '.' . $extension;
        $uploadDir = __DIR__ . '/../../../storage/uploads/avatars/';

        // Crear directorio si no existe
        if (!\is_dir($uploadDir)) {
            \mkdir($uploadDir, 0755, true);
        }

        $uploadPath = $uploadDir . $filename;

        // Mover archivo
        if (!\move_uploaded_file($file['tmp_name'], $uploadPath)) {
            \header('Content-Type: application/json');
            \http_response_code(500);
            echo \json_encode(['success' => false, 'message' => 'Error al guardar el archivo.']);
            exit;
        }

        // Actualizar en base de datos
        $avatarUrl = '/storage/uploads/avatars/' . $filename;
        $result = $this->users->updateAvatar($userId, $avatarUrl);

        if (!$result->ok) {
            // Eliminar archivo si falla la actualización en BD
            \unlink($uploadPath);
            \header('Content-Type: application/json');
            \http_response_code(500);
            echo \json_encode(['success' => false, 'message' => $result->error ?? 'Error al actualizar el avatar.']);
            exit;
        }

        // Actualizar sesión
        $profile = $this->users->getProfile($userId);
        Session::set('user', $profile);

        \header('Content-Type: application/json');
        echo \json_encode([
            'success' => true,
            'message' => 'Avatar actualizado correctamente',
            'avatar_url' => $avatarUrl
        ]);
        exit;
    }

    /**
     * POST /account/avatar/delete
     *
     * Eliminar avatar del usuario
     */
    public function deleteAvatar(): void
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            \header('Content-Type: application/json');
            \http_response_code(401);
            echo \json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }

        // Obtener avatar actual
        $profile = $this->users->getProfile($userId);
        $currentAvatar = $profile['avatar'] ?? null;

        // Eliminar de base de datos
        $result = $this->users->updateAvatar($userId, null);

        if (!$result->ok) {
            \header('Content-Type: application/json');
            \http_response_code(500);
            echo \json_encode(['success' => false, 'message' => $result->error ?? 'Error al eliminar el avatar.']);
            exit;
        }

        // Eliminar archivo físico si existe y es local
        if ($currentAvatar && \str_starts_with($currentAvatar, '/storage/uploads/')) {
            $filePath = __DIR__ . '/../../../' . \ltrim($currentAvatar, '/');
            if (\file_exists($filePath)) {
                \unlink($filePath);
            }
        }

        // Actualizar sesión
        $profile = $this->users->getProfile($userId);
        Session::set('user', $profile);

        \header('Content-Type: application/json');
        echo \json_encode([
            'success' => true,
            'message' => 'Avatar eliminado correctamente'
        ]);
        exit;
    }

    /**
     * GET /account/export-data
     *
     * Exportar todos los datos personales del usuario (GDPR Art. 20 - Portabilidad)
     * @throws NotFoundException
     */
    public function exportData(): void
    {
        // VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = Session::userId();
        if ($userId === null) {
            Flash::error('Necesitas iniciar sesión para exportar tus datos.');
            \header('Location: /login');
            exit;
        }

        // Recopilar todos los datos personales
        $profile = $this->users->getProfile($userId);
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

        // Headers para descarga
        \header('Content-Type: application/json; charset=utf-8');
        \header('Content-Disposition: attachment; filename="komorebi-cafe-data-' . \date('Y-m-d') . '.json"');
        \header('Cache-Control: no-cache, no-store, must-revalidate');
        \header('Pragma: no-cache');
        \header('Expires: 0');

        echo \json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
