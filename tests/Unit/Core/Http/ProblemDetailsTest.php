<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ProblemDetails::fromResult() con ServiceErrorCode y context.
 * ¿Qué me quieres demostrar? Que cuando el code del Result coincide con un case del enum,
 * se usa typeUri() y toTitle(); y que el context se incluye como extension members RFC 9457.
 * ¿Qué va a fallar en este test si se cambia el código? Si ProblemDetails deja de usar
 * el enum para type/title, o si deja de incluir context como extension members.
 */

use App\Core\Http\ProblemDetails;
use App\Core\Result;
use App\Core\ServiceErrorCode;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsTest extends TestCase
{
    // ── type URI ─────────────────────────────────────────────────────────

    public function testKnownCodeUsesEnumTypeUri(): void
    {
        $result = Result::fail('No encontrado', ServiceErrorCode::NOT_FOUND);
        $body   = ProblemDetails::fromResult($result, 404);

        $this->assertSame(ServiceErrorCode::NOT_FOUND->typeUri(), $body['type']);
    }

    public function testUnknownCodeFallsBackToAboutBlank(): void
    {
        $result = Result::fail('error', 'some_custom_code');
        $body   = ProblemDetails::fromResult($result, 400);

        $this->assertSame('about:blank', $body['type']);
    }

    // ── title ────────────────────────────────────────────────────────────

    public function testKnownCodeUsesEnumTitle(): void
    {
        $result = Result::fail('Acceso denegado', ServiceErrorCode::FORBIDDEN);
        $body   = ProblemDetails::fromResult($result, 403);

        $this->assertSame(ServiceErrorCode::FORBIDDEN->toTitle(), $body['title']);
    }

    public function testUnknownCodeFallsBackToHttpReasonPhrase(): void
    {
        $result = Result::fail('error', 'custom');
        $body   = ProblemDetails::fromResult($result, 422);

        $this->assertSame('Unprocessable Content', $body['title']);
    }

    // ── standard fields ──────────────────────────────────────────────────

    public function testStatusFieldMatchesArgument(): void
    {
        $result = Result::fail('error', ServiceErrorCode::UNAUTHORIZED);
        $body   = ProblemDetails::fromResult($result, 401);

        $this->assertSame(401, $body['status']);
    }

    public function testDetailFieldContainsErrorMessage(): void
    {
        $result = Result::fail('Usuario no encontrado', ServiceErrorCode::NOT_FOUND);
        $body   = ProblemDetails::fromResult($result, 404);

        $this->assertSame('Usuario no encontrado', $body['detail']);
    }

    public function testCodeFieldPresentWhenSet(): void
    {
        $result = Result::fail('error', 'my_code');
        $body   = ProblemDetails::fromResult($result, 400);

        $this->assertSame('my_code', $body['code']);
    }

    // ── RFC 9457 extension members from context ───────────────────────────

    public function testContextFieldsAppearAsExtensionMembers(): void
    {
        $result = Result::fail(
            'No encontrado',
            ServiceErrorCode::NOT_FOUND,
            context: ['resource_type' => 'user', 'resource_id' => 42]
        );
        $body = ProblemDetails::fromResult($result, 404);

        $this->assertSame('user', $body['resource_type']);
        $this->assertSame(42, $body['resource_id']);
    }

    public function testContextErrorsArrayAppearsTopLevel(): void
    {
        $errors = ['email' => 'Formato inválido', 'name' => 'Requerido'];
        $result = Result::fail(
            'Validación fallida',
            ServiceErrorCode::VALIDATION_ERROR,
            context: ['errors' => $errors]
        );
        $body = ProblemDetails::fromResult($result, 422);

        $this->assertSame($errors, $body['errors']);
    }

    public function testEmptyContextAddsNoExtraFields(): void
    {
        $result = Result::fail('error', ServiceErrorCode::CONFLICT);
        $body   = ProblemDetails::fromResult($result, 409);

        $standardKeys = ['type', 'title', 'status', 'detail', 'code'];
        foreach ($standardKeys as $key) {
            $this->assertArrayHasKey($key, $body);
        }
        // No extra keys beyond standard ones
        $this->assertCount(\count($standardKeys), $body);
    }

    // ── guard ────────────────────────────────────────────────────────────

    public function testThrowsOnSuccessResult(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProblemDetails::fromResult(Result::ok(), 200);
    }
}
