<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\CafeServiceInterface;
use App\Support\TimeHelper;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST: Gestión del café asignado (Manager scope)
 *
 * Rutas (bajo /api/v1/manager):
 * - PUT /cafe/capacity → updateCapacity()
 * - PUT /cafe/schedule → updateSchedule()
 * - PUT /cafe/settings → updateSettings()
 */
final class CafeApiController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly CafeServiceInterface $cafeService,
    ) {
        parent::__construct($response);
    }

    /**
     * PUT /api/v1/manager/cafe/capacity
     *
     * Actualiza la capacidad máxima del café asignado al manager.
     */
    public function updateCapacity(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $capacityMax = (int) ($body['capacity_max'] ?? 0);

        if ($capacityMax <= 0) {
            return $this->badRequest('La capacidad debe ser mayor a 0', 'capacity_invalid');
        }

        if ($capacityMax > 500) {
            return $this->badRequest('La capacidad máxima permitida es 500', 'capacity_too_high');
        }

        try {
            $this->cafeService->update($cafeId, ['capacity_max' => $capacityMax]);

            return $this->success([
                'message' => 'Capacidad actualizada correctamente',
                'capacity_max' => $capacityMax,
            ]);
        } catch (Exception $e) {
            return $this->serverError('Error al actualizar capacidad: ' . $e->getMessage(), 'update_failed');
        }
    }

    /**
     * PUT /api/v1/manager/cafe/schedule
     *
     * Actualiza los horarios de apertura/cierre del café.
     */
    public function updateSchedule(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $openingTime = (string) ($body['opening_time'] ?? '');
        $closingTime = (string) ($body['closing_time'] ?? '');

        if (!TimeHelper::isValid($openingTime) || !TimeHelper::isValid($closingTime)) {
            return $this->badRequest('Formato de hora inválido. Use HH:MM', 'time_format_invalid');
        }

        if (TimeHelper::compare($openingTime, $closingTime) >= 0) {
            return $this->badRequest('La hora de apertura debe ser menor que la de cierre', 'time_order_invalid');
        }

        try {
            $this->cafeService->update($cafeId, [
                'opening_time' => TimeHelper::normalize($openingTime),
                'closing_time' => TimeHelper::normalize($closingTime),
            ]);

            return $this->success([
                'message' => 'Horarios actualizados correctamente',
                'opening_time' => TimeHelper::normalize($openingTime),
                'closing_time' => TimeHelper::normalize($closingTime),
            ]);
        } catch (Exception $e) {
            return $this->serverError('Error al actualizar horarios: ' . $e->getMessage(), 'update_failed');
        }
    }

    /**
     * PUT /api/v1/manager/cafe/settings
     *
     * Actualiza descripción y/o precio por hora del café.
     */
    public function updateSettings(ServerRequestInterface $request): ResponseInterface
    {
        $cafeId = Session::userCafeId();

        if (!$cafeId) {
            return $this->forbidden('No tienes un café asignado', 'cafe_not_assigned');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $updates = [];

        if (isset($body['description'])) {
            $description = \trim((string) $body['description']);
            if (\mb_strlen($description) > 2000) {
                return $this->badRequest('La descripción no puede superar 2000 caracteres', 'description_too_long');
            }
            $updates['description'] = $description;
        }

        if (isset($body['price_per_hour'])) {
            $price = (int) $body['price_per_hour'];
            if ($price < 0 || $price > 100) {
                return $this->badRequest('El precio debe estar entre 0 y 100€', 'price_out_of_range');
            }
            $updates['price_per_hour'] = $price;
        }

        if ($updates === []) {
            return $this->badRequest('No hay campos para actualizar', 'no_fields');
        }

        try {
            $this->cafeService->update($cafeId, $updates);

            return $this->success([
                'message' => 'Configuración actualizada correctamente',
                'updates' => $updates,
            ]);
        } catch (Exception $e) {
            return $this->serverError('Error al actualizar configuración: ' . $e->getMessage(), 'update_failed');
        }
    }
}
