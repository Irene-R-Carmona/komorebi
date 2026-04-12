<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Services\Contracts\CafeServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Café Management Controller (Manager scope)
 *
 * Permite al manager gestionar la configuración de su café asignado.
 * Scope: Solo puede editar el café especificado en user.cafe_id
 */
final class CafeController
{
    private CafeServiceInterface $cafeService;

    private ResponseFactory $response;

    public function __construct(
        ?CafeServiceInterface $cafeService = null,
        ?ResponseFactory $response = null
    ) {
        $this->cafeService = $cafeService ?? Container::make(CafeServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /manager/cafe
     *
     * Vista de configuración del café asignado al manager
     * Tabs: Info General, Horarios, Zonas, Configuración
     */
    public function show(): void
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            View::render('errors/403', [
                'message' => 'No tienes un café asignado.',
            ]);

            return;
        }

        $cafe = $this->cafeService->getById($cafeId);

        if (!$cafe) {
            View::render('errors/404', [
                'message' => 'Café no encontrado.',
            ]);

            return;
        }

        View::render('manager/cafe/show', [
            'titulo' => 'Gestión de Café',
            'cafe' => $cafe,
            'csrf_token' => Csrf::token(),
        ], ['manager/cafe-management.css'], 'backoffice');
    }

    /**
     * POST /manager/cafe/capacity
     *
     * Actualiza la capacidad y aforo del café
     */
    public function updateCapacity(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json([
                'success' => false,
                'error' => 'No tienes un café asignado',
            ], 403);
        }

        $body = $request->getParsedBody();
        $capacityMax = (int) ($body['capacity_max'] ?? 0);

        // Validaciones de negocio
        if ($capacityMax <= 0) {
            return $this->response->json([
                'success' => false,
                'error' => 'La capacidad debe ser mayor a 0',
            ], 400);
        }

        if ($capacityMax > 500) {
            return $this->response->json([
                'success' => false,
                'error' => 'La capacidad máxima permitida es 500',
            ], 400);
        }

        try {
            $this->cafeService->update($cafeId, [
                'capacity_max' => $capacityMax,
            ]);

            return $this->response->json([
                'success' => true,
                'message' => 'Capacidad actualizada correctamente',
                'capacity_max' => $capacityMax,
            ]);
        } catch (\Exception $e) {
            return $this->response->json([
                'success' => false,
                'error' => 'Error al actualizar capacidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /manager/cafe/schedule
     *
     * Actualiza los horarios de apertura/cierre
     */
    public function updateSchedule(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json([
                'success' => false,
                'error' => 'No tienes un café asignado',
            ], 403);
        }

        $body = $request->getParsedBody();
        $openingTime = $body['opening_time'] ?? '';
        $closingTime = $body['closing_time'] ?? '';

        // Validar formato HH:MM o HH:MM:SS
        if (!$this->isValidTime($openingTime) || !$this->isValidTime($closingTime)) {
            return $this->response->json([
                'success' => false,
                'error' => 'Formato de hora inválido. Use HH:MM',
            ], 400);
        }

        // Validar que apertura < cierre
        if ($this->compareTime($openingTime, $closingTime) >= 0) {
            return $this->response->json([
                'success' => false,
                'error' => 'La hora de apertura debe ser menor que la de cierre',
            ], 400);
        }

        try {
            $this->cafeService->update($cafeId, [
                'opening_time' => $this->normalizeTime($openingTime),
                'closing_time' => $this->normalizeTime($closingTime),
            ]);

            return $this->response->json([
                'success' => true,
                'message' => 'Horarios actualizados correctamente',
                'opening_time' => $this->normalizeTime($openingTime),
                'closing_time' => $this->normalizeTime($closingTime),
            ]);
        } catch (\Exception $e) {
            return $this->response->json([
                'success' => false,
                'error' => 'Error al actualizar horarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /manager/cafe/settings
     *
     * Actualiza configuración específica del café
     * (descripción, política de cancelación, precio base)
     */
    public function updateSettings(ServerRequestInterface $request): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        if (!$cafeId) {
            return $this->response->json([
                'success' => false,
                'error' => 'No tienes un café asignado',
            ], 403);
        }

        $body = $request->getParsedBody();
        $updates = [];

        // Descripción
        if (isset($body['description'])) {
            $description = \trim($body['description']);
            if (\mb_strlen($description) > 2000) {
                return $this->response->json([
                    'success' => false,
                    'error' => 'La descripción no puede superar 2000 caracteres',
                ], 400);
            }
            $updates['description'] = $description;
        }

        // Precio por hora
        if (isset($body['price_per_hour'])) {
            $price = (int) $body['price_per_hour'];
            if ($price < 0 || $price > 100) {
                return $this->response->json([
                    'success' => false,
                    'error' => 'El precio debe estar entre 0 y 100€',
                ], 400);
            }
            $updates['price_per_hour'] = $price;
        }

        if (empty($updates)) {
            return $this->response->json([
                'success' => false,
                'error' => 'No hay campos para actualizar',
            ], 400);
        }

        try {
            $this->cafeService->update($cafeId, $updates);

            return $this->response->json([
                'success' => true,
                'message' => 'Configuración actualizada correctamente',
                'updates' => $updates,
            ]);
        } catch (\Exception $e) {
            return $this->response->json([
                'success' => false,
                'error' => 'Error al actualizar configuración: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Valida formato de tiempo HH:MM o HH:MM:SS
     */
    private function isValidTime(string $time): bool
    {
        return (bool) \preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time);
    }

    /**
     * Compara dos tiempos (HH:MM)
     * Retorna: < 0 si time1 < time2, 0 si iguales, > 0 si time1 > time2
     */
    private function compareTime(string $time1, string $time2): int
    {
        $t1 = \strtotime($this->normalizeTime($time1));
        $t2 = \strtotime($this->normalizeTime($time2));

        return $t1 <=> $t2;
    }

    /**
     * Normaliza tiempo a formato HH:MM:SS
     */
    private function normalizeTime(string $time): string
    {
        if (\preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }
}
