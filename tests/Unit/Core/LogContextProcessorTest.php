<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\LogContext;
use App\Core\LogContextProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * ¿Qué pruebas aquí?
 * Que LogContextProcessor inyecta LogContext::all() en el campo extra del record.
 *
 * ¿Qué me quieres demostrar?
 * Que el processor enriquece el log record sin modificar otros campos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si el processor deja de usar LogContext::all() o escribe en el lugar equivocado.
 */
#[CoversClass(LogContextProcessor::class)]
final class LogContextProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        LogContext::reset();
    }

    protected function tearDown(): void
    {
        LogContext::reset();
    }

    private function makeRecord(array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test message',
            context: [],
            extra: $extra,
        );
    }

    public function testInjectsLogContextIntoExtra(): void
    {
        LogContext::set('request_id', 'abc123');
        LogContext::set('method', 'GET');

        $processor = new LogContextProcessor();
        $result = $processor($this->makeRecord());

        $this->assertSame('abc123', $result->extra['request_id']);
        $this->assertSame('GET', $result->extra['method']);
    }

    public function testPreservesExistingExtraKeys(): void
    {
        LogContext::set('request_id', 'from_context');

        $processor = new LogContextProcessor();
        $result = $processor($this->makeRecord(['existing' => 'value']));

        $this->assertSame('value', $result->extra['existing']);
        $this->assertSame('from_context', $result->extra['request_id']);
    }

    public function testEmptyContextProducesNoExtraChanges(): void
    {
        $processor = new LogContextProcessor();
        $result = $processor($this->makeRecord());

        $this->assertSame([], $result->extra);
    }

    public function testReturnsSameRecordWhenContextEmpty(): void
    {
        $processor = new LogContextProcessor();
        $record = $this->makeRecord();
        $result = $processor($record);

        $this->assertSame($record, $result);
    }
}
