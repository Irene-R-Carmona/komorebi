<?php

declare(strict_types=1);

namespace App\Domain\DTO;

interface DomainTransferObject
{
    public function toViewArray(): array;
}
