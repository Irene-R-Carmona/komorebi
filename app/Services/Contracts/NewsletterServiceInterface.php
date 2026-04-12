<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface NewsletterServiceInterface
{
    public function subscribe(string $email): array;

    public function confirm(string $token): array;

    public function unsubscribe(string $token): array;

    public function getConfirmedEmails(): array;
}
