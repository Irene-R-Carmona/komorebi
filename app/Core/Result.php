<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Clase Result para respuestas estandarizadas
 *
 * Representa el resultado de una operación que puede tener éxito o fallar.
 * Inmutable (readonly) para garantizar consistencia.
 */
final readonly class Result
{
    public bool $ok;

    public mixed $data;

    public ?string $error;

    public ?string $code;

    private function __construct(bool $ok, mixed $data = null, ?string $error = null, ?string $code = null)
    {
        $this->ok = $ok;
        $this->data = $data;
        $this->error = $error;
        $this->code = $code;
    }

    /**
     * Crea un resultado exitoso
     *
     * @param mixed $data
     *
     * @return self
     */
    public static function ok(mixed $data = null): self
    {
        return new self(true, $data);
    }

    /**
     * Crea un resultado fallido
     *
     * @param string $error Mensaje de error
     * @param string $code  Código de error
     * @param mixed  $data  Datos opcionales
     *
     * @return self
     */
    public static function fail(string $error, string $code = 'error', mixed $data = null): self
    {
        return new self(false, $data, $error, $code);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el mensaje de error o un mensaje por defecto
     *
     * @param string $default
     *
     * @return string
     */
    public function getMessage(string $default = 'Error'): string
    {
        return $this->error ?? $default;
    }

    /**
     * Verifica si el resultado es exitoso
     *
     * @return boolean
     */
    public function isOk(): bool
    {
        return $this->ok;
    }

    /**
     * Verifica si el resultado es un fallo
     *
     * @return boolean
     */
    public function isFail(): bool
    {
        return !$this->ok;
    }

    /**
     * Obtiene data o un valor por defecto
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getDataOr(mixed $default = null): mixed
    {
        return $this->data ?? $default;
    }

    /**
     * Propaga el resultado a Flash messages.
     */
    public function toFlash(?string $successMessage = null, string $successType = 'success', string $errorType = 'error'): void
    {
        if ($this->ok) {
            $message = $successMessage ?? (\is_string($this->data) ? $this->data : 'Operación completada exitosamente');
            Flash::set($successType, $message);
        } else {
            Flash::set($errorType, $this->getMessage());
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
