<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Clase Result para respuestas estandarizadas.
 *
 * Representa el resultado de una operación que puede tener éxito o fallar.
 * Inmutable (readonly) para garantizar consistencia.
 *
 * @template-covariant T Tipo del dato en caso de éxito (PHPDoc genérico, sin impacto en runtime)
 */
final class Result
{
    /** Derivado de $error: true cuando no hay error, false cuando sí. */
    public bool $ok {
        get => $this->error === null;
    }

    /** @var T|null */
    public readonly mixed $data;

    public readonly ?string $error;

    public readonly ?string $code;

    /** @var array<string, mixed> Contexto adicional para Problem Details (RFC 9457 extension members) */
    public readonly array $context;

    /**
     * @param T|null $data
     * @param array<string, mixed> $context
     */
    private function __construct(
        mixed   $data = null,
        ?string $error = null,
        ?string $code = null,
        array   $context = [],
    ) {
        $this->data = $data;
        $this->error = $error;
        $this->code = $code;
        $this->context = $context;
    }

    /**
     * Crea un resultado exitoso.
     *
     * @template TData
     * @param TData $data
     * @return self<TData>
     */
    public static function ok(mixed $data = null): self
    {
        return new self($data);
    }

    /**
     * Crea un resultado fallido.
     *
     * @param string|ServiceErrorCode $code Código de error o case del enum ServiceErrorCode
     * @param mixed $data Datos opcionales (compatibilidad hacia atrás)
     * @param array<string, mixed> $context Contexto extra para RFC 9457 extension members
     *
     * @return self<null>
     */
    public static function fail(
        string                  $error,
        string|ServiceErrorCode $code = 'error',
        mixed                   $data = null,
        array                   $context = [],
    ): self {
        $codeStr = $code instanceof ServiceErrorCode ? $code->value : $code;

        return new self($data, $error, $codeStr, $context);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Propaga el resultado a Flash messages.
     */
    public function toFlash(?string $successMessage = null, string $successType = 'success', string $errorType = 'error'): void
    {
        if ($this->ok) {
            $message = $successMessage ?? (\is_string($this->data) ? $this->data : 'Operación completada exitosamente');
            match ($successType) {
                'success' => Flash::success($message),
                'info' => Flash::info($message),
                'warning' => Flash::warning($message),
                default => Flash::success($message),
            };
        } else {
            $errMsg = $this->error ?? 'Error';
            match ($errorType) {
                'error' => Flash::error($errMsg),
                'warning' => Flash::warning($errMsg),
                'info' => Flash::info($errMsg),
                default => Flash::error($errMsg),
            };
        }
    }

    /**
     * Convierte a array para respuestas JSON
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $out = ['ok' => $this->ok];

        if ($this->ok) {
            $out['data'] = $this->data;

            return $out;
        }

        $out['error'] = $this->error ?? 'Error';
        $out['code'] = $this->code ?? 'error';

        // data opcional para casos como {fields:{...}} o meta
        if ($this->data !== null) {
            $out['data'] = $this->data;
        }

        return $out;
    }
}
