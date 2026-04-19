<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CircuitBreaker;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Result;
use App\Exceptions\CircuitOpenException;
use App\Services\Contracts\TelegramServiceInterface;
use Override;
use RuntimeException;

final class TelegramService implements TelegramServiceInterface
{
    private string $botToken;
    private string $chatId;
    private string $apiUrl = 'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->botToken = Env::get('TELEGRAM_BOT_TOKEN', '');
        $this->chatId = Env::get('TELEGRAM_CHAT_ID', '');
    }

    #[Override]
    public function sendMessage(string $text, ?string $chatId = null): Result
    {
        if ($this->botToken === '' || $this->chatId === '') {
            Logger::warning('[TelegramService] Bot token or chat ID not configured, skipping notification');

            return Result::ok(null);
        }

        $targetChat = $chatId ?? $this->chatId;
        $url = $this->apiUrl . $this->botToken . '/sendMessage';

        $payload = \json_encode([
            'chat_id' => $targetChat,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        if ($payload === false) {
            Logger::error('[TelegramService] Failed to encode payload', ['chat_id' => $targetChat]);

            return Result::fail('No se pudo codificar el mensaje', 'telegram_encode_failed');
        }

        $context = \stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . \strlen($payload),
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        try {
            $response = CircuitBreaker::call('telegram', static function () use ($url, $context): string {
                $result = @\file_get_contents($url, false, $context);
                if ($result === false) {
                    throw new RuntimeException('HTTP request failed');
                }

                return $result;
            });
        } catch (CircuitOpenException) {
            Logger::warning('[TelegramService] Circuit breaker abierto, omitiendo notificación de Telegram');

            return Result::ok(null);
        } catch (RuntimeException) {
            Logger::error('[TelegramService] Failed to send message', ['chat_id' => $targetChat]);

            return Result::fail('No se pudo enviar el mensaje de Telegram', 'telegram_send_failed');
        }

        $data = \json_decode($response, true);
        if (!($data['ok'] ?? false)) {
            Logger::error('[TelegramService] Telegram API error', ['response' => $data]);

            return Result::fail('Error en la API de Telegram', 'telegram_api_error');
        }

        Logger::info('[TelegramService] Message sent', ['chat_id' => $targetChat]);

        return Result::ok($data);
    }

    #[Override]
    public function sendAlert(string $emoji, string $title, string $body): Result
    {
        $text = "{$emoji} <b>{$title}</b>\n\n{$body}";

        return $this->sendMessage($text);
    }
}
