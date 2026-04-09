<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
namespace Tests\Unit\Core;

use App\Core\Csrf;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Csrf
 *
 * Verifica:
 * - Generación de tokens
 * - Validación de tokens
 * - Prevención de ataques CSRF
 * - Regeneración de tokens
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Limpiar sesión antes de cada test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testGenerateCreatesValidToken(): void
    {
        $token = Csrf::token();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertGreaterThan(32, strlen($token), 'Token debe tener longitud suficiente');
    }

    public function testGenerateStoresTokenInSession(): void
    {
        Csrf::init();
        $token = Csrf::token();

        // Verificar que el token generado es válido
        $this->assertNotEmpty($token);

        // Verificar que está almacenado correctamente
        $_POST['csrf_token'] = $token;
        $this->assertTrue(Csrf::validate());
    }

    public function testTokenMethodReturnsExistingToken(): void
    {
        $firstToken = Csrf::token();
        $secondToken = Csrf::token();

        $this->assertEquals($firstToken, $secondToken, 'Debe reusar el token existente');
    }

    public function testValidateReturnsTrueForValidTokenInPost(): void
    {
        Csrf::init();
        $token = Csrf::token();
        $_POST['csrf_token'] = $token;

        $isValid = Csrf::validate();

        $this->assertTrue($isValid);
    }

    public function testValidateReturnsFalseForInvalidToken(): void
    {
        Csrf::init();
        $_POST['csrf_token'] = 'invalid-token-12345';

        $isValid = Csrf::validate();

        $this->assertFalse($isValid);
    }

    public function testValidateReturnsFalseWhenNoTokenInSession(): void
    {
        unset($_SESSION['csrf_token']);
        $_POST['csrf_token'] = 'some-token';

        $isValid = Csrf::validate();

        $this->assertFalse($isValid);
    }

    public function testValidateReturnsFalseForEmptyToken(): void
    {
        Csrf::init();
        $_POST['csrf_token'] = '';

        $isValid = Csrf::validate();

        $this->assertFalse($isValid);
    }

    public function testRegenerateCreatesNewToken(): void
    {
        Csrf::init();
        $firstToken = Csrf::token();

        Csrf::regenerate();
        $secondToken = Csrf::token();

        $this->assertNotEquals($firstToken, $secondToken, 'Debe generar un nuevo token');
    }

    public function testRegenerateInvalidatesOldToken(): void
    {
        Csrf::init();
        $oldToken = Csrf::token();

        Csrf::regenerate();
        $_POST['csrf_token'] = $oldToken;

        $isValid = Csrf::validate();

        $this->assertFalse($isValid, 'El token antiguo debe ser inválido después de regenerar');
    }

    public function testVerifyWithValidTokenDoesNotThrow(): void
    {
        Csrf::init();
        $token = Csrf::token();
        $_POST['csrf_token'] = $token;

        $this->expectNotToPerformAssertions();

        Csrf::verify();
    }

    public function testTokenIsConsistentAcrossMultipleCalls(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = Csrf::token();
        }

        $uniqueTokens = array_unique($tokens);
        $this->assertCount(1, $uniqueTokens, 'Todas las llamadas deben retornar el mismo token');
    }

    public function testTokenUsesSecureRandomBytes(): void
    {
        Csrf::init();
        $token1 = Csrf::token();

        // Limpiar sesión y generar nuevo token
        $_SESSION = [];
        Csrf::init();
        $token2 = Csrf::token();

        $this->assertNotEquals($token1, $token2, 'Tokens consecutivos deben ser diferentes');
    }

    public function testValidateWithPsr7RequestFromHeader(): void
    {
        Csrf::init();
        $token = Csrf::token();

        $request = new ServerRequest('POST', '/test', ['X-CSRF-Token' => $token]);

        $isValid = Csrf::validate($request);

        $this->assertTrue($isValid);
    }

    public function testValidateWithPsr7RequestFromBody(): void
    {
        Csrf::init();
        $token = Csrf::token();

        $request = (new ServerRequest('POST', '/test'))
            ->withParsedBody(['csrf_token' => $token]);

        $isValid = Csrf::validate($request);

        $this->assertTrue($isValid);
    }

    public function testValidateWithPsr7RequestPrioritizesBody(): void
    {
        Csrf::init();
        $token = Csrf::token();

        $request = (new ServerRequest('POST', '/test', ['X-CSRF-Token' => 'wrong-token']))
            ->withParsedBody(['csrf_token' => $token]);

        $isValid = Csrf::validate($request);

        $this->assertTrue($isValid, 'Body debe tener prioridad sobre header');
    }

    public function testFieldGeneratesValidHtml(): void
    {
        Csrf::init();
        $token = Csrf::token();

        $html = Csrf::field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString($token, $html);
    }

    public function testMetaGeneratesValidHtml(): void
    {
        Csrf::init();
        $token = Csrf::token();

        $html = Csrf::meta();

        $this->assertStringContainsString('<meta', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString($token, $html);
    }
}
