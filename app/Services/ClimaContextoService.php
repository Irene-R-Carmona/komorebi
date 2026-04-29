<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Services\Contracts\ClimaContextoServiceInterface;
use App\Services\Contracts\WeatherServiceInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Override;

/**
 * Servicio de Clima Contextual con Tokyo
 *
 * Enriquece los datos meteorológicos de WeatherService con mensajes poéticos
 * y configuraciones de efectos visuales adaptativas para el café.
 *
 * Delega la obtención de datos y el caching a WeatherService.
 */
final class ClimaContextoService implements ClimaContextoServiceInterface
{
    private const TOKYO_LAT = 35.6762;
    private const TOKYO_LON = 139.6503;

    /**
     * Mapeo de códigos WMO a condiciones internas
     */
    private const WEATHER_CODES = [
        0 => 'clear',           // Cielo despejado
        1 => 'clear',           // Principalmente despejado
        2 => 'clouds',          // Parcialmente nublado
        3 => 'clouds',          // Nublado
        45 => 'fog',            // Niebla
        48 => 'fog',            // Niebla con escarcha
        51 => 'rain',           // Llovizna ligera
        53 => 'rain',           // Llovizna moderada
        55 => 'rain',           // Llovizna densa
        61 => 'rain',           // Lluvia ligera
        63 => 'rain',           // Lluvia moderada
        65 => 'rain',           // Lluvia fuerte
        71 => 'snow',           // Nieve ligera
        73 => 'snow',           // Nieve moderada
        75 => 'snow',           // Nieve fuerte
        80 => 'rain',           // Chubascos ligeros
        81 => 'rain',           // Chubascos moderados
        82 => 'rain',           // Chubascos violentos
        95 => 'thunderstorm',   // Tormenta
        96 => 'thunderstorm',   // Tormenta con granizo
        99 => 'thunderstorm',   // Tormenta con granizo fuerte
    ];

    /**
     * Mensajes poéticos por condición climática
     */
    private const MENSAJES_POETICOS = [
        'clear' => [
            '☀️ El sol ilumina Tokyo con calidez',
            '☀️ Cielo despejado sobre Shibuya',
            '☀️ Un día perfecto para contemplar desde las ventanas',
        ],
        'clouds' => [
            '☁️ Nubes suaves danzan sobre Tokyo',
            '☁️ El cielo viste su manto gris',
            '☁️ Entre nubes, la luz encuentra su camino',
        ],
        'rain' => [
            '️ Lluvia suave acaricia las calles de Tokyo',
            '️ Perfecto para escuchar la lluvia junto a una taza de café',
            '️ La lluvia canta su melodía en los tejados',
        ],
        'snow' => [
            '❄️ Copos de nieve adornan Tokyo',
            '❄️ El silencio blanco cubre la ciudad',
            '❄️ Nieve como pétalos de sakura en invierno',
        ],
        'fog' => [
            '️ Niebla misteriosa envuelve Tokyo',
            '️ La ciudad emerge entre brumas',
            '️ Como un sueño flotando entre nubes',
        ],
        'thunderstorm' => [
            '⚡️ Tormenta dramática sobre Tokyo',
            '⚡️ El cielo habla con truenos',
            '⚡️ Refugio perfecto en nuestro café',
        ],
    ];

    public function __construct(private readonly WeatherServiceInterface $weatherService)
    {
    }

    /**
     * Obtiene el clima actual de Tokyo con contexto poético.
     *
     * Delega la llamada HTTP y el caching a WeatherService.
     * Añade condición normalizada, mensajes poéticos y efectos visuales.
     *
     * @return array{condicion: string, temperatura: float, temperatura_celsius: int, descripcion: string, mensaje_poetico: string, hora_tokyo: string, hora_local_tokyo: string, codigo_wmo: int, timestamp: int, desde_cache: bool}
     * @throws DateMalformedStringException
     */
    #[Override]
    public function obtenerClimaActual(): array
    {
        $horaTokyoObj = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));

        $result = $this->weatherService->getWeather(self::TOKYO_LAT, self::TOKYO_LON, 'Asia/Tokyo');

        if ($result->error !== null) {
            Logger::warning('[ClimaContexto] WeatherService no disponible, usando datos por defecto', [
                'error' => $result->error,
            ]);

            return \array_merge($this->obtenerClimaPorDefecto($horaTokyoObj), ['desde_cache' => false]);
        }

        $data = $result->data;
        $current = $data['current'] ?? null;

        if ($current === null) {
            Logger::warning('[ClimaContexto] Respuesta de WeatherService sin datos actuales, usando por defecto');

            return \array_merge($this->obtenerClimaPorDefecto($horaTokyoObj), ['desde_cache' => false]);
        }

        $codigoWMO = (int) ($current['weather_code'] ?? 0);
        $condicion = self::WEATHER_CODES[$codigoWMO] ?? 'clear';
        $temperatura = (float) ($current['temp'] ?? 15.0);

        return [
            'condicion' => $condicion,
            'temperatura' => $temperatura,
            'temperatura_celsius' => (int) \round($temperatura),
            'descripcion' => $this->obtenerDescripcion($condicion),
            'mensaje_poetico' => $this->obtenerMensajePoetico($condicion),
            'hora_tokyo' => $horaTokyoObj->format('H:i'),
            'hora_local_tokyo' => $horaTokyoObj->format('c'),
            'codigo_wmo' => $codigoWMO,
            'timestamp' => \time(),
            'desde_cache' => (bool) ($data['cached'] ?? false),
        ];
    }

    /**
     * Clima por defecto cuando WeatherService no está disponible.
     *
     * @throws DateMalformedStringException
     */
    private function obtenerClimaPorDefecto(DateTimeImmutable $horaTokyoObj): array
    {
        return [
            'condicion' => 'clouds',
            'temperatura' => 15.0,
            'temperatura_celsius' => 15,
            'descripcion' => 'Parcialmente nublado',
            'mensaje_poetico' => '☁️ Nubes suaves danzan sobre Tokyo',
            'hora_tokyo' => $horaTokyoObj->format('H:i'),
            'hora_local_tokyo' => $horaTokyoObj->format('c'),
            'codigo_wmo' => 2,
            'timestamp' => \time(),
        ];
    }

    /**
     * Obtiene descripción en español de la condición
     */
    private function obtenerDescripcion(string $condicion): string
    {
        $descripciones = [
            'clear' => 'Despejado',
            'clouds' => 'Nublado',
            'rain' => 'Lluvia',
            'snow' => 'Nieve',
            'fog' => 'Niebla',
            'thunderstorm' => 'Tormenta',
        ];

        return $descripciones[$condicion] ?? 'Variable';
    }

    /**
     * Obtiene mensaje poético aleatorio según condición
     */
    private function obtenerMensajePoetico(string $condicion): string
    {
        $mensajes = self::MENSAJES_POETICOS[$condicion] ?? self::MENSAJES_POETICOS['clear'];

        return $mensajes[\array_rand($mensajes)];
    }

    /**
     * Obtiene configuración de efectos visuales según clima
     *
     * @return array{
     *   animacion: string,
     *   intensidad: string,
     *   color_primario: string,
     *   color_secundario: string
     * }
     */
    #[Override]
    public function obtenerConfiguracionEfectos(string $condicion): array
    {
        $configuraciones = [
            'clear' => [
                'animacion' => 'rayos-sol',
                'intensidad' => 'suave',
                'color_primario' => '#FFD700',
                'color_secundario' => '#FFA500',
            ],
            'clouds' => [
                'animacion' => 'nubes-movimiento',
                'intensidad' => 'media',
                'color_primario' => '#B0C4DE',
                'color_secundario' => '#778899',
            ],
            'rain' => [
                'animacion' => 'lluvia',
                'intensidad' => 'media',
                'color_primario' => '#4682B4',
                'color_secundario' => '#5F9EA0',
            ],
            'snow' => [
                'animacion' => 'nieve',
                'intensidad' => 'suave',
                'color_primario' => '#F0F8FF',
                'color_secundario' => '#E0FFFF',
            ],
            'fog' => [
                'animacion' => 'niebla',
                'intensidad' => 'intensa',
                'color_primario' => '#D3D3D3',
                'color_secundario' => '#C0C0C0',
            ],
            'thunderstorm' => [
                'animacion' => 'tormenta',
                'intensidad' => 'intensa',
                'color_primario' => '#2F4F4F',
                'color_secundario' => '#708090',
            ],
        ];

        return $configuraciones[$condicion] ?? $configuraciones['clear'];
    }
}
