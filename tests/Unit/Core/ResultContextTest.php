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

    // ── toArray() ───────────────────────────────────────────────────────

    public function testToArrayForOkResultContainsDataKey(): void
    {
        $result = Result::ok(['id' => 5]);

        $arr = $result->toArray();

        $this->assertTrue($arr['ok']);
        $this->assertSame(['id' => 5], $arr['data']);
        $this->assertArrayNotHasKey('error', $arr);
    }

    public function testToArrayForOkResultWithNullData(): void
    {
        $result = Result::ok();

        $arr = $result->toArray();

        $this->assertTrue($arr['ok']);
        $this->assertNull($arr['data']);
    }

    public function testToArrayForFailResultContainsErrorAndCode(): void
    {
        $result = Result::fail('Mensaje de error', 'my_code');

        $arr = $result->toArray();

        $this->assertFalse($arr['ok']);
        $this->assertSame('Mensaje de error', $arr['error']);
        $this->assertSame('my_code', $arr['code']);
        $this->assertArrayNotHasKey('data', $arr);
    }

    public function testToArrayForFailResultWithDataIncludesDataKey(): void
    {
        $result = Result::fail('Error con datos', 'validation', ['field' => 'required']);

        $arr = $result->toArray();

        $this->assertFalse($arr['ok']);
        $this->assertSame(['field' => 'required'], $arr['data']);
    }

    public function testToArrayForFailResultWithNullErrorUsesDefault(): void
    {
        $result = Result::fail('', 'code');

        $arr = $result->toArray();

        $this->assertFalse($arr['ok']);
        // error vacío se almacena tal cual
        $this->assertSame('', $arr['error']);
    }

    // ── toFlash() ───────────────────────────────────────────────────────

    public function testToFlashForOkResultSetsSuccessMessage(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }

        Result::ok()->toFlash('¡Guardado!');

        $this->assertSame('¡Guardado!', $_SESSION['_flash_messages'][0]['message']);
        $this->assertSame('success', $_SESSION['_flash_messages'][0]['type']);

        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    public function testToFlashForOkResultWithStringDataUsesDataAsMessage(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }

        Result::ok('Operación completada')->toFlash();

        $this->assertSame('Operación completada', $_SESSION['_flash_messages'][0]['message']);

        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    public function testToFlashForOkResultWithNoDataUsesDefaultMessage(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }

        Result::ok()->toFlash();

        $this->assertSame('Operación completada exitosamente', $_SESSION['_flash_messages'][0]['message']);

        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    public function testToFlashForFailResultSetsErrorMessage(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }

        Result::fail('Algo salió mal')->toFlash();

        $this->assertSame('Algo salió mal', $_SESSION['_flash_messages'][0]['message']);
        $this->assertSame('error', $_SESSION['_flash_messages'][0]['type']);

        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }
}
