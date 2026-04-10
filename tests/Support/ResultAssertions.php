<?php

/**
 * ¿Qué pruebas aquí?
 * Trait reutilizable con helpers para hacer assertions sobre objetos Result.
 *
 * ¿Qué me quieres demostrar?
 * Que los tests de servicios pueden verificar el estado de un Result con mensajes
 * de fallo claros que incluyen el código, el mensaje de error y el contexto.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina Result::$ok, Result::$code, Result::$error o Result::$context.
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Result;
use App\Core\ServiceErrorCode;
use PHPUnit\Framework\Assert;

trait ResultAssertions
{
    protected function assertResultOk(Result $result): void
    {
        Assert::assertTrue(
            $result->ok,
            sprintf(
                'Expected Result::ok but got failure. code=%s, error=%s, context=%s',
                $result->code ?? '(none)',
                $result->error ?? '(none)',
                json_encode($result->context, JSON_UNESCAPED_UNICODE)
            )
        );
    }

    protected function assertResultFail(Result $result, string|ServiceErrorCode|null $expectedCode = null): void
    {
        Assert::assertFalse(
            $result->ok,
            sprintf(
                'Expected Result failure but got ok=true. data=%s',
                json_encode($result->data, JSON_UNESCAPED_UNICODE)
            )
        );

        if ($expectedCode !== null) {
            $expectedCodeStr = $expectedCode instanceof ServiceErrorCode
                ? $expectedCode->value
                : $expectedCode;

            Assert::assertSame(
                $expectedCodeStr,
                $result->code,
                sprintf(
                    'Expected result code "%s" but got "%s". error=%s, context=%s',
                    $expectedCodeStr,
                    $result->code ?? '(none)',
                    $result->error ?? '(none)',
                    json_encode($result->context, JSON_UNESCAPED_UNICODE)
                )
            );
        }
    }

    protected function assertResultFailWithCode(Result $result, ServiceErrorCode $code): void
    {
        $this->assertResultFail($result, $code);
    }
}
