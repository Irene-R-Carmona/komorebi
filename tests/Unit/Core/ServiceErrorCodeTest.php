<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? El enum ServiceErrorCode.
 * ¿Qué me quieres demostrar? Que cada case produce typeUri(), toHttpStatus() y toTitle() correctos,
 * y que tryFrom() funciona para integración con ProblemDetails.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la URI base, el status HTTP
 * o el título de algún case del enum.
 */

use App\Core\ServiceErrorCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceErrorCode::class)]
final class ServiceErrorCodeTest extends TestCase
{
    // ── typeUri ──────────────────────────────────────────────────────────

    public function testTypeUriContainsEnumValue(): void
    {
        foreach (ServiceErrorCode::cases() as $case) {
            $this->assertStringContainsString(
                $case->value,
                $case->typeUri(),
                "typeUri() for case {$case->name} must include its value"
            );
        }
    }

    public function testTypeUriIsAbsoluteHttpsUrl(): void
    {
        foreach (ServiceErrorCode::cases() as $case) {
            $this->assertStringStartsWith(
                'https://',
                $case->typeUri(),
                "typeUri() for case {$case->name} must be an absolute HTTPS URL"
            );
        }
    }

    public function testTypeUriFormat(): void
    {
        $this->assertSame(
            'https://komorebi.cafe/errors/not_found',
            ServiceErrorCode::NOT_FOUND->typeUri()
        );
    }

    // ── toHttpStatus ─────────────────────────────────────────────────────

    public function testNotFoundIs404(): void
    {
        $this->assertSame(404, ServiceErrorCode::NOT_FOUND->toHttpStatus());
    }

    public function testUnauthorizedIs401(): void
    {
        $this->assertSame(401, ServiceErrorCode::UNAUTHORIZED->toHttpStatus());
    }

    public function testForbiddenIs403(): void
    {
        $this->assertSame(403, ServiceErrorCode::FORBIDDEN->toHttpStatus());
    }

    public function testValidationErrorIs422(): void
    {
        $this->assertSame(422, ServiceErrorCode::VALIDATION_ERROR->toHttpStatus());
    }

    public function testMissingFieldIs422(): void
    {
        $this->assertSame(422, ServiceErrorCode::MISSING_FIELD->toHttpStatus());
    }

    public function testBusinessRuleIs400(): void
    {
        $this->assertSame(400, ServiceErrorCode::BUSINESS_RULE->toHttpStatus());
    }

    public function testConflictIs409(): void
    {
        $this->assertSame(409, ServiceErrorCode::CONFLICT->toHttpStatus());
    }

    public function testServerErrorIs500(): void
    {
        $this->assertSame(500, ServiceErrorCode::SERVER_ERROR->toHttpStatus());
    }

    // ── toTitle ──────────────────────────────────────────────────────────

    public function testTitleIsNonEmpty(): void
    {
        foreach (ServiceErrorCode::cases() as $case) {
            $this->assertNotEmpty($case->toTitle(), "toTitle() for {$case->name} must not be empty");
        }
    }

    public function testNotFoundTitle(): void
    {
        $this->assertSame('Not Found', ServiceErrorCode::NOT_FOUND->toTitle());
    }

    public function testServerErrorTitle(): void
    {
        $this->assertSame('Internal Server Error', ServiceErrorCode::SERVER_ERROR->toTitle());
    }

    // ── tryFrom / backed string ───────────────────────────────────────────

    public function testTryFromKnownValue(): void
    {
        $case = ServiceErrorCode::tryFrom('not_found');
        $this->assertSame(ServiceErrorCode::NOT_FOUND, $case);
    }

    public function testTryFromUnknownValueReturnsNull(): void
    {
        $case = ServiceErrorCode::tryFrom('totally_unknown_code');
        $this->assertNull($case);
    }

    public function testAllCasesAreSnakeCase(): void
    {
        foreach (ServiceErrorCode::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $case->value,
                "Enum value for {$case->name} must be snake_case"
            );
        }
    }
}
