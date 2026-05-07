<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Exceptions\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base abstracta para Form Requests con validación y sanitización.
 *
 * Subclases deben implementar rules() y sanitize().
 * validate() recopila TODOS los errores (no fail-fast) y lanza
 * ValidationException si alguno falla.
 */
abstract class FormRequest
{
    private array $sanitizedData = [];

    abstract protected function rules(): array;

    /** @param array<string,mixed> $raw */
    abstract protected function sanitize(array $raw): array;

    /**
     * Crea una instancia a partir de un request PSR-7,
     * extrayendo el cuerpo parseado automáticamente.
     */
    public static function fromRequest(ServerRequestInterface $request): static
    {
        /** @var array<string,mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);

        /** @phpstan-ignore new.static */
        $instance = new static();
        $instance->sanitizedData = $instance->sanitize($body);

        return $instance;
    }

    /**
     * Valida los datos dados contra rules().
     * sanitize() SE APLICA ANTES de evaluar las reglas.
     *
     * @param array<string,mixed> $data
     * @throws ValidationException si algún campo falla
     */
    public function validate(array $data): void
    {
        $sanitized = $this->sanitize($data);
        $this->sanitizedData = $sanitized;

        $errors = [];

        foreach ($this->rules() as $field => $ruleString) {
            $rules = \explode('|', (string) $ruleString);
            $value = $sanitized[$field] ?? null;

            foreach ($rules as $rule) {
                $error = $this->applyRule($rule, $field, $value);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // fail-on-first per field, collect across all fields
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Validation failed', $errors);
        }
    }

    /**
     * Devuelve los datos sanitizados tras una validación exitosa
     * o tras fromRequest().
     *
     * @return array<string,mixed>
     */
    public function validated(): array
    {
        return $this->sanitizedData;
    }

    // ─────────────────────────────────────────────
    // Rule engine
    // ─────────────────────────────────────────────

    private function applyRule(string $rule, string $field, mixed $value): ?string
    {
        if ($rule === 'required') {
            if ($value === null || $value === '') {
                return "El campo $field es obligatorio.";
            }

            return null;
        }

        if ($rule === 'email') {
            if ($value !== null && $value !== '' && \filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return "El campo $field debe ser un correo válido.";
            }

            return null;
        }

        if ($rule === 'integer') {
            if ($value !== null && $value !== '' && !\ctype_digit((string) $value)) {
                return "El campo $field debe ser un número entero.";
            }

            return null;
        }

        if ($rule === 'bool') {
            if ($value !== null && $value !== '') {
                $allowed = ['1', '0', 'true', 'false', true, false, 1, 0];
                if (!\in_array($value, $allowed, true)) {
                    return "El campo $field debe ser un valor booleano.";
                }
            }

            return null;
        }

        if (\str_starts_with($rule, 'min:')) {
            $min = (int) \substr($rule, 4);
            if ($value !== null && $value !== '' && \mb_strlen((string) $value) < $min) {
                return "El campo $field debe tener al menos $min caracteres.";
            }

            return null;
        }

        if (\str_starts_with($rule, 'max:')) {
            $max = (int) \substr($rule, 4);
            if ($value !== null && $value !== '' && \mb_strlen((string) $value) > $max) {
                return "El campo $field no debe superar los $max caracteres.";
            }

            return null;
        }

        if (\str_starts_with($rule, 'in:')) {
            $allowed = \explode(',', \substr($rule, 3));
            if ($value !== null && $value !== '' && !\in_array((string) $value, $allowed, true)) {
                $list = \implode(', ', $allowed);

                return "El campo $field debe ser uno de: $list.";
            }

            return null;
        }

        if (\str_starts_with($rule, 'regex:')) {
            $pattern = '/' . \substr($rule, 6) . '/';
            if ($value !== null && $value !== '' && !\preg_match($pattern, (string) $value)) {
                return "El campo $field no tiene el formato esperado.";
            }

            return null;
        }

        return null; // unknown rules are silently ignored
    }
}
