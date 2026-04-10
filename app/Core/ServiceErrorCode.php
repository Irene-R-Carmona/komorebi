<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Códigos de error semánticos del dominio.
 *
 * Cada case lleva el código de error (string), el status HTTP correspondiente
 * y el título RFC 9457 para Problem Details.
 *
 * Uso en servicios:   Result::fail('mensaje', ServiceErrorCode::NOT_FOUND)
 * Uso en renderers:   Result::fail($e->getMessage(), ServiceErrorCode::FORBIDDEN, context: [...])
 * Resolución en ProblemDetails::fromResult() vía ServiceErrorCode::tryFrom($result->code)
 */
enum ServiceErrorCode: string
{
    case NOT_FOUND       = 'not_found';
    case UNAUTHORIZED    = 'unauthorized';
    case FORBIDDEN       = 'forbidden';
    case VALIDATION_ERROR = 'validation_error';
    case MISSING_FIELD   = 'missing_field';
    case BUSINESS_RULE   = 'business_rule';
    case CONFLICT        = 'conflict';
    case SERVER_ERROR    = 'server_error';

    /**
     * URI de tipo RFC 9457 — identifica de forma inequívoca esta clase de error.
     */
    public function typeUri(): string
    {
        return 'https://komorebi.cafe/errors/' . $this->value;
    }

    /**
     * Status HTTP canónico para este código de error.
     */
    public function toHttpStatus(): int
    {
        return match ($this) {
            self::NOT_FOUND       => 404,
            self::UNAUTHORIZED    => 401,
            self::FORBIDDEN       => 403,
            self::VALIDATION_ERROR,
            self::MISSING_FIELD   => 422,
            self::BUSINESS_RULE   => 400,
            self::CONFLICT        => 409,
            self::SERVER_ERROR    => 500,
        };
    }

    /**
     * Título humano-legible RFC 9457 para este código de error.
     */
    public function toTitle(): string
    {
        return match ($this) {
            self::NOT_FOUND       => 'Not Found',
            self::UNAUTHORIZED    => 'Unauthorized',
            self::FORBIDDEN       => 'Forbidden',
            self::VALIDATION_ERROR,
            self::MISSING_FIELD   => 'Unprocessable Content',
            self::BUSINESS_RULE   => 'Bad Request',
            self::CONFLICT        => 'Conflict',
            self::SERVER_ERROR    => 'Internal Server Error',
        };
    }
}
