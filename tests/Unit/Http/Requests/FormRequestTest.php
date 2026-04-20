<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Core\Http\FormRequest;
use App\Exceptions\ValidationException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ¿Qué pruebas aquí?
 * La clase abstracta FormRequest y su motor de reglas de validación.
 *
 * ¿Qué me quieres demostrar?
 * Que validate() recopila TODOS los errores (no fail-fast), lanza ValidationException,
 * y que cada regla (required, email, min, max, integer, bool, in, regex) funciona correctamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en la lógica de reglas, en el comportamiento no-fail-fast,
 * en sanitize() o en fromRequest() romperá estos tests.
 */
#[CoversClass(FormRequest::class)]
final class FormRequestTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Fixture: concrete subclass for testing
    // ─────────────────────────────────────────────

    private function makeRequest(array $rules, array $sanitized): FormRequest
    {
        return new class ($rules, $sanitized) extends FormRequest {
            public function __construct(
                private readonly array $testRules,
                private readonly array $testSanitized,
            ) {
            }

            #[Override]
            protected function rules(): array
            {
                return $this->testRules;
            }

            #[Override]
            protected function sanitize(array $raw): array
            {
                return $this->testSanitized;
            }
        };
    }

    // ─────────────────────────────────────────────
    // validate() – required
    // ─────────────────────────────────────────────

    public function testRequiredPassesWhenFieldPresent(): void
    {
        $req = $this->makeRequest(['name' => 'required'], ['name' => 'Hola']);
        $req->validate(['name' => 'Hola']); // no exception
        $this->addToAssertionCount(1);
    }

    public function testRequiredFailsWhenFieldMissing(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['name' => 'required'], []);
        $req->validate([]);
    }

    public function testRequiredFailsWhenFieldEmptyString(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['name' => 'required'], ['name' => '']);
        $req->validate(['name' => '']);
    }

    public function testRequiredFailsWhenFieldNull(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['name' => 'required'], ['name' => null]);
        $req->validate(['name' => null]);
    }

    // ─────────────────────────────────────────────
    // validate() – email
    // ─────────────────────────────────────────────

    public function testEmailPassesWithValidAddress(): void
    {
        $req = $this->makeRequest(['email' => 'email'], ['email' => 'user@example.com']);
        $req->validate(['email' => 'user@example.com']);
        $this->addToAssertionCount(1);
    }

    public function testEmailFailsWithInvalidAddress(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['email' => 'email'], ['email' => 'not-an-email']);
        $req->validate(['email' => 'not-an-email']);
    }

    public function testEmailSkipsWhenFieldAbsent(): void
    {
        $req = $this->makeRequest(['email' => 'email'], []);
        $req->validate([]);
        $this->addToAssertionCount(1);
    }

    // ─────────────────────────────────────────────
    // validate() – min / max (string length)
    // ─────────────────────────────────────────────

    public function testMinPassesWhenLengthSufficient(): void
    {
        $req = $this->makeRequest(['bio' => 'min:5'], ['bio' => 'twelve chars']);
        $req->validate(['bio' => 'twelve chars']);
        $this->addToAssertionCount(1);
    }

    public function testMinFailsWhenTooShort(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['bio' => 'min:10'], ['bio' => 'hi']);
        $req->validate(['bio' => 'hi']);
    }

    public function testMaxPassesWhenLengthAcceptable(): void
    {
        $req = $this->makeRequest(['title' => 'max:10'], ['title' => 'short']);
        $req->validate(['title' => 'short']);
        $this->addToAssertionCount(1);
    }

    public function testMaxFailsWhenTooLong(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['title' => 'max:5'], ['title' => 'way too long text']);
        $req->validate(['title' => 'way too long text']);
    }

    // ─────────────────────────────────────────────
    // validate() – integer (range min/max on numbers)
    // ─────────────────────────────────────────────

    public function testIntegerPassesWithNumericString(): void
    {
        $req = $this->makeRequest(['count' => 'integer'], ['count' => '5']);
        $req->validate(['count' => '5']);
        $this->addToAssertionCount(1);
    }

    public function testIntegerFailsWithNonNumeric(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['count' => 'integer'], ['count' => 'abc']);
        $req->validate(['count' => 'abc']);
    }

    // ─────────────────────────────────────────────
    // validate() – bool
    // ─────────────────────────────────────────────

    public function testBoolPassesWithValidValues(): void
    {
        foreach (['1', '0', 'true', 'false', true, false] as $val) {
            $req = $this->makeRequest(['active' => 'bool'], ['active' => $val]);
            $req->validate(['active' => $val]);
        }
        $this->addToAssertionCount(1);
    }

    public function testBoolFailsWithInvalidValue(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['active' => 'bool'], ['active' => 'yes']);
        $req->validate(['active' => 'yes']);
    }

    // ─────────────────────────────────────────────
    // validate() – in:a,b,c
    // ─────────────────────────────────────────────

    public function testInPassesWhenValueInList(): void
    {
        $req = $this->makeRequest(['role' => 'in:admin,user'], ['role' => 'admin']);
        $req->validate(['role' => 'admin']);
        $this->addToAssertionCount(1);
    }

    public function testInFailsWhenValueNotInList(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(['role' => 'in:admin,user'], ['role' => 'supervillain']);
        $req->validate(['role' => 'supervillain']);
    }

    // ─────────────────────────────────────────────
    // validate() – regex
    // ─────────────────────────────────────────────

    public function testRegexPassesWhenPatternMatches(): void
    {
        $req = $this->makeRequest(
            ['date' => 'regex:^\d{4}-\d{2}-\d{2}$'],
            ['date' => '2026-03-28']
        );
        $req->validate(['date' => '2026-03-28']);
        $this->addToAssertionCount(1);
    }

    public function testRegexFailsWhenPatternNotMatched(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(
            ['date' => 'regex:^\d{4}-\d{2}-\d{2}$'],
            ['date' => '28-03-2026']
        );
        $req->validate(['date' => '28-03-2026']);
    }

    // ─────────────────────────────────────────────
    // validate() – chained rules (pipe-separated)
    // ─────────────────────────────────────────────

    public function testChainedRulesAllMustPass(): void
    {
        $req = $this->makeRequest(
            ['email' => 'required|email'],
            ['email' => 'user@example.com']
        );
        $req->validate(['email' => 'user@example.com']);
        $this->addToAssertionCount(1);
    }

    public function testChainedRulesFailsIfAnyFails(): void
    {
        $this->expectException(ValidationException::class);
        $req = $this->makeRequest(
            ['email' => 'required|email'],
            ['email' => 'not-valid']
        );
        $req->validate(['email' => 'not-valid']);
    }

    // ─────────────────────────────────────────────
    // Non-fail-fast: collects ALL errors
    // ─────────────────────────────────────────────

    public function testCollectsAllErrorsNotJustFirst(): void
    {
        $req = $this->makeRequest(
            ['name' => 'required', 'email' => 'required|email'],
            ['name' => '', 'email' => 'bad']
        );

        try {
            $req->validate(['name' => '', 'email' => 'bad']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('email', $errors);
        }
    }

    // ─────────────────────────────────────────────
    // fromRequest() – extracts parsed body
    // ─────────────────────────────────────────────

    public function testFromRequestExtractsParsedBody(): void
    {
        $psrRequest = $this->createMock(ServerRequestInterface::class);
        $psrRequest->method('getParsedBody')->willReturn(['name' => 'Komorebi', 'ignored' => 'x']);

        // Use an inline subclass that only keeps 'name'
        $class = new class () extends FormRequest {
            #[Override]
            protected function rules(): array
            {
                return ['name' => 'required'];
            }

            #[Override]
            protected function sanitize(array $raw): array
            {
                return ['name' => \trim((string) ($raw['name'] ?? ''))];
            }
        };

        /** @var FormRequest $instance */
        $instance = $class::fromRequest($psrRequest);
        $this->assertInstanceOf(FormRequest::class, $instance);
    }

    // ─────────────────────────────────────────────
    // validated() – returns sanitized data after passing validation
    // ─────────────────────────────────────────────

    public function testValidatedReturnsDataWhenValid(): void
    {
        $psrRequest = $this->createMock(ServerRequestInterface::class);
        $psrRequest->method('getParsedBody')->willReturn(['name' => '  Komorebi  ']);

        $class = new class () extends FormRequest {
            #[Override]
            protected function rules(): array
            {
                return ['name' => 'required'];
            }

            #[Override]
            protected function sanitize(array $raw): array
            {
                return ['name' => \trim((string) ($raw['name'] ?? ''))];
            }
        };

        $instance = $class::fromRequest($psrRequest);
        $data = $instance->validated();

        $this->assertSame('Komorebi', $data['name']);
    }
}
