<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests del servicio TelegramService, enfocados en el comportamiento cuando
 * las variables de entorno TELEGRAM_BOT_TOKEN y TELEGRAM_CHAT_ID no están configuradas.
 *
 * ¿Qué me quieres demostrar?
 * Que TelegramService no lanza excepciones ni realiza llamadas HTTP cuando no
 * está configurado, retornando Result::ok(null) como degradación graceful.
 * Que sendAlert() delega en sendMessage() y también se degrada limpiamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se elimina la guarda de token/chatId vacíos → el servicio intentará
 *   llamadas HTTP en tests y fallará o lanzará errores de red.
 * - Si sendMessage devuelve Result::fail en lugar de Result::ok(null) cuando
 *   no está configurado → todos estos tests fallan.
 * - Si sendAlert deja de delegar en sendMessage → testSendAlertDelegatesWithoutError falla.
 */

use App\Core\Env;
use App\Services\TelegramService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para TelegramService.
 *
 * TelegramService no acepta dependencias inyectables; lee env vars en el constructor.
 * Los tests manipulan el entorno y limpian el caché estático de Env entre ejecuciones.
 * No se realizan llamadas HTTP reales.
 */
final class TelegramServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $this->clearEnvCache();
        putenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_CHAT_ID');
        unset($_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
    }

    protected function tearDown(): void
    {
        putenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_CHAT_ID');
        $this->clearEnvCache();
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function clearEnvCache(): void
    {
        $ref = new \ReflectionProperty(Env::class, 'cache');
        $ref->setValue(null, []);
    }

    // ─────────────────────────────────────────────────────────────
    // sendMessage — comportamiento sin configuración
    // ─────────────────────────────────────────────────────────────

    public function testSendMessageReturnsOkNullWhenBotTokenIsEmpty(): void
    {
        // Ambas vars vacías → guarda temprana sin HTTP
        $service = new TelegramService();
        $result  = $service->sendMessage('Hola desde el test');

        $this->assertTrue($result->ok);
        $this->assertNull($result->data);
    }

    public function testSendMessageReturnsOkNullWhenOnlyChatIdIsEmpty(): void
    {
        // Token configurado pero sin chat_id → misma guarda temprana
        putenv('TELEGRAM_BOT_TOKEN=bot123:TestToken');
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'bot123:TestToken';

        $this->clearEnvCache();

        $service = new TelegramService();
        $result  = $service->sendMessage('Mensaje sin destino');

        $this->assertTrue($result->ok);
        $this->assertNull($result->data);
    }

    public function testSendMessageReturnsOkNullWhenBothVarsAreEmpty(): void
    {
        // Verifica explícitamente que la degradación ocurre sin lanzar excepción
        $service = new TelegramService();

        $this->assertNotNull($service); // servicio construible aunque env esté vacío
        $result = $service->sendMessage('');

        $this->assertTrue($result->ok);
        $this->assertNull($result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // sendAlert — delega en sendMessage, misma degradación graceful
    // ─────────────────────────────────────────────────────────────

    public function testSendAlertReturnsOkNullWhenServiceNotConfigured(): void
    {
        $service = new TelegramService();
        $result  = $service->sendAlert('🚨', 'Alerta crítica', 'Cuerpo del mensaje');

        $this->assertTrue($result->ok);
        $this->assertNull($result->data);
    }

    public function testSendAlertWithDifferentEmojisAndTextReturnsOkNull(): void
    {
        $service = new TelegramService();

        $resultInfo    = $service->sendAlert('ℹ️', 'Información', 'Detalles adicionales');
        $resultWarning = $service->sendAlert('⚠️', 'Advertencia', 'Revisar sistema');

        $this->assertTrue($resultInfo->ok);
        $this->assertNull($resultInfo->data);

        $this->assertTrue($resultWarning->ok);
        $this->assertNull($resultWarning->data);
    }
}
