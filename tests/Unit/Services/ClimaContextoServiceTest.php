<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ClimaContextoService: obtención de contexto climático usando WeatherService stub.
 * ¿Qué me quieres demostrar? Que obtenerClimaActual devuelve un array de contexto.
 * ¿Qué va a fallar en este test si se cambia el código? Si obtenerClimaActual deja de retornar array.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Services\ClimaContextoService;
use App\Services\Contracts\WeatherServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClimaContextoService::class)]
final class ClimaContextoServiceTest extends TestCase
{
    public function testObtenerClimaActualReturnsArrayWhenWeatherFails(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::fail('Network error'));

        $service = new ClimaContextoService($weatherStub);
        $result = $service->obtenerClimaActual();

        $this->assertIsArray($result);
    }

    public function testObtenerConfiguracionEfectosReturnsArray(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::fail('Not configured'));

        $service = new ClimaContextoService($weatherStub);
        $result = $service->obtenerConfiguracionEfectos('clear');

        $this->assertIsArray($result);
    }

    public function testObtenerClimaActualReturnsAllRequiredKeysWhenWeatherSucceeds(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::ok([
            'current' => ['weather_code' => 0, 'temp' => 22.5],
            'cached' => false,
        ]));

        $result = new ClimaContextoService($weatherStub)->obtenerClimaActual();

        foreach (
            [
                'condicion',
                'temperatura',
                'temperatura_celsius',
                'descripcion',
                'mensaje_poetico',
                'hora_tokyo',
                'hora_local_tokyo',
                'codigo_wmo',
                'timestamp',
                'desde_cache',
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    public function testObtenerClimaActualReturnsClearConditionForWmoCode0(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::ok([
            'current' => ['weather_code' => 0, 'temp' => 20.0],
            'cached' => false,
        ]));

        $result = new ClimaContextoService($weatherStub)->obtenerClimaActual();

        $this->assertSame('clear', $result['condicion']);
        $this->assertSame(0, $result['codigo_wmo']);
        $this->assertFalse($result['desde_cache']);
    }

    public function testObtenerClimaActualReturnsRainConditionForWmoCode61(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::ok([
            'current' => ['weather_code' => 61, 'temp' => 14.0],
            'cached' => true,
        ]));

        $result = new ClimaContextoService($weatherStub)->obtenerClimaActual();

        $this->assertSame('rain', $result['condicion']);
        $this->assertSame(61, $result['codigo_wmo']);
        $this->assertTrue($result['desde_cache']);
    }

    public function testObtenerClimaActualReturnsThunderstormForWmoCode95(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::ok([
            'current' => ['weather_code' => 95, 'temp' => 18.0],
            'cached' => false,
        ]));

        $result = new ClimaContextoService($weatherStub)->obtenerClimaActual();

        $this->assertSame('thunderstorm', $result['condicion']);
        $this->assertSame((int) \round(18.0), $result['temperatura_celsius']);
    }

    public function testObtenerClimaActualReturnsDefaultWhenCurrentDataMissing(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::ok([
            'cached' => false,
            // 'current' key intentionally absent
        ]));

        $result = new ClimaContextoService($weatherStub)->obtenerClimaActual();

        $this->assertSame('clouds', $result['condicion']);
    }

    public function testObtenerConfiguracionEfectosReturnsVisualKeysForAllConditions(): void
    {
        $weatherStub = $this->createStub(WeatherServiceInterface::class);
        $weatherStub->method('getWeather')->willReturn(Result::fail('Not configured'));
        $service = new ClimaContextoService($weatherStub);

        foreach (['clear', 'clouds', 'rain', 'snow', 'fog', 'thunderstorm'] as $condicion) {
            $config = $service->obtenerConfiguracionEfectos($condicion);
            $this->assertIsArray($config, "obtenerConfiguracionEfectos('{$condicion}') must return array");
            $this->assertNotEmpty($config, "Config for '{$condicion}' must not be empty");
        }
    }
}
