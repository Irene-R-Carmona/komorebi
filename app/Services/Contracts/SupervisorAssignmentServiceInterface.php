<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface SupervisorAssignmentServiceInterface
{
    public function createFromRequest(): Result;

    public function createFromArray(array $input): Result;

    public function listAssignments(): Result;
}
