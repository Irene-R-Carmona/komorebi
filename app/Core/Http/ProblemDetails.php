<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Result;
use App\Core\ServiceErrorCode;

/**
 * Genera estructuras RFC 9457 Problem Details for HTTP APIs.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9457
 */
final readonly class ProblemDetails
{
    /** @var array<int, string> Reason phrases estándar HTTP */
    private const array HTTP_REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a Teapot",
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * Genera un array RFC 9457 a partir de un Result fallido.
     *
     * Si el code del Result coincide con un case de ServiceErrorCode,
     * se usa su typeUri() y toTitle() en lugar de los valores genéricos.
     * El campo context del Result se añade como RFC 9457 extension members.
     *
     * @return array<string, mixed>
     */
    public static function fromResult(Result $result, int $status): array
    {
        if ($result->ok) {
            throw new \InvalidArgumentException('ProblemDetails::fromResult() requires a failed Result');
        }

        $enumCase = $result->code !== null ? ServiceErrorCode::tryFrom($result->code) : null;

        $body = [
            'type'   => $enumCase !== null ? $enumCase->typeUri() : 'about:blank',
            'title'  => $enumCase !== null ? $enumCase->toTitle() : (self::HTTP_REASON_PHRASES[$status] ?? 'Unknown'),
            'status' => $status,
            'detail' => $result->error ?? '',
        ];

        if ($result->code !== null) {
            $body['code'] = $result->code;
        }

        // RFC 9457 extension members: context fields are merged at top level
        if ($result->context !== []) {
            $body = \array_merge($body, $result->context);
        }

        return $body;
    }
}
