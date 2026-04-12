<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Result;
use App\Models\Allergen;
use App\Services\Contracts\AllergenServiceInterface;

/**
 * AllergenService — capa fina de negocio sobre el modelo Allergen
 */
final class AllergenService implements AllergenServiceInterface
{
    private Allergen $model;

    public function __construct(?Allergen $model = null)
    {
        $this->model = $model ?? new Allergen();
    }

    /**
     * Listado de alérgenos
     *
     * @return array<int,array>
     */
    #[\Override]
    public function listAll(bool $orderBySeverity = true): array
    {
        return $this->model->getAll($orderBySeverity);
    }

    #[\Override]
    public function getById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    #[\Override]
    public function getByName(string $name): ?array
    {
        return $this->model->findByName($name);
    }

    #[\Override]
    public function getByProduct(int $productId): Result
    {
        if ($productId <= 0) {
            return Result::fail('productId inválido', 'validation_error');
        }

        return Result::ok($this->model->getByProduct($productId));
    }

    #[\Override]
    public function getProductIds(int $allergenId): Result
    {
        if ($allergenId <= 0) {
            return Result::fail('allergenId inválido', 'validation_error');
        }

        return Result::ok($this->model->getProductIds($allergenId));
    }

    #[\Override]
    public function getStatistics(): array
    {
        return $this->model->getStatistics();
    }

    /**
     * Crear alérgeno. data keys: name (required), optional: code, japanese_name/name_jp, icon_class/icon, icon_color, severity, description
     */
    #[\Override]
    public function create(array $data): Result
    {
        // mínima validación: name
        $name = \trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return Result::fail('El nombre es obligatorio', 'validation_error');
        }

        // normalizar severity si viene
        if (isset($data['severity'])) {
            $data['severity'] = \trim((string) $data['severity']);
        }

        return Result::ok($this->model->create($data));
    }

    #[\Override]
    public function update(int $id, array $data): Result
    {
        if ($id <= 0) {
            return Result::fail('ID inválido', 'validation_error');
        }

        return Result::ok($this->model->update($id, $data));
    }

    #[\Override]
    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): Result
    {
        if ($productId <= 0 || $allergenId <= 0) {
            return Result::fail('IDs inválidos', 'validation_error');
        }

        return Result::ok($this->model->attachProduct($productId, $allergenId, $notes));
    }

    #[\Override]
    public function detachFromProduct(int $productId, int $allergenId): Result
    {
        if ($productId <= 0 || $allergenId <= 0) {
            return Result::fail('IDs inválidos', 'validation_error');
        }

        return Result::ok($this->model->detachProduct($productId, $allergenId));
    }
}
