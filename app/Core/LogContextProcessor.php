<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor que inyecta LogContext::all() en el campo extra de cada log record.
 *
 * Registrado en Logger::channel() para que todos los logs lleven automáticamente
 * el request_id, method y path del request en curso.
 */
final class LogContextProcessor implements ProcessorInterface
{
    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = LogContext::all();

        if ($extra === []) {
            return $record;
        }

        return $record->with(extra: array_merge($record->extra, $extra));
    }
}
