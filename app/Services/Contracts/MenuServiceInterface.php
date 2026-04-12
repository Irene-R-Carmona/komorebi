<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface MenuServiceInterface
{
    public function getCategories(bool $includeExperiences = false): array;

    public function getProductsByCategory(array $excludeAllergens = []): array;

    public function getAllProducts(): array;

    public function getPasses(): array;

    public function getPassesForCafe(?string $cafeCategory = null, ?string $animalType = null): array;

    public function getMenuForView(array $excludeAllergens = []): array;

    public function getAllergens(): array;
}
