<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores con servicios externos
 *
 * Se lanza cuando falla la comunicación con APIs o servicios de terceros
 * (PHPMailer, OpenAI, WeatherAPI, etc.)
 */
final class ExternalServiceException extends Exception
{
    private int $httpCode = 503;

    private ?string $serviceName;

    private ?int $serviceHttpCode;

    private ?string $serviceResponse;

    /**
     * @param string         $message         Mensaje del error
     * @param string|null    $serviceName     Nombre del servicio externo
     * @param integer|null   $serviceHttpCode Código HTTP del servicio
     * @param string|null    $serviceResponse Respuesta del servicio
     * @param integer        $code            Código de error interno
     * @param Throwable|null $previous        Excepción previa
     */
    public function __construct(
        string $message = 'Error en servicio externo',
        ?string $serviceName = null,
        ?int $serviceHttpCode = null,
        ?string $serviceResponse = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->serviceName = $serviceName;
        $this->serviceHttpCode = $serviceHttpCode;
        $this->serviceResponse = $serviceResponse;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (503)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene el nombre del servicio externo
     *
     * @return string|null
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    /**
     * Obtiene el código HTTP del servicio externo
     *
     * @return integer|null
     */
    public function getServiceHttpCode(): ?int
    {
        return $this->serviceHttpCode;
    }

    /**
     * Obtiene la respuesta del servicio externo
     *
     * @return string|null
     */
    public function getServiceResponse(): ?string
    {
        return $this->serviceResponse;
    }

    /**
     * Convierte la excepción a formato JSON para API
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => 'Servicio temporalmente no disponible',
            'service' => $this->serviceName,
            'code' => $this->httpCode,
            // No exponemos serviceResponse por seguridad
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Error en servicio de email (PHPMailer)
     *
     * @param string|null $details Detalles adicionales del error
     *
     * @return self
     */
    public static function emailService(?string $details = null): self
    {
        $message = 'Error al enviar email';

        if ($details) {
            $message .= ": $details";
        }

        return new self(
            $message,
            'PHPMailer'
        );
    }

    /**
     * Factory method: Error en WeatherAPI
     *
     * @param integer|null $httpCode Código HTTP de la respuesta
     *
     * @return self
     */
    public static function weatherAPI(?int $httpCode = null): self
    {
        return new self(
            'Error al obtener datos del clima',
            'WeatherAPI',
            $httpCode
        );
    }

    /**
     * Factory method: Error en OpenAI
     *
     * @param string|null  $errorMessage Mensaje de error de OpenAI
     * @param integer|null $httpCode     Código HTTP de la respuesta
     *
     * @return self
     */
    public static function openAI(?string $errorMessage = null, ?int $httpCode = null): self
    {
        $message = 'Error al comunicarse con OpenAI';

        if ($errorMessage) {
            $message .= ": $errorMessage";
        }

        return new self(
            $message,
            'OpenAI',
            $httpCode,
            $errorMessage
        );
    }

    /**
     * Factory method: Error en Telegram Bot API
     *
     * @param string|null $errorMessage Mensaje de error
     *
     * @return self
     */
    public static function telegram(?string $errorMessage = null): self
    {
        $message = 'Error al comunicarse con Telegram';

        if ($errorMessage) {
            $message .= ": $errorMessage";
        }

        return new self(
            $message,
            'Telegram'
        );
    }

    /**
     * Factory method: Timeout en servicio externo
     *
     * @param string $serviceName Nombre del servicio
     *
     * @return self
     */
    public static function timeout(string $serviceName): self
    {
        return new self(
            "Timeout al comunicarse con $serviceName",
            $serviceName,
            null,
            'Connection timeout'
        );
    }

    /**
     * Factory method: Servicio externo no disponible
     *
     * @param string $serviceName Nombre del servicio
     *
     * @return self
     */
    public static function unavailable(string $serviceName): self
    {
        return new self(
            "$serviceName no está disponible actualmente",
            $serviceName,
            503
        );
    }

    /**
     * Factory method: API key inválida o faltante
     *
     * @param string $serviceName Nombre del servicio
     *
     * @return self
     */
    public static function invalidApiKey(string $serviceName): self
    {
        return new self(
            "API key inválida o faltante para $serviceName",
            $serviceName,
            401
        );
    }

    /**
     * Factory method: Límite de cuota excedido
     *
     * @param string $serviceName Nombre del servicio
     *
     * @return self
     */
    public static function quotaExceeded(string $serviceName): self
    {
        return new self(
            "Límite de cuota excedido para $serviceName",
            $serviceName,
            429
        );
    }
}
