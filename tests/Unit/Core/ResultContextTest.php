<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Las mejoras de Result: campo context y aceptar ServiceErrorCode en fail().
 * ¿Qué me quieres demostrar? Que fail() almacena context, que ServiceErrorCode se acepta como
 * código y que la API existente (string code, data) sigue siendo compatible.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina context, si fail() deja
 * de aceptar ServiceErrorCode, o si se rompe la compatibilidad hacia atrás con $data.
 */

use App\Core\Result;
use App\Core\ServiceErrorCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Result::class)]
#[CoversClass(ServiceErrorCode::class)]
final class ResultContextTest extends TestCase
{
    // ── context field ────────────────────────────────────────────────────

    public function testFailWithContextStoresContext(): void
    {
        $result = Result::fail('error', 'code', context: ['user_id' => 42]);

        $this->assertSame(['user_id' => 42], $result->context);
    }

    public function testFailWithoutContextDefaultsToEmptyArray(): void
    {
        $result = Result::fail('error', 'code');

        $this->assertSame([], $result->context);
    }

    public function testOkResultHasEmptyContext(): void
    {
        $result = Result::ok(['id' => 1]);

        $this->assertSame([], $result->context);
    }

    public function testContextCanContainNestedArrays(): void
    {
        $result = Result::fail('error', 'code', context: ['errors' => ['field' => 'required']]);

        $this->assertSame(['errors' => ['field' => 'required']], $result->context);
    }

    // ── ServiceErrorCode as $code ────────────────────────────────────────

    public function testFailAcceptsServiceErrorCodeEnum(): void
    {
        $result = Result::fail('No encontrado', ServiceErrorCode::NOT_FOUND);

        $this->assertSame('not_found', $result->code);
    }

    public function testFailStoresEnumValueAsString(): void
    {
        $result = Result::fail('Prohibido', ServiceErrorCode::FORBIDDEN);

        $this->assertFalse($result->ok);
        $this->assertSame('forbidden', $result->code);
        $this->assertSame('Prohibido', $result->error);
    }

    public function testFailAcceptsEnumWithContextNamed(): void
    {
        $result = Result::fail(
            'No encontrado',
            ServiceErrorCode::NOT_FOUND,
            context: ['resource_type' => 'user', 'id' => 99]
        );

        $this->assertSame('not_found', $result->code);
        $this->assertSame('user', $result->context['resource_type']);
        $this->assertSame(99, $result->context['id']);
    }

    // ── Backward compatibility ───────────────────────────────────────────

    public function testFailStringCodeStillWorks(): void
    {
        $result = Result::fail('error', 'my_custom_code');

        $this->assertSame('my_custom_code', $result->code);
    }

    public function testFailDefaultCodeStillWorks(): void
    {
        $result = Result::fail('error');

        $this->assertSame('error', $result->code);
    }

    public function testFailWithDataPositionalStillWorks(): void
    {
        $result = Result::fail('error', 'code', ['field' => 'value']);

        $this->assertSame(['field' => 'value'], $result->data);
    }

    public function testOkReturnType(): void
    {
        $result = Result::ok(['id' => 1, 'name' => 'Test']);

        $this->assertTrue($result->ok);
        $this->assertSame(['id' => 1, 'name' => 'Test'], $result->data);
    }
}
