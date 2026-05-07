<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface FileUploadServiceInterface
{
    public function uploadAvatar(array $file, int $userId): Result;

    public function deleteAvatar(int $userId): Result;

    public function uploadAnimalPhoto(array $file, int $animalId): Result;

    public function deleteFile(string $relativeUrl): Result;

    public function getUploadLimits(string $type = 'avatar'): array;
}
