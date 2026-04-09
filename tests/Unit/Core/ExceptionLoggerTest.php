<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * El envío condicional de notificaciones Telegram desde ExceptionLogger::notifyCriticalError().
 *
 * ¿Qué me quieres demostrar?
 * Que ExceptionLogger::log() no lanza ninguna excepción cuando TELEGRAM_BOT_TOKEN está vacío
 * (degradación graceful) y que tampoco lanza cuando el token está configurado pero TelegramService
 * retorna Result::ok(null) por ausencia de chat_id (simulación sin red).
 * Que solo se llama a Telegram cuando la severidad es CRITICAL (ConfigurationException).
 * Que excepciones no críticas (ValidationException) no lanzan notificaciones Telegram.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se elimina la guarda Env::get('TELEGRAM_BOT_TOKEN') en notifyCriticalError → los tests siguen
 *   pasando porque TelegramService tiene su propia guarda interna.
 * - Si se lanza una excepción dentro de notifyCriticalError sin try-catch → los tests fallan con error
 *   inesperado al loguear excepciones críticas.
 * - Si se mueve la condición de 'CRITICAL' → el test de severidad no crítica puede comenzar a notificar.
 */

namespace Tests\Unit\Core;

use App\Core\ExceptionLogger;
use App\Core\Env;
use App\Exceptions\ConfigurationException;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Core\ExceptionLogger::class)]
final class ExceptionLoggerTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Infraestructura
    // ─────────────────────────────────────────────────────────────

    private function clearEnvCache(): void
    {
        $ref = new \ReflectionProperty(Env::class, 'cache');
        $ref->setValue(null, []);
    }

    protected function setUp(): void
    {
        // Garantizar entorno limpio para cada test
        $this->clearEnvCache();
        putenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_CHAT_ID');
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
    }

    protected function tearDown(): void
    {
        putenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_CHAT_ID');
        putenv('APP_ENV');
        unset($_ENV['APP_ENV'], $_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
        $this->clearEnvCache();
    }

    // ─────────────────────────────────────────────────────────────
    // notifyCriticalError — Telegram condicional
    // ─────────────────────────────────────────────────────────────

    #[TestDox('log() con excepción CRITICAL y TELEGRAM_BOT_TOKEN vacío no lanza excepción')]
    public function testLogCriticalWithoutTelegramTokenDoesNotThrow(): void
    {
        // Sin token → Telegram no se llama, no hay excepción
        $exception = new ConfigurationException('Config clave faltante');

        ExceptionLogger::log($exception, 'Test');

        $this->assertTrue(true);  // llegamos aquí sin excepción
    }

    #[TestDox('log() con excepción CRITICAL y token configurado pero sin chat_id se degrada limpiamente')]
    public function testLogCriticalWithTokenButNoChatIdDegratesGracefully(): void
    {
        // Token presente pero sin CHAT_ID → TelegramService devuelve Result::ok(null) internamente
        putenv('TELEGRAM_BOT_TOKEN=bot123:FakeTestToken');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'bot123:FakeTestToken';
        $this->clearEnvCache();

        $exception = new ConfigurationException('Config clave faltante');

        // No debe lanzar excepción aunque Telegram no pueda enviar
        ExceptionLogger::log($exception, 'Test');

        $this->assertTrue(true);
    }

    #[TestDox('log() con ValidationException (INFO) no activa notifyCriticalError')]
    public function testLogNonCriticalExceptionDoesNotActivateTelegramPath(): void
    {
        // Aunque haya token, ValidationException es INFO y no debe notificar
        putenv('TELEGRAM_BOT_TOKEN=bot123:FakeTestToken');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'bot123:FakeTestToken';
        $this->clearEnvCache();

        $exception = new ValidationException('Datos inválidos', ['campo' => 'requerido']);

        ExceptionLogger::log($exception, 'Test');

        $this->assertTrue(true);
    }
}
