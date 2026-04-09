<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Middleware;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Cafe;
use App\Services\ReviewService;
use JsonException;
use Random\RandomException;

/**
 * Controlador de Reseñas
 *
 * Responsabilidades de este Controller:
 * - Crear/editar/eliminar reseñas de usuarios
 * - Moderación en backoffice (admin/supervisor)
 * - Validar reglas de negocio (ej: 1 reseña por café)
 */
final class ReviewController
{
    private ReviewService $reviewService;
    private Cafe $cafeModel;

    // ─────────────────────────────────────────────────────────────
    // Inyección de dependencias
    // ─────────────────────────────────────────────────────────────

    public function __construct(
        ?ReviewService $reviewService = null,
        ?Cafe $cafeModel = null
    ) {
        $this->reviewService = $reviewService ?? new ReviewService();
        $this->cafeModel = $cafeModel ?? new Cafe();
    }

    // ─────────────────────────────────────────────────────────────
    // POST /reviews - Crear reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /reviews
     *
     * Crea una nueva reseña del usuario en un café.
     *
     * MIDDLEWARE VALIDADO:
     * - ['auth', ['can', 'review.create']]
     * - Middleware garantiza: usuario autenticado + tiene permiso
     *
     * VALIDACIÓN DE CONTEXTO (este controller):
     * - [x] Usuario tiene reserva completada en este café
     * - [x] Usuario no tiene otra reseña en este café (policy)
     * - [x] Café existe y está activo
     *
     * FLUJO DE DATOS:
     * 1. Verificar CSRF token (POST seguro)
     * 2. Obtener user_id de sesión
     * 3. Recopilar inputs: cafe_id, rating, title, body
     * 4. VALIDAR CONTEXTO:
     *    a. ¿Café existe?
     *    b. ¿Usuario tiene reserva completada?
     *    c. ¿Ya reseñó este café?
     * 5. Llamar a ReviewService::createReview()
     * 6. Si OK: Flash success + redirect
     * 7. Si ERROR: Flash error + redirect
     *
     * REGLA DE NEGOCIO:
     * - Solo puede crear reseña quien tenga >=1 reserva completada
     * - Política: 1 reseña por usuario+café (no permite duplicadas)
     * - Reseña entra en estado "pending" hasta aprobación
     *
     * EXCEPCIONES MANEJADAS:
     * - Café no existe -> 404 lógico
     * - Sin reserva completada -> UX graceful (Flash warning)
     * - Ya reseñó -> UX graceful (Flash info)
     * - Error BD -> Flash error genérico + log
     * @throws BusinessRuleException
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     * @throws JsonException
     */
    public function create(): void
    {
        // [x] VALIDACIÓN PERMISO: Middleware ya validó 'review.create'

        // [x] VALIDACIÓN CONTEXTO: Usuario autenticado
        $userId = (Session::userId() ?? 0);
        if ($userId <= 0) {
            throw ValidationException::withMessage('Debes iniciar sesión para reseñar.', 401);
        }

        // [x] RECOPILACIÓN DE INPUTS
        $cafeId = (int) ($_POST['cafe_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 0);
        $title = \trim((string) ($_POST['title'] ?? ''));
        $body = \trim((string) ($_POST['body'] ?? ''));

        $errors = [];
        // [x] VALIDACIÓN: Inputs básicos
        if ($cafeId <= 0) {
            $errors['cafe_id'] = 'Café inválido';
        }
        if ($rating < 1 || $rating > 5) {
            $errors['rating'] = 'Valoración inválida';
        }

        if (!empty($errors)) {
            throw ValidationException::fromArray($errors);
        }

        // [x] VALIDACIÓN CONTEXTO: Café existe
        $cafe = $this->cafeModel->findById($cafeId);
        if (!$cafe) {
            throw NotFoundException::forResource('Café', $cafeId);
        }

        // [x] VALIDACIÓN CONTEXTO DELEGADA: Usuario tiene reserva completada
        if (!$this->reviewService->userHasCompletedReservation($userId, $cafeId)) {
            throw BusinessRuleException::withMessage('Debes tener una reserva completada en este café para reseñar.', 'no_completed_reservation', ['user_id' => $userId, 'cafe_id' => $cafeId]);
        }

        // [x] VALIDACIÓN CONTEXTO DELEGADA: Usuario no tiene otra reseña
        if ($this->reviewService->userHasReviewInCafe($userId, $cafeId)) {
            throw BusinessRuleException::withMessage('Ya has reseñado este café. Usa editar para actualizar tu reseña.', 'duplicate_review', ['user_id' => $userId, 'cafe_id' => $cafeId]);
        }

        // [x] LÓGICA DE NEGOCIO: Crear reseña
        $result = $this->reviewService->createReview($userId, $cafeId, $rating, $title, $body);

        if ($result->ok) {
            Flash::set('success', 'Tu reseña se ha enviado y está pendiente de aprobación. ¡Gracias por compartir tu experiencia!');
            \header('Location: /cafes/' . $cafeId);
            exit;
        }

        // El servicio devolvió un resultado de fallo — convertir a ValidationException
        throw ValidationException::withMessage($result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // POST /reviews/update - Editar reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /reviews/update
     *
     * Edita una reseña existente.
     *
     * MIDDLEWARE VALIDADO:
     * - ['auth', ['can', 'review.edit_own']]
     * - Middleware garantiza: usuario autenticado + tiene permiso
     *
     * VALIDACIÓN DE CONTEXTO (este controller):
     * - [x] Reseña existe
     * - [x] Reseña es del usuario actual (scope)
     *   O usuario tiene permiso 'review.moderate' (admin puede editar cualquiera)
     *
     * FLUJO DE DATOS:
     * 1. Verificar CSRF token
     * 2. Obtener user_id de sesión
     * 3. Recopilar inputs: review_id, rating, title, body
     * 4. VALIDAR CONTEXTO:
     *    a. ¿Reseña existe?
     *    b. ¿Es suya O es moderador?
     * 5. Llamar a ReviewService::updateReview()
     * 6. Si OK: Flash success + redirect
     *
     * NOTA: Reseña editada vuelve a estado "pending" (requiere re-aprobación)
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     * @throws JsonException
     */
    public function update(): void
    {
        // [x] VALIDACIÓN PERMISO: Middleware ya validó 'review.edit_own'

        $userId = (Session::userId() ?? 0);
        $reviewId = (int) ($_POST['id'] ?? 0);

        // [x] VALIDACIÓN CONTEXTO: Reseña existe
        $review = $this->reviewService->getReview($reviewId);
        if (!$review) {
            throw NotFoundException::forResource('Reseña', $reviewId);
        }

        // [x] VALIDACIÓN CONTEXTO: Reseña pertenece a usuario O es moderador
        $isOwner = ($review['user_id'] === $userId);
        $isModerator = Middleware::hasPermission('review.moderate');

        if (!$isOwner && !$isModerator) {
            throw ValidationException::withMessage('No tienes permiso para editar esta reseña.', 403);
        }

        // [x] RECOPILACIÓN DE INPUTS
        $rating = (int) ($_POST['rating'] ?? 0);
        $title = \trim((string) ($_POST['title'] ?? ''));
        $body = \trim((string) ($_POST['body'] ?? ''));

        // [x] LÓGICA DE NEGOCIO: Actualizar
        $result = $this->reviewService->updateReview($reviewId, $userId, $rating, $title, $body);

        if ($result->ok) {
            Flash::set('success', 'Tu reseña se ha actualizado y quedará pendiente de aprobación.');
            \header('Location: /perfil');
            exit;
        }

        throw ValidationException::withMessage($result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // POST /reviews/delete - Eliminar reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /reviews/delete
     *
     * Elimina una reseña.
     *
     * MIDDLEWARE VALIDADO:
     * - ['auth', ['can', 'review.delete_own']]
     *
     * VALIDACIÓN DE CONTEXTO:
     * - [x] Reseña existe
     * - [x] Reseña es del usuario (scope)
     *
     * FLUJO: Similar a update()
     * @throws NotFoundException
     * @throws RandomException
     * @throws ValidationException
     * @throws JsonException
     */
    public function delete(): void
    {
        // [x] VALIDACIÓN PERMISO: Middleware ya validó 'review.delete_own'

        $userId = (Session::userId() ?? 0);
        $reviewId = (int) ($_POST['id'] ?? 0);

        // [x] VALIDACIÓN CONTEXTO: Reseña existe
        $review = $this->reviewService->getReview($reviewId);
        if (!$review) {
            throw NotFoundException::forResource('Reseña', $reviewId);
        }

        // [x] VALIDACIÓN CONTEXTO: Reseña es del usuario
        if ($review['user_id'] !== $userId) {
            throw ValidationException::withMessage('No puedes eliminar esta reseña.', 403);
        }

        // [x] LÓGICA DE NEGOCIO: Eliminar
        $result = $this->reviewService->deleteReview($reviewId, $userId);

        if ($result->ok) {
            Flash::set('success', 'Reseña eliminada con éxito.');
            \header('Location: /perfil');
            exit;
        }

        throw ValidationException::withMessage($result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // GET /admin/reviews/pending - Listar pendientes (Moderación)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /admin/reviews/pending
     *
     * Muestra reseñas pendientes de aprobación.
     *
     * MIDDLEWARE VALIDADO:
     * - ['auth', ['can', 'review.moderate']]
     * - Middleware garantiza: usuario con permiso de moderación
     *
     * VALIDACIÓN DE CONTEXTO:
     * - [x] Middleware ya validó permiso (confiamos)
     * - No requiere validación adicional
     *
     * FLUJO:
     * 1. Obtener reseñas pendientes (paginadas)
     * 2. Renderizar vista de backoffice
     * @throws RandomException
     */
    public function pending(): void
    {
        // [x] VALIDACIÓN PERMISO: Middleware ya validó 'review.moderate'

        // [x] RECOPILACIÓN DE INPUTS: Paginación
        $page = \max(1, (int) ($_GET['page'] ?? 1));

        // [x] LÓGICA: Obtener reseñas pendientes
        $pending = $this->reviewService->listPendingReviews($page);

        // [x] RENDERIZAR: Vista de backoffice
        View::render('admin/reviews/pending', [
            'titulo' => 'Moderación de Reseñas',
            'pending' => $pending,
            'page' => $page,
            'csrf_token' => Csrf::token(),
            'extraJs' => ['admin/admin-reviews.js'],
        ], ['admin/admin-reviews.css'], 'backoffice');
    }

    // ─────────────────────────────────────────────────────────────
    // POST /admin/reviews/approve - Aprobar reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /admin/reviews/approve
     *
     * Aprueba una reseña (cambio de estado: pending → approved).
     *
     * MIDDLEWARE VALIDADO:
     * - ['auth', ['can', 'review.moderate']]
     *
     * VALIDACIÓN DE CONTEXTO:
     * - [x] Reseña existe
     * - [x] Reseña está en estado "pending"
     *
     * FLUJO:
     * 1. Obtener review_id
     * 2. Validar que existe y está pending
     * 3. Llamar a ReviewService::approveReview()
     * 4. Flash + redirect
     */
    public function approve(): void
    {
        // [x] VALIDACIÓN PERMISO: Middleware ya validó 'review.moderate'

        $reviewId = (int) ($_POST['id'] ?? 0);

        // [x] VALIDACIÓN CONTEXTO: Reseña existe
        $review = $this->reviewService->getReview($reviewId);
        if (!$review) {
            Flash::set('error', 'Reseña no encontrada.');
            \header('Location: /admin/reviews/pending');
            exit;
        }

        // [x] VALIDACIÓN CONTEXTO: Reseña está pending
        if ($review['status'] !== 'pending') {
            Flash::set('warning', 'Esta reseña no está pendiente de aprobación.');
            \header('Location: /admin/reviews/pending');
            exit;
        }

        // [x] LÓGICA DE NEGOCIO: Aprobar
        $result = $this->reviewService->approveReview($reviewId);

        if ($result->ok) {
            Flash::set('success', 'Reseña aprobada y publicada.');
            \header('Location: /admin/reviews/pending');
            exit;
        }

        Flash::set('error', $result->getMessage());
        \header('Location: /admin/reviews/pending');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // POST /admin/reviews/reject - Rechazar reseña
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /admin/reviews/reject
     *
     * Rechaza una reseña (cambio de estado: pending → rejected).
     *
     * MIDDLEWARE VALIDADO:
     * - ['auth', ['can', 'review.moderate']]
     *
     * VALIDACIÓN DE CONTEXTO:
     * - [x] Reseña existe
     * - [x] Reseña está en estado "pending"
     *
     * FLUJO:
     * 1. Obtener review_id y reason
     * 2. Validar que existe y está pending
     * 3. Llamar a ReviewService::rejectReview()
     * 4. Flash + redirect
     *
     * NOTA: Se envía notificación al usuario sobre rechazo + motivo
     */
    public function reject(): void
    {
        // [x] VALIDACIÓN PERMISO: Middleware ya validó 'review.moderate'

        $reviewId = (int) ($_POST['id'] ?? 0);
        $reason = \trim((string) ($_POST['reason'] ?? ''));

        // [x] VALIDACIÓN CONTEXTO: Reseña existe
        $review = $this->reviewService->getReview($reviewId);
        if (!$review) {
            Flash::set('error', 'Reseña no encontrada.');
            \header('Location: /admin/reviews/pending');
            exit;
        }

        // [x] VALIDACIÓN CONTEXTO: Reseña está pending
        if ($review['status'] !== 'pending') {
            Flash::set('warning', 'Esta reseña no está pendiente de aprobación.');
            \header('Location: /admin/reviews/pending');
            exit;
        }

        // [x] VALIDACIÓN: Motivo de rechazo (recomendado)
        if (!$reason) {
            Flash::set('warning', 'Se recomienda proporcionar un motivo de rechazo.');
        }

        // [x] LÓGICA DE NEGOCIO: Rechazar
        $result = $this->reviewService->rejectReview($reviewId, $reason);

        if ($result->ok) {
            Flash::set('success', 'Reseña rechazada. Se notificará al autor con el motivo.');
            \header('Location: /admin/reviews/pending');
            exit;
        }

        Flash::set('error', $result->getMessage());
        \header('Location: /admin/reviews/pending');
        exit;
    }
}
