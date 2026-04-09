<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use PDOException;
use Throwable;

/**
 * Excepción para errores de base de datos
 *
 * Envuelve errores de PDO y proporciona información adicional
 * sin exponer detalles sensibles en producción.
 */
final class DatabaseException extends Exception
{
    private int $httpCode = 500;

    private ?string $query;

    private array $params;

    /**
     * @param string         $message  Mensaje del error
     * @param string|null    $query    Consulta SQL que causó el error
     * @param array          $params   Parámetros de la consulta
     * @param integer        $code     Código de error interno
     * @param Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $message = 'Error de base de datos',
        ?string $query = null,
        array $params = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->query = $query;
        $this->params = $params;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (500)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene la consulta SQL que causó el error
     *
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Obtiene los parámetros de la consulta
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Convierte la excepción a formato JSON para API
     *
     * No expone query/params en producción por seguridad
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => 'Error interno del servidor',
            'code' => $this->httpCode,
            // Intencionalmente no exponemos query/params
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Crea excepción desde PDOException
     *
     * @param PDOException $e      Excepción de PDO
     * @param string|null  $query  Consulta SQL
     * @param array        $params Parámetros de la consulta
     *
     * @return self
     */
    public static function fromPDOException(
        PDOException $e,
        ?string $query = null,
        array $params = []
    ): self {
        return new self(
            'Error ejecutando consulta: ' . $e->getMessage(),
            $query,
            $params,
            (int) $e->getCode(),
            $e
        );
    }

    /**
     * Factory method: Entrada duplicada (UNIQUE constraint)
     *
     * @param string $field Campo con valor duplicado
     *
     * @return self
     */
    public static function duplicateEntry(string $field): self
    {
        return new self(
            "El valor ya existe: $field",
            null,
            ['field' => $field]
        );
    }

    /**
     * Factory method: Violación de foreign key
     *
     * @param string $table Tabla afectada
     *
     * @return self
     */
    public static function foreignKeyViolation(string $table): self
    {
        return new self(
            "No se puede realizar la operación debido a referencias en: $table",
            null,
            ['table' => $table]
        );
    }

    /**
     * Factory method: Conexión fallida
     *
     * @return self
     */
    public static function connectionFailed(): self
    {
        return new self('No se pudo conectar a la base de datos');
    }

    /**
     * Factory method: Transacción fallida
     *
     * @param string $reason Razón del fallo
     *
     * @return self
     */
    public static function transactionFailed(string $reason = 'Error desconocido'): self
    {
        return new self("Transacción fallida: $reason");
    }
}
