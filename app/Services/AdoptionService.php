<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Repositories\Contracts\AdoptionRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\Contracts\AdoptionServiceInterface;
use Override;

/**
 * Gestiona el ciclo de adopción de animales del café.
 *
 * Flujo:
 *  1. Usuario solicita adoptar un animal disponible.
 *  2. Keeper aprueba o rechaza la solicitud.
 *  3. Al aprobar, el animal queda marcado como adoptado (adopted_at/adopted_by).
 */
final class AdoptionService implements AdoptionServiceInterface
{
    public function __construct(
        private readonly AdoptionRepositoryInterface $adoptionRepo,
        private readonly AnimalRepositoryInterface $animalRepo,
    ) {}

    // ─── Consultas ────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getAdoptableAnimals(): array
    {
        return $this->adoptionRepo->findAdoptable();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getPendingRequests(?int $cafeId = null): array
    {
        return $this->adoptionRepo->findPendingRequests($cafeId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getUserRequests(int $userId): array
    {
        return $this->adoptionRepo->findRequestsByUser($userId);
    }

    // ─── Acciones del usuario ─────────────────────────────────────────────────

    #[Override]
    public function requestAdoption(int $userId, int $animalId, ?string $message): Result
    {
        // Verificar que el animal es adoptable comprobando si aparece en la vista
        $adoptable = $this->adoptionRepo->findAdoptable();
        $ids = \array_column($adoptable, 'id');

        if (!\in_array($animalId, $ids, true)) {
            return Result::fail('El animal no está disponible para adopción.', 'animal_not_adoptable');
        }

        if ($this->adoptionRepo->hasPendingRequest($animalId, $userId)) {
            return Result::fail('Ya tienes una solicitud pendiente para este animal.', 'request_already_exists');
        }

        $requestId = $this->adoptionRepo->createRequest($animalId, $userId, $message);

        Logger::info('[AdoptionService] Nueva solicitud de adopción creada', [
            'request_id' => $requestId,
            'animal_id' => $animalId,
            'user_id' => $userId,
        ]);

        return Result::ok($requestId);
    }

    #[Override]
    public function withdrawRequest(int $userId, int $requestId): Result
    {
        $request = $this->adoptionRepo->findRequestById($requestId);

        if ($request === null) {
            return Result::fail('Solicitud no encontrada.', 'request_not_found');
        }

        if ((int) $request['user_id'] !== $userId) {
            return Result::fail('No tienes permiso para retirar esta solicitud.', 'forbidden');
        }

        if ($request['status'] !== 'pending') {
            return Result::fail('Solo se pueden retirar solicitudes pendientes.', 'request_not_pending');
        }

        $this->adoptionRepo->updateRequest($requestId, 'withdrawn', null, null);

        Logger::info('[AdoptionService] Solicitud de adopción retirada', [
            'request_id' => $requestId,
            'user_id' => $userId,
        ]);

        return Result::ok();
    }

    // ─── Acciones del Keeper ──────────────────────────────────────────────────

    #[Override]
    public function approveRequest(int $keeperId, int $requestId, int $cafeId): Result
    {
        $request = $this->adoptionRepo->findRequestById($requestId);

        if ($request === null) {
            return Result::fail('Solicitud no encontrada.', 'request_not_found');
        }

        if ($request['status'] !== 'pending') {
            return Result::fail('Solo se pueden aprobar solicitudes pendientes.', 'request_not_pending');
        }

        if ((int) $request['animal_cafe_id'] !== $cafeId) {
            return Result::fail('No tienes permiso para gestionar adopciones de otros cafés.', 'forbidden');
        }

        $this->adoptionRepo->updateRequest($requestId, 'approved', $keeperId, null);
        $this->animalRepo->markAsAdopted((int) $request['animal_id'], (int) $request['user_id']);

        Logger::info('[AdoptionService] Solicitud de adopción aprobada', [
            'request_id' => $requestId,
            'animal_id' => $request['animal_id'],
            'adopted_by' => $request['user_id'],
            'keeper_id' => $keeperId,
        ]);

        return Result::ok();
    }

    #[Override]
    public function rejectRequest(int $keeperId, int $requestId, ?string $notes, int $cafeId): Result
    {
        $request = $this->adoptionRepo->findRequestById($requestId);

        if ($request === null) {
            return Result::fail('Solicitud no encontrada.', 'request_not_found');
        }

        if ($request['status'] !== 'pending') {
            return Result::fail('Solo se pueden rechazar solicitudes pendientes.', 'request_not_pending');
        }

        if ((int) $request['animal_cafe_id'] !== $cafeId) {
            return Result::fail('No tienes permiso para gestionar adopciones de otros cafés.', 'forbidden');
        }

        $this->adoptionRepo->updateRequest($requestId, 'rejected', $keeperId, $notes);

        Logger::info('[AdoptionService] Solicitud de adopción rechazada', [
            'request_id' => $requestId,
            'keeper_id' => $keeperId,
        ]);

        return Result::ok();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getProcessedRequests(?int $cafeId = null): array
    {
        return $this->adoptionRepo->findProcessedRequests($cafeId);
    }
}
