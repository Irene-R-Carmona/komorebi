<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Que SendTelegramNotificationJob delega en TelegramService.
 * ¿Qué me quieres demostrar? Que el job llama sendAlert con los parámetros correctos.
 * ¿Qué va a fallar? Si se cambia el contrato icon/title/message del job.
 */

namespace Tests\Unit\Jobs;

use App\Core\Result;
use App\Jobs\SendTelegramNotificationJob;
use App\Services\Contracts\TelegramServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SendTelegramNotificationJob::class)]
final class SendTelegramNotificationJobTest extends TestCase
{
    public function testHandleCallsTelegramServiceWithPayload(): void
    {
        $telegram = $this->createMock(TelegramServiceInterface::class);
        $telegram->expects($this->once())
            ->method('sendAlert')
            ->with('🆕', 'Test title', 'Test body')
            ->willReturn(Result::ok(null));

        $job = new SendTelegramNotificationJob($telegram);
        $job->handle([
            'icon' => '🆕',
            'title' => 'Test title',
            'message' => 'Test body',
        ]);
    }

    public function testHandleLogsAndContinuesOnTelegramFailure(): void
    {
        $telegram = $this->createMock(TelegramServiceInterface::class);
        $telegram->method('sendAlert')->willThrowException(new RuntimeException('timeout'));

        $job = new SendTelegramNotificationJob($telegram);

        // Should not throw
        $this->expectNotToPerformAssertions();
        $job->handle(['icon' => '🔔', 'title' => 't', 'message' => 'm']);
    }
}
