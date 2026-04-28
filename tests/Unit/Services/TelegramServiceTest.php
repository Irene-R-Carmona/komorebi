<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? TelegramService: comportamiento cuando no hay configuración de bot.
 * ¿Qué me quieres demostrar? Que sendMessage retorna ok (silently skip) cuando el token no está configurado.
 * ¿Qué va a fallar en este test si se cambia el código? Si se lanza excepción o fail en vez de ok cuando no hay token.
 */

namespace Tests\Unit\Services;

use App\Services\TelegramService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramService::class)]
final class TelegramServiceTest extends TestCase
{
    public function testSendMessageReturnsOkWhenNotConfigured(): void
    {
        // Without TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID env vars, the service silently skips
        $service = new TelegramService();
        $result  = $service->sendMessage('Test message');

        $this->assertTrue($result->ok);
    }

    public function testSendAlertReturnsOkWhenNotConfigured(): void
    {
        $service = new TelegramService();
        $result  = $service->sendAlert('🔔', 'Test Alert', 'Test body');

        $this->assertTrue($result->ok);
    }
}
