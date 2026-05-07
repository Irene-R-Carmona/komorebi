<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * SendEmailJob valida el payload antes de intentar enviar y protege contra
 * duplicados via idempotency key.
 *
 * ¿Qué me quieres demostrar?
 * Que validatePayload() lanza ExternalServiceException cuando faltan campos
 * requeridos (to, subject, body) o el email tiene formato inválido.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cambios en los campos requeridos, en el tipo de excepción lanzada, o en la
 * validación del formato de email.
 */

namespace Tests\Unit\Jobs;

use App\Exceptions\ExternalServiceException;
use App\Jobs\SendEmailJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendEmailJob::class)]
final class SendEmailJobTest extends TestCase
{
    private SendEmailJob $job;

    protected function setUp(): void
    {
        $this->job = new SendEmailJob();
    }

    // ──────────────────────────────────────────────────────────
    // Payload validation — missing required fields
    // ──────────────────────────────────────────────────────────

    public function testHandleThrowsWhenToIsMissing(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'subject' => 'Test',
            'body' => '<p>Hello</p>',
        ]);
    }

    public function testHandleThrowsWhenToIsEmpty(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => '',
            'subject' => 'Test',
            'body' => '<p>Hello</p>',
        ]);
    }

    public function testHandleThrowsWhenSubjectIsMissing(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'user@example.com',
            'body' => '<p>Hello</p>',
        ]);
    }

    public function testHandleThrowsWhenSubjectIsEmpty(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'user@example.com',
            'subject' => '',
            'body' => '<p>Hello</p>',
        ]);
    }

    public function testHandleThrowsWhenBodyIsMissing(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'user@example.com',
            'subject' => 'Test',
        ]);
    }

    public function testHandleThrowsWhenBodyIsEmpty(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'user@example.com',
            'subject' => 'Test',
            'body' => '',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Payload validation — invalid email format
    // ──────────────────────────────────────────────────────────

    public function testHandleThrowsOnInvalidEmailFormat(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'not-an-email',
            'subject' => 'Test',
            'body' => '<p>Hello</p>',
        ]);
    }

    public function testHandleThrowsOnEmailWithoutDomain(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'user@',
            'subject' => 'Test',
            'body' => '<p>Hello</p>',
        ]);
    }

    public function testHandleThrowsOnEmailWithSpaces(): void
    {
        $this->expectException(ExternalServiceException::class);
        $this->job->handle([
            'to' => 'user @example.com',
            'subject' => 'Test',
            'body' => '<p>Hello</p>',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Exception message content
    // ──────────────────────────────────────────────────────────

    public function testExceptionMessageMentionsMissingField(): void
    {
        try {
            $this->job->handle([
                'subject' => 'Test',
                'body' => '<p>Hello</p>',
            ]);
            self::fail('Expected ExternalServiceException');
        } catch (ExternalServiceException $e) {
            self::assertStringContainsString('to', $e->getMessage());
        }
    }
}
