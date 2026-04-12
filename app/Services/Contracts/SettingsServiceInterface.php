<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface SettingsServiceInterface
{
    public function getAll(): array;

    public function get(string $key, mixed $default = null): mixed;

    public function update(string $key, mixed $value, ?int $userId = null): bool;

    public function updateBulk(array $settings, ?string $group = null, ?int $userId = null): int;

    public function getByGroup(string $group): array;

    public function isSmtpEnabled(): bool;

    public function getSmtpConfig(): array;

    public function validate(): array;

    public function getStats(): array;

    public function resetToDefault(string $key): bool;
}
