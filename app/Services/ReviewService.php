<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Result;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Contracts\ReviewServiceInterface;
use Exception;
use RuntimeException;

/**
 * Servicio de Reseñas
 *
 * Gestiona la lógica de negocio para reseñas: creación, edición y eliminación
 * por el propietario, más verificación de elegibilidad.
 */
final class ReviewService extends BaseService implements ReviewServiceInterface
{
    private User $userModel;

    private ReviewRepositoryInterface $reviewRepository;

    public function __construct(
        User $userModel,
        ReviewRepositoryInterface $reviewRepository
    ) {
        $this->userModel = $userModel;
        $this->reviewRepository = $reviewRepository;
    }

    // ─────────────────────────────────────────────────────────────
    // Crear / Editar / Eliminar reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea una reseña con validaciones completas.
     *
     * @return Result Data contiene ['id' => int] si exitoso
     */
    #[\Override]
    public function createReview(
        int $userId,
        int $cafeId,
        int $rating,
        string $title,
        string $body
    ): Result {
        try {
            // 1. Validar usuario existe y está activo
            $user = $this->userModel->findById($userId);

            if (!$user) {
                return Result::fail('Usuario no encontrado');
            }

            if (!$user['is_active']) {
                return Result::fail('Tu cuenta está desactivada');
            }

            // 2. Validar datos
            if ($rating < 1 || $rating > 5) {
                return Result::fail('Rating debe estar entre 1 y 5');
            }

            if (\strlen(\trim($title)) < 3 || \strlen(\trim($title)) > 100) {
                return Result::fail('Título debe tener entre 3 y 100 caracteres');
            }

            if (\strlen(\trim($body)) < 10 || \strlen(\trim($body)) > 5000) {
                return Result::fail('Descripción debe tener entre 10 y 5000 caracteres');
            }

            // 3. Sanitizar HTML
            $title = \htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $body = \htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

            // 4. Crear reseña usando repository
            $reviewId = $this->reviewRepository->create([
                'user_id' => $userId,
                'cafe_id' => $cafeId,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'status' => 'pending',
            ]);

            return Result::ok(['id' => $reviewId]);
        } catch (RuntimeException $e) {
            return Result::fail($e->getMessage());
        } catch (Exception $e) {
            Logger::error('Error al crear reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'cafe_id' => $cafeId,
                'trace' => $e->getTraceAsString(),
            ]);

            return Result::fail('Error al crear reseña. Intenta más tarde.');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Editar/Eliminar reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * Edita una reseña (solo propietario).
     *
     * @param integer $reviewId
     * @param integer $userId
     * @param integer $rating
     * @param string  $title
     * @param string  $body
     *
     * @return Result
     */
    #[\Override]
    public function updateReview(
        int $reviewId,
        int $userId,
        int $rating,
        string $title,
        string $body
    ): Result {
        try {
            $review = $this->reviewRepository->findById($reviewId);

            if (!$review) {
                return Result::fail('Reseña no encontrada');
            }

            // Verificar propiedad
            if ((int) $review['user_id'] !== $userId) {
                return Result::fail('No puedes editar esta reseña');
            }

            // Validar datos
            if ($rating < 1 || $rating > 5) {
                return Result::fail('Rating debe estar entre 1 y 5');
            }

            if (\strlen(\trim($title)) < 3 || \strlen(\trim($title)) > 100) {
                return Result::fail('Título debe tener entre 3 y 100 caracteres');
            }

            if (\strlen(\trim($body)) < 10 || \strlen(\trim($body)) > 5000) {
                return Result::fail('Descripción debe tener entre 10 y 5000 caracteres');
            }

            // Sanitizar
            $title = \htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $body = \htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

            // Actualizar (vuelve a pending)
            $this->reviewRepository->update($reviewId, [
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'status' => 'pending',
            ]);

            return Result::ok('Reseña actualizada exitosamente');
        } catch (Exception $e) {
            Logger::error('Error al editar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
                'user_id' => $userId,
            ]);

            return Result::fail('Error al editar reseña');
        }
    }

    /**
     * Elimina una reseña (solo propietario).
     *
     * @return Result
     */
    #[\Override]
    public function deleteReview(int $reviewId, ?int $userId = null): Result
    {
        // Si no se pasa userId, eliminar directamente sin verificar propiedad
        if ($userId === null) {
            try {
                $deleted = $this->reviewRepository->delete($reviewId);

                return $deleted ? Result::ok(null) : Result::fail('No se pudo eliminar la reseña', 'delete_failed');
            } catch (Exception $e) {
                Logger::error('Error al eliminar reseña (byId)', [
                    'exception' => \get_class($e),
                    'message' => $e->getMessage(),
                    'review_id' => $reviewId,
                ]);

                return Result::fail('Error al eliminar reseña', 'delete_error');
            }
        }

        // Si se pasa userId, comportamiento existente que retorna Result
        try {
            $review = $this->reviewRepository->findById($reviewId);

            if (!$review) {
                return Result::fail('Reseña no encontrada');
            }

            // Verificar propiedad
            if ((int) $review['user_id'] !== $userId) {
                return Result::fail('No puedes eliminar esta reseña');
            }

            $this->reviewRepository->delete($reviewId);

            return Result::ok('Reseña eliminada exitosamente');
        } catch (Exception $e) {
            Logger::error('Error al eliminar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
                'user_id' => $userId,
            ]);

            return Result::fail('Error al eliminar reseña');
        }
    }

    /**
     * Verifica si usuario puede dejar reseña (tiene reserva completada).
     */
    #[\Override]
    public function canUserReview(int $userId, int $cafeId): array
    {
        try {
            // Verificar si ya existe reseña
            if ($this->reviewRepository->userHasReview($userId, $cafeId)) {
                return ['can_review' => false, 'reason' => 'Ya has dejado una reseña para este café'];
            }

            return ['can_review' => true];
        } catch (Exception $e) {
            Logger::error('Error al verificar elegibilidad para reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'cafe_id' => $cafeId,
            ]);

            return ['can_review' => false, 'reason' => 'Error al verificar eligibilidad'];
        }
    }

    /**
     * Verifica si el usuario tiene una reserva completada en el café
     *
     * @param integer $userId
     * @param integer $cafeId
     *
     * @return boolean
     */
    #[\Override]
    public function userHasCompletedReservation(int $userId, int $cafeId): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                SELECT COUNT(*)
                FROM reservations
                WHERE user_id = :user_id
                AND cafe_id = :cafe_id
                AND status = "completed"
            ');
            $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            Logger::error('Error al verificar reserva completada', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'cafe_id' => $cafeId,
            ]);

            return false;
        }
    }

    /**
     * Verifica si el usuario ya tiene una reseña en el café
     *
     * @param integer $userId
     * @param integer $cafeId
     *
     * @return boolean
     */
    #[\Override]
    public function userHasReviewInCafe(int $userId, int $cafeId): bool
    {
        try {
            return $this->reviewRepository->userHasReview($userId, $cafeId);
        } catch (Exception $e) {
            Logger::error('Error al verificar reseña existente', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'cafe_id' => $cafeId,
            ]);

            return false;
        }
    }
}
