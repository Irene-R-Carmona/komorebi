<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AccountDeletionServiceInterface
{
    public function deleteAndAnonymize(int $userId): Result;
}
