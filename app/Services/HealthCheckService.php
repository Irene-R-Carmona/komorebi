<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Result;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use Override;
use PDOException;

/**
 * Servicio de lógica de negocio para chequeos de salud animal.
 * Maneja validaciones, detección de alertas y orquestación de operaciones.
 *
 * @package App\Services
 */
final class HealthCheckService implements HealthCheckServiceInterface
{
    private HealthCheckRepositoryInterface $repository;

    // Umbrales para detección de alertas
    private const TEMPERATURE_HIGH_THRESHOLD = 39.5; // °C
    private const TEMPERATURE_LOW_THRESHOLD = 36.0;  // °C
    private const WEIGHT_MIN = 0.1;  // kg
    private const WEIGHT_MAX = 50.0; // kg

    public function __construct(HealthCheckRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Crear un nuevo chequeo de salud con detección automática de alertas.
     *
     * @param int $animalId ID del animal
     * @param int $keeperId ID del keeper que realiza el chequeo
     * @param array $data Datos del chequeo
     * @return Result Success con ID del chequeo o Error con mensaje
     */
    #[Override]
    public function createHealthCheck(int $animalId, int $keeperId, array $data): Result
    {
        // Validar que no exista ya un chequeo HOY para este animal
        if ($this->repository->exists($animalId, \date('Y-m-d'))) {
            return Result::fail('Ya existe un chequeo registrado hoy para este animal');
        }

        // Validar métricas físicas
        $validation = $this->validateMetrics($data);
        if (!$validation->ok) {
            return $validation;
        }

        // Detectar alertas automáticamente
        $alerts = $this->detectAlerts($data);

        // Preparar datos para inserción
        $checkData = [
            'animal_id' => $animalId,
            'checked_by' => $keeperId,
            'check_date' => $data['check_date'] ?? \date('Y-m-d'),
            'weight_kg' => $data['weight_kg'] ?? null,
            'temperature_c' => $data['temperature_c'] ?? null,
            'appetite' => $data['appetite'] ?? 'normal',
            'energy_level' => $data['energy_level'] ?? 'normal',
            'coat_condition' => $data['coat_condition'] ?? 'good',
            'eyes_clear' => $data['eyes_clear'] ?? true,
            'breathing_normal' => $data['breathing_normal'] ?? true,
            'mobility_normal' => $data['mobility_normal'] ?? true,
            'notes' => \trim($data['notes'] ?? ''),
            'alerts' => !empty($alerts) ? $alerts : null,
        ];

        try {
            $checkId = $this->repository->create($checkData);

            return Result::ok([
                'id' => $checkId,
                'alerts' => $alerts,
                'message' => 'Chequeo de salud registrado exitosamente',
            ]);
        } catch (PDOException $e) {
            return Result::fail('Error al guardar el chequeo: ' . $e->getMessage());
        }
    }

    /**
     * Obtener un chequeo por su ID con información completa.
     *
     * @param int $id ID del chequeo
     * @return array|null Datos del chequeo o null si no existe
     */
    #[Override]
    public function getCheckById(int $id): ?array
    {
        $check = $this->repository->findById($id);

        if ($check === null) {
            return null;
        }

        // Decodificar alertas JSON
        if (isset($check['alerts'])) {
            $check['alerts'] = \json_decode($check['alerts'], true);
        }

        return $check;
    }

    /**
     * Obtener datos para el dashboard de hoy: checks realizados + animales pendientes.
     *
     * @param int|null $cafeId Filtrar por café específico
     * @return array Array con 'completed' y 'pending'
     */
    #[Override]
    public function getTodayDashboard(?int $cafeId = null): array
    {
        $completedChecks = $this->repository->getTodayChecks();
        $pendingAnimals = $this->repository->getPendingAnimals($cafeId);

        // Decodificar alertas JSON en checks completados
        foreach ($completedChecks as &$check) {
            if (isset($check['alerts'])) {
                $check['alerts'] = \json_decode($check['alerts'], true);
            }
        }

        return [
            'completed' => $completedChecks,
            'pending' => $pendingAnimals,
            'completed_count' => \count($completedChecks),
            'pending_count' => \count($pendingAnimals),
        ];
    }

    /**
     * Obtener historial de chequeos de un animal.
     *
     * @param int $animalId ID del animal
     * @param int $limit Número de registros (default: 30)
     * @return array Lista de chequeos
     */
    #[Override]
    public function getAnimalHistory(int $animalId, int $limit = 30): array
    {
        $history = $this->repository->getCheckHistory($animalId, $limit);

        // Decodificar alertas JSON
        foreach ($history as &$check) {
            if (isset($check['alerts'])) {
                $check['alerts'] = \json_decode($check['alerts'], true);
            }
        }

        return $history;
    }

    /**
     * Obtener alertas activas de los últimos N días.
     *
     * @param int $days Días hacia atrás (default: 7)
     * @return array Checks con alertas
     */
    #[Override]
    public function getActiveAlerts(int $days = 7): array
    {
        $checksWithAlerts = $this->repository->getCheckswithAlerts($days);

        // Decodificar alertas JSON
        foreach ($checksWithAlerts as &$check) {
            if (isset($check['alerts'])) {
                $check['alerts'] = \json_decode($check['alerts'], true);
            }
        }

        return $checksWithAlerts;
    }

    /**
     * Verificar si un animal tiene chequeo registrado hoy.
     *
     * @param int $animalId ID del animal
     * @return bool True si tiene chequeo hoy
     */
    #[Override]
    public function hasCheckToday(int $animalId): bool
    {
        return $this->repository->exists($animalId, \date('Y-m-d'));
    }

    /**
     * Obtener estadísticas de productividad de un keeper.
     *
     * @param int $keeperId ID del keeper
     * @param string|null $startDate Fecha inicio (default: inicio del mes)
     * @param string|null $endDate Fecha fin (default: hoy)
     * @return array Estadísticas del keeper
     */
    #[Override]
    public function getKeeperStatistics(int $keeperId, ?string $startDate = null, ?string $endDate = null): array
    {
        $count = $this->repository->countByKeeperInPeriod($keeperId, $startDate, $endDate);

        return [
            'keeper_id' => $keeperId,
            'checks_count' => $count,
            'period_start' => $startDate ?? \date('Y-m-01'),
            'period_end' => $endDate ?? \date('Y-m-d'),
        ];
    }

    /**
     * Detectar alertas automáticamente basándose en umbrales y observaciones.
     *
     * @param array $data Datos del chequeo
     * @return array Lista de alertas detectadas
     */
    #[Override]
    public function detectAlerts(array $data): array
    {
        $alerts = [];

        // Alerta por temperatura alta (fiebre)
        if (isset($data['temperature_c']) && (float) $data['temperature_c'] > self::TEMPERATURE_HIGH_THRESHOLD) {
            $alerts[] = \sprintf(
                'Fiebre detectada: %.1f°C (normal: <%.1f°C)',
                (float) $data['temperature_c'],
                self::TEMPERATURE_HIGH_THRESHOLD
            );
        }

        // Alerta por temperatura baja (hipotermia)
        if (isset($data['temperature_c']) && (float) $data['temperature_c'] < self::TEMPERATURE_LOW_THRESHOLD) {
            $alerts[] = \sprintf(
                'Temperatura baja: %.1f°C (normal: >%.1f°C)',
                (float) $data['temperature_c'],
                self::TEMPERATURE_LOW_THRESHOLD
            );
        }

        // Alerta por falta de apetito
        if (isset($data['appetite']) && $data['appetite'] === 'none') {
            $alerts[] = 'Sin apetito - revisar con veterinario';
        } elseif (isset($data['appetite']) && $data['appetite'] === 'reduced') {
            $alerts[] = 'Apetito reducido - monitorear de cerca';
        }

        // Alerta por letargo severo (energía baja + movilidad reducida)
        $lowEnergy = isset($data['energy_level']) && $data['energy_level'] === 'low';
        $mobilityIssue = isset($data['mobility_normal']) && $data['mobility_normal'] === false;

        if ($lowEnergy && $mobilityIssue) {
            $alerts[] = 'Letargo severo - evaluación veterinaria urgente';
        } elseif ($lowEnergy) {
            $alerts[] = 'Nivel de energía bajo - revisar alimentación y descanso';
        } elseif ($mobilityIssue) {
            $alerts[] = 'Movilidad reducida - revisar posibles lesiones';
        }

        // Alerta por síntomas respiratorios
        $eyesIssue = isset($data['eyes_clear']) && $data['eyes_clear'] === false;
        $breathingIssue = isset($data['breathing_normal']) && $data['breathing_normal'] === false;

        if ($breathingIssue) {
            $alerts[] = 'Dificultad respiratoria - atención veterinaria requerida';
        } elseif ($eyesIssue) {
            $alerts[] = 'Ojos con secreción - posible infección ocular';
        }

        // Alerta por condición de pelaje pobre
        if (isset($data['coat_condition']) && $data['coat_condition'] === 'poor') {
            $alerts[] = 'Pelaje en mal estado - revisar nutrición y parásitos';
        }

        return $alerts;
    }

    /**
     * Validar métricas físicas del chequeo.
     *
     * @param array $data Datos a validar
     * @return Result Success si válido, Error con mensaje en caso contrario
     */
    private function validateMetrics(array $data): Result
    {
        // Validar peso si está presente
        if (isset($data['weight_kg'])) {
            $weight = (float) $data['weight_kg'];
            if ($weight < self::WEIGHT_MIN || $weight > self::WEIGHT_MAX) {
                return Result::fail(\sprintf(
                    'Peso fuera de rango: %.2f kg (rango válido: %.1f - %.1f kg)',
                    $weight,
                    self::WEIGHT_MIN,
                    self::WEIGHT_MAX
                ));
            }
        }

        // Validar temperatura si está presente
        if (isset($data['temperature_c'])) {
            $temp = (float) $data['temperature_c'];
            if ($temp < 30.0 || $temp > 45.0) {
                return Result::fail(\sprintf(
                    'Temperatura fuera de rango viable: %.1f°C (rango: 30.0 - 45.0°C)',
                    $temp
                ));
            }
        }

        // Validar ENUMs
        $validAppetite = ['normal', 'reduced', 'none'];
        if (isset($data['appetite']) && !\in_array($data['appetite'], $validAppetite, true)) {
            return Result::fail('Valor de apetito inválido. Opciones: ' . \implode(', ', $validAppetite));
        }

        $validEnergy = ['high', 'normal', 'low'];
        if (isset($data['energy_level']) && !\in_array($data['energy_level'], $validEnergy, true)) {
            return Result::fail('Nivel de energía inválido. Opciones: ' . \implode(', ', $validEnergy));
        }

        $validCoat = ['excellent', 'good', 'fair', 'poor'];
        if (isset($data['coat_condition']) && !\in_array($data['coat_condition'], $validCoat, true)) {
            return Result::fail('Condición de pelaje inválida. Opciones: ' . \implode(', ', $validCoat));
        }

        return Result::ok([]);
    }
}
