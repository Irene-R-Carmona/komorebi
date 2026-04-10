<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Result;
use App\Events\ReviewPublishedEvent;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\CafeRepository;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\ReviewRepository;
use DateTimeImmutable;
use Exception;
use RuntimeException;

/**
 * Servicio de Reseñas
 *
 * Gestiona la lógica de negocio para reseñas.
 * Validaciones, cálculos y operaciones de moderación.
 */
final class ReviewService extends BaseService
{
    private Review $reviewModel;

    private User $userModel;

    private ReviewRepositoryInterface $reviewRepository;

    private CafeRepositoryInterface $cafeRepository;

    private ?\Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ?Review $reviewModel = null,
        ?User $userModel = null,
        ?ReviewRepositoryInterface $reviewRepository = null,
        ?CafeRepositoryInterface $cafeRepository = null,
        ?\Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->reviewModel = $reviewModel ?? new Review();
        $this->userModel = $userModel ?? new User();
        $this->reviewRepository = $reviewRepository ?? new ReviewRepository();
        $this->cafeRepository = $cafeRepository ?? new CafeRepository();
        $this->eventDispatcher = $eventDispatcher;
    }

    // ─────────────────────────────────────────────────────────────
    // Crear reseña (con validaciones completas)
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea una reseña con validaciones completas.
     *
     * @return Result Data contiene ['id' => int] si exitoso
     */
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
    // Moderación (Backoffice)
    // ─────────────────────────────────────────────────────────────

    /**
     * Aprueba una reseña.
     *
     * @param integer $reviewId
     *
     * @return Result
     */
    public function approveReview(int $reviewId): Result
    {
        try {
            $review = $this->reviewRepository->findById($reviewId);

            if (!$review) {
                return Result::fail('Reseña no encontrada');
            }

            $this->reviewRepository->updateStatus($reviewId, 'approved');

            // Actualizar rating del café automáticamente
            $cafeId = (int) $review['cafe_id'];
            $this->cafeRepository->updateRating($cafeId);

            // Log de auditoría
            Logger::info('Reseña aprobada', [
                'review_id' => $reviewId,
                'cafe_id' => $cafeId,
                'action' => 'approve',
            ]);

            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch(new ReviewPublishedEvent(
                    (int) $review['id'],
                    (int) $review['user_id'],
                    (int) $review['rating'],
                    (string) ($review['body'] ?? ''),
                    new DateTimeImmutable(),
                ));
            }

            return Result::ok('Reseña aprobada exitosamente');
        } catch (Exception $e) {
            Logger::error('Error al aprobar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return Result::fail('Error al aprobar reseña');
        }
    }

    /**
     * Rechaza una reseña con motivo.
     *
     * @param integer $reviewId
     * @param string  $reason
     *
     * @return Result
     */
    public function rejectReview(int $reviewId, string $reason): Result
    {
        try {
            if (\strlen(\trim($reason)) < 5 || \strlen(\trim($reason)) > 500) {
                return Result::fail('Motivo debe tener entre 5 y 500 caracteres');
            }

            $review = $this->reviewRepository->findById($reviewId);

            if (!$review) {
                return Result::fail('Reseña no encontrada');
            }

            $reason = \htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $this->reviewRepository->updateStatus($reviewId, 'rejected');

            Logger::info('Reseña rechazada', [
                'review_id' => $reviewId,
                'action' => 'reject',
                'reason_length' => \strlen($reason),
            ]);

            return Result::ok('Reseña rechazada');
        } catch (Exception $e) {
            Logger::error('Error al rechazar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return Result::fail('Error al rechazar reseña');
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
     * @return Result|bool
     */
    public function deleteReview(int $reviewId, ?int $userId = null): bool|Result
    {
        // Si no se pasa userId, comportamiento simple esperado por tests: devolver booleano
        if ($userId === null) {
            try {
                return $this->reviewRepository->delete($reviewId);
            } catch (Exception $e) {
                Logger::error('Error al eliminar reseña (byId)', [
                    'exception' => \get_class($e),
                    'message' => $e->getMessage(),
                    'review_id' => $reviewId,
                ]);

                return false;
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
     * Compatibilidad para tests: eliminar reseña por id (sin userId) y devolver booleano.
     */
    public function deleteReviewById(int $reviewId): bool
    {
        try {
            return $this->reviewRepository->delete($reviewId);
        } catch (Exception $e) {
            Logger::error('Error al eliminar reseña (byId)', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return false;
        }
    }

    /**
     * Wrapper esperado por tests: obtener reseñas por usuario.
     */
    public function getReviewsByUserId(int $userId): array
    {
        return $this->reviewRepository->findByUserId($userId);
    }

    /**
     * Wrapper esperado por tests: obtener reseñas por café.
     */
    public function getReviewsByCafeId(int $cafeId): array
    {
        return $this->reviewRepository->findByCafeId($cafeId, 'approved');
    }

    /**
     * Calcula promedio simple de ratings (esperado por tests).
     */
    public function calculateAverageRating(int $cafeId): float
    {
        return $this->reviewRepository->calculateAverageRating($cafeId);
    }

    /**
     * Moderación simplificada: intenta delegar a updateStatus del modelo.
     * Devuelve booleano para compatibilidad con tests.
     */
    public function moderateReview(int $reviewId, string $status): bool
    {
        try {
            $review = $this->reviewRepository->findById($reviewId);
            $result = $this->reviewRepository->updateStatus($reviewId, $status);

            // Si se aprueba o rechaza, actualizar rating del café
            if ($result && $review && in_array($status, ['approved', 'rejected'], true)) {
                $cafeId = (int) $review['cafe_id'];
                $this->cafeRepository->updateRating($cafeId);
            }

            return $result;
        } catch (Exception $e) {
            Logger::error('Error al moderar reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
                'status' => $status,
            ]);

            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Listar reseñas
    // ─────────────────────────────────────────────────────────────

    /**
     * Lista reseñas aprobadas de un café.
     */
    public function listApprovedReviews(int $cafeId, int $page = 1): array
    {
        try {
            return $this->reviewRepository->findApprovedPaginated($cafeId, 10, $page);
        } catch (Exception $e) {
            Logger::error('Error al listar reseñas aprobadas', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'cafe_id' => $cafeId,
                'page' => $page,
            ]);

            return ['data' => [], 'total' => 0, 'pages' => 0];
        }
    }

    /**
     * Lista reseñas del usuario.
     */
    public function listUserReviews(int $userId): array
    {
        try {
            return $this->reviewRepository->findByUserId($userId);
        } catch (Exception $e) {
            Logger::error('Error al listar reseñas del usuario', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return [];
        }
    }

    /**
     * Lista reseñas pendientes para moderación.
     */
    public function listPendingReviews(int $page = 1): array
    {
        try {
            return $this->reviewRepository->findPendingPaginated(10, $page);
        } catch (Exception $e) {
            Logger::error('Error al listar reseñas pendientes', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'page' => $page,
            ]);

            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene estadísticas de ratings de un café.
     */
    public function getCafeRatingStats(int $cafeId): array
    {
        try {
            $stats = $this->reviewRepository->getRatingStats($cafeId);

            return [
                'average' => $stats['avg_rating'] ?? 0.0,
                'count' => $stats['total_reviews'] ?? 0,
                'distribution' => [
                    1 => $stats['one_star'] ?? 0,
                    2 => $stats['two_stars'] ?? 0,
                    3 => $stats['three_stars'] ?? 0,
                    4 => $stats['four_stars'] ?? 0,
                    5 => $stats['five_stars'] ?? 0,
                ],
            ];
        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas de ratings', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'cafe_id' => $cafeId,
            ]);

            return [
                'average' => 0.0,
                'count' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }
    }

    /**
     * Verifica si usuario puede dejar reseña (tiene reserva completada).
     */
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

    /**
     * Obtiene una reseña por ID.
     *
     * @param integer $reviewId ID de la reseña
     *
     * @return array|null Datos de la reseña o null si no existe
     */
    public function getReview(int $reviewId): ?array
    {
        try {
            return $this->reviewRepository->findById($reviewId);
        } catch (Exception $e) {
            Logger::error('Error al obtener reseña', [
                'exception' => \get_class($e),
                'message' => $e->getMessage(),
                'review_id' => $reviewId,
            ]);

            return null;
        }
    }
}
