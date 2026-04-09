<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Core\Session;
use App\Repositories\Contracts\SupervisorAssignmentRepositoryInterface;

/**
 * Servicio que encapsula la lógica de creación y consulta de asignaciones
 * de mesas para el módulo de Supervisor.
 *
 * Responsable de:
 * - Validar el payload
 * - Enriquecer con contexto de sesión (supervisor_id, cafe_id)
 * - Persistir y consultar via SupervisorAssignmentRepository
 *
 * La validación CSRF es responsabilidad exclusiva del middleware PSR-15.
 */
final class SupervisorAssignmentService
{
    public function __construct(
        private readonly SupervisorAssignmentRepositoryInterface $repo,
    ) {}

    /**
     * Crea una asignación leyendo el cuerpo JSON de la petición HTTP.
     */
    public function createFromRequest(): Result
    {
        $raw = (string) file_get_contents('php://input');

        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Result::fail('JSON inválido en el cuerpo de la petición', 'invalid_json');
        }

        if (!is_array($input)) {
            return Result::fail('Payload inválido: se esperaba un objeto JSON', 'invalid_payload');
        }

        return $this->createFromArray($input);
    }

    /**
     * Crea una asignación a partir de un array de datos.
     *
     * Si `supervisor_id` o `cafe_id` no están en el array, se obtienen
     * de la sesión activa del usuario autenticado.
     *
     * @param array<string, mixed> $input
     */
    public function createFromArray(array $input): Result
    {
        $reservationId = isset($input['reservation_id']) ? (int) $input['reservation_id'] : 0;
        $tableCode     = trim((string) ($input['table_code'] ?? ''));
        $supervisorId  = isset($input['supervisor_id'])
            ? (int) $input['supervisor_id']
            : (int) (Session::user()['id'] ?? 0);
        $cafeId        = isset($input['cafe_id'])
            ? (int) $input['cafe_id']
            : (int) (Session::user()['cafe_id'] ?? 0);

        if ($reservationId <= 0) {
            return Result::fail('reservation_id es requerido y debe ser mayor que 0', 'validation_error');
        }

        if ($tableCode === '') {
            return Result::fail('table_code es requerido', 'validation_error');
        }

        if ($supervisorId <= 0) {
            return Result::fail('No se pudo determinar el supervisor de la sesión', 'auth_error');
        }

        if ($cafeId <= 0) {
            return Result::fail('No se pudo determinar el café de la sesión', 'auth_error');
        }

        try {
            $id     = $this->repo->createAssignment([
                'supervisor_id'  => $supervisorId,
                'reservation_id' => $reservationId,
                'table_code'     => $tableCode,
                'cafe_id'        => $cafeId,
            ]);
            $record = $this->repo->findById($id);

            Logger::info('[SupervisorAssignmentService] Asignación creada', [
                'id'             => $id,
                'reservation_id' => $reservationId,
                'table_code'     => $tableCode,
                'supervisor_id'  => $supervisorId,
            ]);

            return Result::ok($record);
        } catch (\Throwable $e) {
            Logger::error('[SupervisorAssignmentService] Error al crear asignación', [
                'exception' => $e->getMessage(),
            ]);

            return Result::fail('Error al guardar la asignación en base de datos', 'db_error');
        }
    }

    /**
     * Devuelve todas las asignaciones registradas.
     */
    public function listAssignments(): Result
    {
        try {
            $rows = $this->repo->findAll();

            return Result::ok($rows);
        } catch (\Throwable $e) {
            Logger::error('[SupervisorAssignmentService] Error al listar asignaciones', [
                'exception' => $e->getMessage(),
            ]);

            return Result::fail('Error al obtener las asignaciones', 'db_error');
        }
    }
}
