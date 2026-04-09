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
use ReflectionClass;

final class ConfigGettersValuesTest extends TestCase
{
    private function setConfigCache(array $cache): void
    {
        $rc = new ReflectionClass(Config::class);
        $cacheProp = $rc->getProperty('cache');
        // setAccessible está deprecado; para propiedades estáticas usar setValue con la
        // clase declarante (null para estáticas) y evitar llamar setAccessible.
        $cacheProp->setValue(null, $cache);

        $initProp = $rc->getProperty('initialized');
        $initProp->setValue(null, true);
    }

    public function testGetIntFromNumericStringInCache(): void
    {
        $this->setConfigCache(['session' => ['lifetime' => '30']]);
        $this->assertSame(30, Config::getInt('session.lifetime', 120));
    }

    public function testGetIntFromIntegerInCache(): void
    {
        $this->setConfigCache(['session' => ['lifetime' => 45]]);
        $this->assertSame(45, Config::getInt('session.lifetime', 120));
    }

    public function testGetIntFallbackOnNonNumeric(): void
    {
        $this->setConfigCache(['session' => ['lifetime' => 'not-a-number']]);
        $this->assertSame(120, Config::getInt('session.lifetime', 120));
    }

    public function testGetBoolFromStringTrue(): void
    {
        $this->setConfigCache(['feature' => ['enabled' => 'true']]);
        $this->assertTrue(Config::getBool('feature.enabled', false));
    }

    public function testGetBoolFromStringFalse(): void
    {
        $this->setConfigCache(['feature' => ['enabled' => 'false']]);
        $this->assertFalse(Config::getBool('feature.enabled', true));
    }

    public function testGetBoolFromBoolean(): void
    {
        $this->setConfigCache(['feature' => ['enabled' => true]]);
        $this->assertTrue(Config::getBool('feature.enabled', false));
    }

    public function testGetStringFromScalar(): void
    {
        $this->setConfigCache(['app' => ['name' => 12345]]);
        $this->assertSame('12345', Config::getString('app.name', 'fallback'));
    }

    public function testGetStringFallbackOnNonScalar(): void
    {
        $this->setConfigCache(['app' => ['name' => ['array']]]);
        $this->assertSame('fallback', Config::getString('app.name', 'fallback'));
    }
}
