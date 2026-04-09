<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface TelegramServiceInterface
{
    public function sendMessage(string $text, ?string $chatId = null): Result;

    public function sendAlert(string $emoji, string $title, string $body): Result;
}
