<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
namespace Tests\Unit\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigGettersTest extends TestCase
{
    public function testGetIntReturnsDefaultWhenKeyMissing(): void
    {
        $value = Config::getInt('non.existent.int', 123);
        $this->assertSame(123, $value);
    }

    public function testGetBoolReturnsDefaultWhenKeyMissing(): void
    {
        $value = Config::getBool('non.existent.bool', true);
        $this->assertTrue($value);
    }

    public function testGetStringReturnsDefaultWhenKeyMissing(): void
    {
        $value = Config::getString('non.existent.string', 'fallback');
        $this->assertSame('fallback', $value);
    }
}
