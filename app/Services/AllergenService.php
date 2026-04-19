<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Result;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use App\Services\Contracts\AllergenServiceInterface;
use Override;

/**
 * AllergenService — capa de aplicación sobre AllergenRepositoryInterface.
 *
 * Responsabilidad: validación de entrada, mapeo de errores a Result.
 * Sin SQL: toda la persistencia delega en AllergenRepositoryInterface.
 */
final class AllergenService implements AllergenServiceInterface
{
    public function __construct(
        private readonly AllergenRepositoryInterface $repository,
    ) {}

    #[Override]
    public function listAll(bool $orderBySeverity = true): array
    {
        return $this->repository->findAll($orderBySeverity);
    }

    #[Override]
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    #[Override]
    public function getByName(string $name): ?array
    {
        return $this->repository->findByName($name);
    }

    #[Override]
    public function getByProduct(int $productId): Result
    {
        if ($productId <= 0) {
            return Result::fail('productId inválido', 'validation_error');
        }

        return Result::ok($this->repository->findByProduct($productId));
    }

    #[Override]
    public function getProductIds(int $allergenId): Result
    {
        if ($allergenId <= 0) {
            return Result::fail('allergenId inválido', 'validation_error');
        }

        return Result::ok($this->repository->getProductIds($allergenId));
    }

    #[Override]
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    #[Override]
    public function create(array $data): Result
    {
        $name = \trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return Result::fail('El nombre es obligatorio', 'validation_error');
        }

        if (isset($data['severity'])) {
            $data['severity'] = \trim((string) $data['severity']);
        }

        return Result::ok($this->repository->create($data));
    }

    #[Override]
    public function update(int $id, array $data): Result
    {
        if ($id <= 0) {
            return Result::fail('ID inválido', 'validation_error');
        }

        return Result::ok($this->repository->update($id, $data));
    }

    #[Override]
    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): Result
    {
        if ($productId <= 0 || $allergenId <= 0) {
            return Result::fail('IDs inválidos', 'validation_error');
        }

        return Result::ok($this->repository->attachToProduct($productId, $allergenId, $notes));
    }

    #[Override]
    public function detachFromProduct(int $productId, int $allergenId): Result
    {
        if ($productId <= 0 || $allergenId <= 0) {
            return Result::fail('IDs inválidos', 'validation_error');
        }

        return Result::ok($this->repository->detachFromProduct($productId, $allergenId));
    }
}
