<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? MercurePublisherService::publish() — la única surface pública.
 * ¿Qué me quieres demostrar? Que el método retorna false cuando MERCURE_JWT_SECRET no está
 * configurado, sin lanzar excepciones y sin intentar conectarse al hub.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la guardia de
 * MERCURE_JWT_SECRET, si publish() lanza excepciones al no haber secret, o si el contrato
 * de retorno bool cambia.
 */

namespace Tests\Unit\Services;

use App\Services\MercurePublisherService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MercurePublisherService::class)]
final class MercurePublisherServiceTest extends TestCase
{
    private ?string $originalSecret = null;

    protected function setUp(): void
    {
        // Guardar estado previo para restaurarlo en tearDown
        $this->originalSecret = $_ENV['MERCURE_JWT_SECRET'] ?? null;
        unset($_ENV['MERCURE_JWT_SECRET']);
    }

    protected function tearDown(): void
    {
        if ($this->originalSecret !== null) {
            $_ENV['MERCURE_JWT_SECRET'] = $this->originalSecret;
        } else {
            unset($_ENV['MERCURE_JWT_SECRET']);
        }
    }

    /**
     * Test: publish() retorna false silenciosamente cuando MERCURE_JWT_SECRET no está configurado.
     * Este es el path seguro que evita intentar conectarse al hub sin credenciales.
     */
    public function testPublishReturnsFalseWhenSecretNotSet(): void
    {
        $result = MercurePublisherService::publish('kds/1/orders', ['order_id' => 42]);

        $this->assertFalse($result);
    }

    /**
     * Test: publish() retorna false también cuando el topic es vacío y sin secret.
     * El comportamiento de guardia se aplica independientemente del contenido del topic.
     */
    public function testPublishReturnsFalseForEmptyTopicWithoutSecret(): void
    {
        $result = MercurePublisherService::publish('', []);

        $this->assertFalse($result);
    }

    /**
     * Test: publish() con private=true también retorna false cuando falta el secret.
     * La flag private no altera el comportamiento de la guardia inicial.
     */
    public function testPublishReturnsFalseForPrivateEventWithoutSecret(): void
    {
        $result = MercurePublisherService::publish('waitlist/1', ['position' => 3], true);

        $this->assertFalse($result);
    }
}
