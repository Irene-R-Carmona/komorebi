<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ExceptionHandler;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DatabaseException;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ExceptionHandler::class)]
final class ExceptionHandlerTest extends TestCase
{
    // ─── getDebugInfo() ───────────────────────────────────────────────────────

    public function testGetDebugInfoReturnsMessageKey(): void
    {
        $ex = new RuntimeException('Something went wrong');

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertSame('Something went wrong', $info['message']);
    }

    public function testGetDebugInfoReturnsTypeKey(): void
    {
        $ex = new RuntimeException('test');

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertSame(RuntimeException::class, $info['type']);
    }

    public function testGetDebugInfoReturnsCodeKey(): void
    {
        $ex = new RuntimeException('test', 42);

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertSame(42, $info['code']);
    }

    public function testGetDebugInfoReturnsFileKey(): void
    {
        $ex = new RuntimeException('test');

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertArrayHasKey('file', $info);
        self::assertIsString($info['file']);
    }

    public function testGetDebugInfoReturnsLineKey(): void
    {
        $ex = new RuntimeException('test');

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertArrayHasKey('line', $info);
        self::assertIsInt($info['line']);
    }

    public function testGetDebugInfoReturnsTraceKey(): void
    {
        $ex = new RuntimeException('test');

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertArrayHasKey('trace', $info);
        self::assertIsString($info['trace']);
    }

    public function testGetDebugInfoReturnsExactlyFiveKeys(): void
    {
        $ex = new RuntimeException('test');

        $info = ExceptionHandler::getDebugInfo($ex);

        // Exactamente: message, type, code, file, line, trace
        self::assertCount(6, $info);
    }

    public function testGetDebugInfoWorksWithCustomException(): void
    {
        $ex = new Exception('custom message', 99);

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertSame('custom message', $info['message']);
        self::assertSame(99, $info['code']);
        self::assertSame(Exception::class, $info['type']);
    }

    public function testGetDebugInfoWithZeroCode(): void
    {
        $ex = new RuntimeException('no code');

        $info = ExceptionHandler::getDebugInfo($ex);

        self::assertSame(0, $info['code']);
    }

    // ─── register() ───────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    public function testRegisterDoesNotThrow(): void
    {
        // register() setea handlers globales — en proceso separado para no contaminar suite
        ExceptionHandler::register();
        self::assertTrue(true);
    }
}
