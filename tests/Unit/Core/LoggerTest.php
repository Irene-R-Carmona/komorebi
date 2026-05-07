<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::reset();
    }

    protected function tearDown(): void
    {
        Logger::reset();
    }

    // ─── channel() / get() ────────────────────────────────────────────────────

    public function testGetReturnsLoggerInterface(): void
    {
        $logger = Logger::get();

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testChannelReturnsLoggerInterface(): void
    {
        $logger = Logger::channel('app');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testChannelReturnsSameInstanceForSameName(): void
    {
        $a = Logger::channel('http');
        $b = Logger::channel('http');

        self::assertSame($a, $b);
    }

    public function testChannelReturnsDifferentInstancesForDifferentNames(): void
    {
        $app = Logger::channel('app');
        $db = Logger::channel('db');

        self::assertNotSame($app, $db);
    }

    public function testGetReturnsSameInstanceAsChannelApp(): void
    {
        $get = Logger::get();
        $channel = Logger::channel('app');

        self::assertSame($get, $channel);
    }

    // ─── reset() ──────────────────────────────────────────────────────────────

    public function testResetClearsChannelCache(): void
    {
        $before = Logger::channel('queue');
        Logger::reset();
        $after = Logger::channel('queue');

        // Tras reset, se crea una nueva instancia
        self::assertNotSame($before, $after);
    }

    // ─── proxy methods ────────────────────────────────────────────────────────

    public function testInfoDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::info('test info message');
    }

    public function testErrorDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::error('test error message', ['context_key' => 'value']);
    }

    public function testWarningDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::warning('test warning', []);
    }

    public function testDebugDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::debug('test debug');
    }

    public function testNoticeDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::notice('test notice');
    }

    public function testCriticalDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::critical('test critical', []);
    }

    public function testAlertDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::alert('test alert', []);
    }

    public function testEmergencyDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        Logger::emergency('test emergency', []);
    }

    public function testChannelDbReturnsLoggerInterface(): void
    {
        $logger = Logger::channel('db');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testChannelAuthReturnsLoggerInterface(): void
    {
        $logger = Logger::channel('auth');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(LoggerInterface::class, $logger);
    }
}
