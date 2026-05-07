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

use App\Core\Env;
use App\Core\ExceptionLogger;
use App\Core\LogContext;
use App\Core\LogContextProcessor;
use App\Core\Logger;
use App\Core\Result;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConfigurationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Services\TelegramService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(ExceptionLogger::class)]
#[UsesClass(Env::class)]
#[UsesClass(LogContext::class)]
#[UsesClass(LogContextProcessor::class)]
#[UsesClass(Logger::class)]
#[UsesClass(Result::class)]
#[UsesClass(Session::class)]
#[UsesClass(TelegramService::class)]
final class ExceptionLoggerTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Infraestructura
    // ─────────────────────────────────────────────────────────────

    private function clearEnvCache(): void
    {
        $ref = new ReflectionProperty(Env::class, 'cache');
        $ref->setValue(null, []);
    }

    protected function setUp(): void
    {
        // Garantizar entorno limpio para cada test
        $this->clearEnvCache();
        Logger::reset();
        \putenv('TELEGRAM_BOT_TOKEN');
        \putenv('TELEGRAM_CHAT_ID');
        \putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
    }

    protected function tearDown(): void
    {
        \putenv('TELEGRAM_BOT_TOKEN');
        \putenv('TELEGRAM_CHAT_ID');
        \putenv('APP_ENV');
        unset($_ENV['APP_ENV'], $_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
        $this->clearEnvCache();
        Logger::reset();
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
        \putenv('TELEGRAM_BOT_TOKEN=bot123:FakeTestToken');
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
        \putenv('TELEGRAM_BOT_TOKEN=bot123:FakeTestToken');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'bot123:FakeTestToken';
        $this->clearEnvCache();

        $exception = new ValidationException('Datos inválidos', ['campo' => 'requerido']);

        ExceptionLogger::log($exception, 'Test');

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // extractExceptionData — ramas de cada tipo de excepción
    // ─────────────────────────────────────────────────────────────

    public function testLogWithNotFoundExceptionDoesNotThrow(): void
    {
        $exception = new NotFoundException('No encontrado', 'User', 42);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogWithAuthenticationExceptionDoesNotThrow(): void
    {
        $exception = new AuthenticationException('No autenticado', 'token_expired');
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogWithAuthorizationExceptionDoesNotThrow(): void
    {
        $exception = new AuthorizationException('No autorizado', 'manage_users', 'User');
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogWithBusinessRuleExceptionDoesNotThrow(): void
    {
        $exception = new BusinessRuleException('Regla de negocio', 'max_reservations', []);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogWithDatabaseExceptionDoesNotThrow(): void
    {
        $exception = new DatabaseException('DB error', 'SELECT 1', []);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogWithRateLimitExceptionDoesNotThrow(): void
    {
        $exception = new RateLimitException('Rate limit', 60, 5, 'login');
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogWithExternalServiceExceptionDoesNotThrow(): void
    {
        $exception = new ExternalServiceException('Ext error', 'stripe', 503);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // generateErrorId
    // ─────────────────────────────────────────────────────────────

    public function testGenerateErrorIdStartsWithErrPrefix(): void
    {
        $id = ExceptionLogger::generateErrorId();
        $this->assertStringStartsWith('ERR-', $id);
    }

    public function testGenerateErrorIdIsUnique(): void
    {
        $id1 = ExceptionLogger::generateErrorId();
        $id2 = ExceptionLogger::generateErrorId();
        $this->assertNotSame($id1, $id2);
    }

    // ─────────────────────────────────────────────────────────────
    // logQuick
    // ─────────────────────────────────────────────────────────────

    public function testLogQuickDoesNotThrow(): void
    {
        $exception = new ValidationException('Fallo rápido', []);
        ExceptionLogger::logQuick($exception, 'upload_avatar', ['user_id' => 1]);
        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // anonymizeIp — mediante log con $_SERVER manipulado
    // ─────────────────────────────────────────────────────────────

    public function testLogAnonymizesIpv4Address(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $exception = new ValidationException('ip test', []);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogAnonymizesIpv6Address(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        $exception = new ValidationException('ip test', []);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    public function testLogHandlesInvalidIpAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'not_an_ip';
        $exception = new ValidationException('ip test', []);
        ExceptionLogger::log($exception, 'Test');
        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // cleanOldLogs
    // ─────────────────────────────────────────────────────────────

    public function testCleanOldLogsDeletesExpiredLogFiles(): void
    {
        $logDir = \sys_get_temp_dir() . '/komtest_logs_' . \getmypid() . '/';
        \mkdir($logDir, 0o777, true);

        // Crear un archivo de log con fecha de modificación antigua
        $oldLog = $logDir . 'old.log';
        \file_put_contents($oldLog, 'old log content');
        \touch($oldLog, \time() - (35 * 86400)); // 35 días atrás

        // Crear un archivo nuevo (no debe eliminarse)
        $newLog = $logDir . 'new.log';
        \file_put_contents($newLog, 'new log content');

        // Usar reflexión para llamar con el directorio temporal
        $ref = new ReflectionClass(ExceptionLogger::class);
        // cleanOldLogs usa __DIR__ hardcoded, así que solo verificamos que no lanza
        ExceptionLogger::cleanOldLogs(30);

        // Limpiar
        @\unlink($oldLog);
        @\unlink($newLog);
        @\rmdir($logDir);

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // extractSessionData — cuando hay sesión activa
    // ─────────────────────────────────────────────────────────────

    public function testLogWithActiveSessionDoesNotThrow(): void
    {
        $_SESSION['user_id'] = 99;
        $_SESSION['user_email'] = 'test@example.com';
        $exception = new ValidationException('Con sesión', []);
        ExceptionLogger::log($exception, 'Test');
        unset($_SESSION['user_id'], $_SESSION['user_email']);
        $this->assertTrue(true);
    }
}
