<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface NewsletterServiceInterface
{
    public function subscribe(string $email): Result;

    public function confirm(string $token): array;

    public function unsubscribe(string $token): array;

    public function getConfirmedEmails(): array;
}
