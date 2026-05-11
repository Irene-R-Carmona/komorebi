<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\FestivosJaponesesServiceInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;

/**
 * Servicio de Festivos Españoles
 *
 * Gestiona los días festivos nacionales, autonómicos (Comunidad de Madrid)
 * y locales (municipio de Madrid) que restringen las reservas en Komorebi Café.
 */
final class FestivosEspañolesService implements FestivosJaponesesServiceInterface
{
    private const TIMEZONE = 'Europe/Madrid';

    /**
     * Festivos fijos nacionales y locales de Madrid.
     * Formato: 'MM-DD' => [nombre_es, descripcion, icono]
     */
    private const FESTIVOS_FIJOS = [
        '01-01' => [
            'nombre_es'       => 'Año Nuevo',
            'descripcion'     => 'Primer día del año.',
            'icono'           => '🎉',
            'permite_reservas' => false,
        ],
        '01-06' => [
            'nombre_es'       => 'Epifanía del Señor',
            'descripcion'     => 'Día de Reyes.',
            'icono'           => '👑',
            'permite_reservas' => false,
        ],
        '05-01' => [
            'nombre_es'       => 'Fiesta del Trabajo',
            'descripcion'     => 'Día Internacional de los Trabajadores.',
            'icono'           => '⚒️',
            'permite_reservas' => false,
        ],
        '05-02' => [
            'nombre_es'       => 'Fiesta de la Comunidad de Madrid',
            'descripcion'     => 'Conmemoración del levantamiento del 2 de mayo de 1808.',
            'icono'           => '🏛️',
            'permite_reservas' => false,
        ],
        '05-15' => [
            'nombre_es'       => 'San Isidro Labrador',
            'descripcion'     => 'Patrón de Madrid. Festividad local del municipio.',
            'icono'           => '🌾',
            'permite_reservas' => false,
        ],
        '08-15' => [
            'nombre_es'       => 'Asunción de la Virgen',
            'descripcion'     => 'Festividad religiosa nacional.',
            'icono'           => '🌸',
            'permite_reservas' => false,
        ],
        '10-12' => [
            'nombre_es'       => 'Fiesta Nacional de España',
            'descripcion'     => 'Día de la Hispanidad.',
            'icono'           => '🇪🇸',
            'permite_reservas' => false,
        ],
        '11-01' => [
            'nombre_es'       => 'Todos los Santos',
            'descripcion'     => 'Festividad religiosa nacional.',
            'icono'           => '🕯️',
            'permite_reservas' => false,
        ],
        '11-09' => [
            'nombre_es'       => 'La Almudena',
            'descripcion'     => 'Patrona de Madrid. Festividad local del municipio.',
            'icono'           => '⛪',
            'permite_reservas' => false,
        ],
        '12-06' => [
            'nombre_es'       => 'Día de la Constitución Española',
            'descripcion'     => 'Conmemoración de la Constitución de 1978.',
            'icono'           => '📜',
            'permite_reservas' => false,
        ],
        '12-08' => [
            'nombre_es'       => 'Inmaculada Concepción',
            'descripcion'     => 'Festividad religiosa nacional.',
            'icono'           => '✨',
            'permite_reservas' => false,
        ],
        '12-25' => [
            'nombre_es'       => 'Navidad',
            'descripcion'     => 'Día de Navidad.',
            'icono'           => '🎄',
            'permite_reservas' => false,
        ],
    ];

    // ─── Interface implementation ─────────────────────────────────────────

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function obtenerFestivo(DateTimeInterface|string|null $fecha = null): ?array
    {
        $dt = $this->normalizar($fecha);
        $md = $dt->format('m-d');

        if (isset(self::FESTIVOS_FIJOS[$md])) {
            return ['fecha' => $dt->format('Y-m-d'), 'tipo' => 'fijo', 'nombre_ja' => '', 'romaji' => '', ...self::FESTIVOS_FIJOS[$md]];
        }

        return $this->calcularMovil($dt);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function esFestivo(DateTimeInterface|string|null $fecha = null): bool
    {
        return $this->obtenerFestivo($fecha) !== null;
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function obtenerFestivosDelAnio(?int $anio = null): array
    {
        if ($anio === null) {
            $anio = (int) (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y');
        }

        $festivos = [];

        foreach (self::FESTIVOS_FIJOS as $md => $datos) {
            $festivos[] = ['fecha' => "$anio-$md", 'tipo' => 'fijo', 'nombre_ja' => '', 'romaji' => '', ...$datos];
        }

        $moviles = [
            $this->viernesSanto($anio),
            $this->lunesPascua($anio),
        ];

        foreach ($moviles as $m) {
            if ($m !== null) {
                $festivos[] = $m;
            }
        }

        \usort($festivos, static fn(array $a, array $b) => \strcmp($a['fecha'], $b['fecha']));

        return $festivos;
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function obtenerMensajeContextual(DateTimeInterface|string|null $fecha = null): string
    {
        $festivo = $this->obtenerFestivo($fecha);

        if ($festivo === null) {
            return '';
        }

        return $festivo['descripcion'];
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function permiteReservas(DateTimeInterface|string|null $fecha = null): bool
    {
        $festivo = $this->obtenerFestivo($fecha);

        if ($festivo === null) {
            return true;
        }

        return $festivo['permite_reservas'] ?? true;
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    /**
     * Calcula festivos móviles para una fecha dada (Viernes Santo y Lunes de Pascua).
     */
    private function calcularMovil(DateTimeImmutable $dt): ?array
    {
        $anio = (int) $dt->format('Y');
        $fechaStr = $dt->format('Y-m-d');

        $moviles = [
            $this->viernesSanto($anio),
            $this->lunesPascua($anio),
        ];

        foreach ($moviles as $m) {
            if ($m !== null && ($m['fecha'] ?? '') === $fechaStr) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Viernes Santo — festivo nacional.
     * Calcula la Pascua con el algoritmo de Meeus/Jones/Butcher.
     */
    private function viernesSanto(int $anio): ?array
    {
        $pascua = $this->calcularPascua($anio);

        if ($pascua === null) {
            return null;
        }

        $fecha = $pascua->modify('-2 days');

        return [
            'fecha'           => $fecha->format('Y-m-d'),
            'tipo'            => 'movil',
            'nombre_es'       => 'Viernes Santo',
            'nombre_ja'       => '',
            'romaji'          => '',
            'descripcion'     => 'Viernes de Semana Santa. Festivo nacional.',
            'icono'           => '✝️',
            'permite_reservas' => false,
        ];
    }

    /**
     * Lunes de Pascua — festivo en la Comunidad de Madrid.
     */
    private function lunesPascua(int $anio): ?array
    {
        $pascua = $this->calcularPascua($anio);

        if ($pascua === null) {
            return null;
        }

        $fecha = $pascua->modify('+1 day');

        return [
            'fecha'           => $fecha->format('Y-m-d'),
            'tipo'            => 'movil',
            'nombre_es'       => 'Lunes de Pascua',
            'nombre_ja'       => '',
            'romaji'          => '',
            'descripcion'     => 'Lunes de Pascua. Festivo autonómico de la Comunidad de Madrid.',
            'icono'           => '🐣',
            'permite_reservas' => false,
        ];
    }

    /**
     * Algoritmo de Meeus/Jones/Butcher para calcular el Domingo de Pascua.
     */
    private function calcularPascua(int $anio): ?DateTimeImmutable
    {
        $a = $anio % 19;
        $b = (int) ($anio / 100);
        $c = $anio % 100;
        $d = (int) ($b / 4);
        $e = $b % 4;
        $f = (int) (($b + 8) / 25);
        $g = (int) (($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = (int) ($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = (int) (($a + 11 * $h + 22 * $l) / 451);
        $mes = (int) (($h + $l - 7 * $m + 114) / 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        $fecha = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', "$anio-$mes-$dia 00:00:00", new DateTimeZone(self::TIMEZONE));

        return $fecha !== false ? $fecha : null;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function normalizar(DateTimeInterface|string|null $fecha): DateTimeImmutable
    {
        $tz = new DateTimeZone(self::TIMEZONE);

        if ($fecha === null) {
            return new DateTimeImmutable('now', $tz);
        }

        if (\is_string($fecha)) {
            return new DateTimeImmutable($fecha, $tz);
        }

        return DateTimeImmutable::createFromInterface($fecha)->setTimezone($tz);
    }
}
