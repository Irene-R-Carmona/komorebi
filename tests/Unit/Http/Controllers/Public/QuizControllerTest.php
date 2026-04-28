<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato de Public/QuizController: instanciación y que resultado() lanza
 * ValidationException cuando el body no contiene respuestas suficientes.
 *
 * ¿Qué me quieres demostrar?
 * Que la validación de respuestas en resultado() funciona: un POST vacío
 * lanza ValidationException antes de procesar ningún resultado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de respuestas en resultado() o se cambia la excepción lanzada.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Public\QuizController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuizController::class)]
final class QuizControllerTest extends TestCase
{
    private function makeController(): QuizController
    {
        return new QuizController(
            response: new ResponseFactory(),
            cafeRepo: $this->createStub(CafeRepositoryInterface::class),
        );
    }

    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(QuizController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(QuizController::class, 'index'));
        $this->assertTrue(\method_exists(QuizController::class, 'resultado'));
    }

    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(QuizController::class, $this->makeController());
    }

    public function test_resultado_throws_validation_exception_when_no_answers_provided(): void
    {
        $request = new ServerRequest(
            'POST',
            '/quiz/resultado',
            ['Content-Type' => 'application/json'],
            '{}'
        );

        $this->expectException(ValidationException::class);

        $this->makeController()->resultado($request);
    }
}
