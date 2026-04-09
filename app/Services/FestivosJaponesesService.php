<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Servicio de Festivos Japoneses (祝日 - Shukujitsu)
 *
 * Gestiona los días festivos nacionales de Japón, tanto fijos como móviles.
 * Proporciona información cultural y restricciones para reservas.
 *
 * Referencias:
 * - National Holidays Act (Ley de Feriados Nacionales de Japón)
 * - Feriados compensatorios (振替休日 - Furikae Kyūjitsu)
 *
 * @package Komorebi\Services
 * @author TFC DAW - Komorebi Café
 * @version 1.0.0
 */
final class FestivosJaponesesService
{
    /**
     * Festivos fijos anuales
     *
     * Formato: 'MM-DD' => [nombre_es, nombre_ja, descripcion, icono]
     */
    private const FESTIVOS_FIJOS = [
        '01-01' => [
            'nombre_es' => 'Año Nuevo',
            'nombre_ja' => '元日',
            'romaji' => 'Ganjitsu',
            'descripcion' => 'Primer día del año. Tradiciones: Hatsumode (primera visita al templo)',
            'icono' => '🎉',
            'permite_reservas' => false,
        ],
        '02-11' => [
            'nombre_es' => 'Día de la Fundación Nacional',
            'nombre_ja' => '建国記念の日',
            'romaji' => 'Kenkoku Kinen no Hi',
            'descripcion' => 'Conmemora la fundación de Japón en 660 a.C.',
            'icono' => '🇯🇵',
            'permite_reservas' => true,
        ],
        '02-23' => [
            'nombre_es' => 'Día del Cumpleaños del Emperador',
            'nombre_ja' => '天皇誕生日',
            'romaji' => 'Tennō Tanjōbi',
            'descripcion' => 'Celebración del cumpleaños del Emperador actual',
            'icono' => '🎂',
            'permite_reservas' => true,
        ],
        '04-29' => [
            'nombre_es' => 'Día Showa',
            'nombre_ja' => '昭和の日',
            'romaji' => 'Shōwa no Hi',
            'descripcion' => 'Honra al Emperador Showa (Hirohito)',
            'icono' => '🌸',
            'permite_reservas' => true,
        ],
        '05-03' => [
            'nombre_es' => 'Día de la Constitución',
            'nombre_ja' => '憲法記念日',
            'romaji' => 'Kenpō Kinenbi',
            'descripcion' => 'Conmemora la promulgación de la Constitución de 1947',
            'icono' => '📜',
            'permite_reservas' => true,
        ],
        '05-04' => [
            'nombre_es' => 'Día del Verde',
            'nombre_ja' => 'みどりの日',
            'romaji' => 'Midori no Hi',
            'descripcion' => 'Día para agradecer la naturaleza y bendiciones',
            'icono' => '🌿',
            'permite_reservas' => true,
        ],
        '05-05' => [
            'nombre_es' => 'Día de los Niños',
            'nombre_ja' => 'こどもの日',
            'romaji' => 'Kodomo no Hi',
            'descripcion' => 'Celebración de los niños y deseo de felicidad',
            'icono' => '🎏',
            'permite_reservas' => true,
        ],
        '08-11' => [
            'nombre_es' => 'Día de la Montaña',
            'nombre_ja' => '山の日',
            'romaji' => 'Yama no Hi',
            'descripcion' => 'Oportunidad de familiarizarse con las montañas',
            'icono' => '🏔️',
            'permite_reservas' => true,
        ],
        '11-03' => [
            'nombre_es' => 'Día de la Cultura',
            'nombre_ja' => '文化の日',
            'romaji' => 'Bunka no Hi',
            'descripcion' => 'Promoción de la cultura, artes y libertad académica',
            'icono' => '🎭',
            'permite_reservas' => true,
        ],
        '11-23' => [
            'nombre_es' => 'Día de Acción de Gracias por el Trabajo',
            'nombre_ja' => '勤労感謝の日',
            'romaji' => 'Kinrō Kansha no Hi',
            'descripcion' => 'Respeto por el trabajo y celebración de la producción',
            'icono' => '🛠️',
            'permite_reservas' => true,
        ],
    ];

    /**
     * Obtiene el festivo de una fecha específica
     *
     * @param DateTimeInterface|string|null $fecha Fecha a consultar (default: hoy en Tokyo)
     * @return array|null Datos del festivo o null si no es festivo
     * @throws \DateMalformedStringException
     */
    public function obtenerFestivo(DateTimeInterface|string|null $fecha = null): ?array
    {
        $fechaObj = $this->normalizarFecha($fecha);
        $mesYDia = $fechaObj->format('m-d');

        // Verificar festivos fijos
        if (isset(self::FESTIVOS_FIJOS[$mesYDia])) {
            return [
                'fecha' => $fechaObj->format('Y-m-d'),
                'tipo' => 'fijo',
                ...self::FESTIVOS_FIJOS[$mesYDia],
            ];
        }

        // Verificar festivos móviles
        $festivoMovil = $this->calcularFestivoMovil($fechaObj);
        if ($festivoMovil) {
            return $festivoMovil;
        }

        return null;
    }

    /**
     * Verifica si una fecha es festivo
     *
     * @param DateTimeInterface|string|null $fecha Fecha a verificar
     * @return boolean True si es festivo
     * @throws \DateMalformedStringException
     */
    public function esFestivo(DateTimeInterface|string|null $fecha = null): bool
    {
        return $this->obtenerFestivo($fecha) !== null;
    }

    /**
     * Obtiene todos los festivos de un año específico
     *
     * @param integer|null $anio Año (default: año actual en Tokyo)
     * @return array Lista de festivos del año
     * @throws \DateMalformedStringException
     */
    public function obtenerFestivosDelAnio(?int $anio = null): array
    {
        if ($anio === null) {
            $dateTime = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
            $anio = (int) $dateTime->format('Y');
        }
        $festivos = [];

        // Agregar festivos fijos
        foreach (self::FESTIVOS_FIJOS as $fecha => $datos) {
            $fechaCompleta = "$anio-$fecha";
            $festivos[] = [
                'fecha' => $fechaCompleta,
                'tipo' => 'fijo',
                ...$datos,
            ];
        }

        // Agregar festivos móviles del año
        $festivosMoviles = [
            $this->calcularSegundoLunesEnero($anio),
            $this->calcularTercerLunesJulio($anio),
            $this->calcularTercerLunesSeptiembre($anio),
            $this->calcularSegundoLunesOctubre($anio),
            $this->calcularEquinoccioVernal($anio),
            $this->calcularEquinoccioOtonal($anio),
        ];

        foreach ($festivosMoviles as $festivo) {
            if ($festivo) {
                $festivos[] = $festivo;
            }
        }

        // Ordenar por fecha
        \usort($festivos, static fn ($a, $b) => \strcmp($a['fecha'], $b['fecha']));

        return $festivos;
    }

    /**
     * Obtiene mensaje contextual para un festivo
     *
     * @param DateTimeInterface|string|null $fecha Fecha a consultar
     * @return string Mensaje poético o información
     * @throws \DateMalformedStringException
     */
    public function obtenerMensajeContextual(DateTimeInterface|string|null $fecha = null): string
    {
        $festivo = $this->obtenerFestivo($fecha);

        if (!$festivo) {
            return '';
        }

        $mensajes = [
            '元日' => 'El café abre con energías renovadas para el nuevo año. ¡Feliz año nuevo!',
            '建国記念の日' => 'Honramos las raíces de esta tierra milenaria.',
            '天皇誕生日' => 'Celebramos junto a todo Japón este día especial.',
            '昭和の日' => 'Entre flores de cerezo, recordamos tiempos de paz.',
            '憲法記念日' => 'Un día para reflexionar sobre libertad y democracia.',
            'みどりの日' => 'La naturaleza nos regala su verdor. Tiempo de agradecer.',
            'こどもの日' => '¡Las carpas koi vuelan alto! Día de sonrisas infantiles.',
            '山の日' => 'Las montañas nos llaman. Un respiro entre la ciudad.',
            '文化の日' => 'Arte, música y tradición. La cultura florece hoy.',
            '勤労感謝の日' => 'Gracias a quienes trabajan, el mundo sigue girando.',
            '成人の日' => 'Los jóvenes adultos celebran su mayoría de edad.',
            '海の日' => 'El océano, fuente de vida. Hoy lo honramos.',
            '敬老の日' => 'Respeto y cariño a nuestros mayores, tesoros vivientes.',
            'スポーツの日' => '¡Salud y movimiento! Un día para activarse.',
            '春分の日' => 'Equinoccio de primavera. Día y noche en armonía.',
            '秋分の日' => 'Equinoccio de otoño. Balance entre luz y oscuridad.',
        ];

        return $mensajes[$festivo['nombre_ja']] ?? $festivo['descripcion'];
    }

    /**
     * Verifica si se permite reservar en un festivo
     *
     * @param DateTimeInterface|string|null $fecha Fecha a verificar
     * @return boolean True si se permiten reservas
     * @throws \DateMalformedStringException
     */
    public function permiteReservas(DateTimeInterface|string|null $fecha = null): bool
    {
        $festivo = $this->obtenerFestivo($fecha);

        if (!$festivo) {
            return true; // No es festivo, siempre se puede reservar
        }

        return $festivo['permite_reservas'] ?? true;
    }

    // ==========================================
    // MÉTODOS PRIVADOS - FESTIVOS MÓVILES
    // ==========================================

    /**
     * Calcula festivos móviles para una fecha específica
     */
    private function calcularFestivoMovil(DateTime $fecha): ?array
    {
        $anio = (int) $fecha->format('Y');
        $mes = (int) $fecha->format('m');
        $fechaStr = $fecha->format('Y-m-d');

        $checks = $this->obtenerChecksFestivos($mes, $anio);

        return $this->ejecutarChecksFestivos($checks, $fechaStr);
    }

    /**
     * Ejecuta un array de callables y devuelve la primera coincidencia con la fecha dada.
     */
    private function ejecutarChecksFestivos(array $checks, string $fechaStr): ?array
    {
        foreach ($checks as $check) {
            $festivo = $check();
            if ($festivo && ($festivo['fecha'] ?? '') === $fechaStr) {
                return $festivo;
            }
        }

        return null;
    }

    /**
     * Devuelve un array de callables para comprobar festivos según mes y año.
     */
    private function obtenerChecksFestivos(int $mes, int $anio): array
    {
        $map = [
            1 => [fn (): array => $this->calcularSegundoLunesEnero($anio)],
            7 => [fn (): array => $this->calcularTercerLunesJulio($anio)],
            9 => [
                fn (): array => $this->calcularTercerLunesSeptiembre($anio),
                fn (): array => $this->calcularEquinoccioOtonal($anio),
            ],
            10 => [fn (): array => $this->calcularSegundoLunesOctubre($anio)],
            3 => [fn (): array => $this->calcularEquinoccioVernal($anio)],
        ];

        return $map[$mes] ?? [];
    }

    /**
     * Segundo lunes de enero - Día de la Mayoría de Edad (成人の日)
     * @param integer $anio
     * @return array
     * @throws \DateMalformedStringException
     */
    private function calcularSegundoLunesEnero(int $anio): array
    {
        $fecha = $this->calcularNesimoLunes($anio, 1, 2);

        return [
            'fecha' => $fecha->format('Y-m-d'),
            'tipo' => 'movil',
            'nombre_es' => 'Día de la Mayoría de Edad',
            'nombre_ja' => '成人の日',
            'romaji' => 'Seijin no Hi',
            'descripcion' => 'Celebración de quienes cumplen 20 años (mayoría de edad)',
            'icono' => '🎉',
            'permite_reservas' => true,
        ];
    }

    /**
     * Tercer lunes de julio - Día del Mar (海の日)
     * @param integer $anio
     * @return array
     * @throws \DateMalformedStringException
     */
    private function calcularTercerLunesJulio(int $anio): array
    {
        // Nota: En 2020 (Olimpiadas) fue el 23 de julio, pero retomó 3er lunes en 2021
        $fecha = $this->calcularNesimoLunes($anio, 7, 3);

        return [
            'fecha' => $fecha->format('Y-m-d'),
            'tipo' => 'movil',
            'nombre_es' => 'Día del Mar',
            'nombre_ja' => '海の日',
            'romaji' => 'Umi no Hi',
            'descripcion' => 'Gratitud por las bendiciones del océano',
            'icono' => '🌊',
            'permite_reservas' => true,
        ];
    }

    /**
     * Tercer lunes de septiembre - Día del Respeto a los Ancianos (敬老の日)
     * @param integer $anio
     * @return array
     * @throws \DateMalformedStringException
     */
    private function calcularTercerLunesSeptiembre(int $anio): array
    {
        $fecha = $this->calcularNesimoLunes($anio, 9, 3);

        return [
            'fecha' => $fecha->format('Y-m-d'),
            'tipo' => 'movil',
            'nombre_es' => 'Día del Respeto a los Ancianos',
            'nombre_ja' => '敬老の日',
            'romaji' => 'Keirō no Hi',
            'descripcion' => 'Honrar y agradecer a las personas mayores',
            'icono' => '👴',
            'permite_reservas' => true,
        ];
    }

    /**
     * Segundo lunes de octubre - Día del Deporte (スポーツの日)
     * @param integer $anio
     * @return array
     * @throws \DateMalformedStringException
     */
    private function calcularSegundoLunesOctubre(int $anio): array
    {
        $fecha = $this->calcularNesimoLunes($anio, 10, 2);

        return [
            'fecha' => $fecha->format('Y-m-d'),
            'tipo' => 'movil',
            'nombre_es' => 'Día del Deporte',
            'nombre_ja' => 'スポーツの日',
            'romaji' => 'Supōtsu no Hi',
            'descripcion' => 'Promoción de la salud y el deporte',
            'icono' => '🏅',
            'permite_reservas' => true,
        ];
    }

    /**
     * Equinoccio de primavera (春分の日) - Vernal Equinox
     */
    private function calcularEquinoccioVernal(int $anio): array
    {
        // Fórmula aproximada para Japón (suele ser 20 o 21 de marzo)
        $dia = (int) \floor(20.8431 + 0.242194 * ($anio - 1980) - (int) (($anio - 1980) / 4));
        $fecha = \sprintf('%d-03-%02d', $anio, $dia);

        return [
            'fecha' => $fecha,
            'tipo' => 'astronomico',
            'nombre_es' => 'Equinoccio de Primavera',
            'nombre_ja' => '春分の日',
            'romaji' => 'Shunbun no Hi',
            'descripcion' => 'Día y noche de igual duración. Inicio de la primavera',
            'icono' => '🌸',
            'permite_reservas' => true,
        ];
    }

    /**
     * Equinoccio de otoño (秋分の日) - Autumnal Equinox
     */
    private function calcularEquinoccioOtonal(int $anio): array
    {
        // Fórmula aproximada para Japón (suele ser 22 o 23 de septiembre)
        $dia = (int) \floor(23.2488 + 0.242194 * ($anio - 1980) - (int) (($anio - 1980) / 4));
        $fecha = \sprintf('%d-09-%02d', $anio, $dia);

        return [
            'fecha' => $fecha,
            'tipo' => 'astronomico',
            'nombre_es' => 'Equinoccio de Otoño',
            'nombre_ja' => '秋分の日',
            'romaji' => 'Shūbun no Hi',
            'descripcion' => 'Día y noche de igual duración. Homenaje a los ancestros',
            'icono' => '🍁',
            'permite_reservas' => true,
        ];
    }

    /**
     * Calcula el n-ésimo lunes de un mes
     * @param integer $anio
     * @param integer $mes
     * @param integer $numero
     * @return DateTime
     * @throws \DateMalformedStringException
     */
    private function calcularNesimoLunes(int $anio, int $mes, int $numero): DateTime
    {
        $fecha = new DateTime("$anio-$mes-01", new DateTimeZone('Asia/Tokyo'));

        // Encontrar el primer lunes del mes
        while ((int) $fecha->format('N') !== 1) {
            $fecha->modify('+1 day');
        }

        // Avanzar al n-ésimo lunes
        if ($numero > 1) {
            $fecha->modify('+' . ($numero - 1) . ' weeks');
        }

        return $fecha;
    }

    /**
     * Normaliza una fecha a DateTime en timezone Tokyo
     * @param DateTimeInterface|string|null $fecha
     * @return DateTime
     * @throws \DateMalformedStringException
     */
    private function normalizarFecha(DateTimeInterface|string|null $fecha): DateTime
    {
        if ($fecha === null) {
            return new DateTime('now', new DateTimeZone('Asia/Tokyo'));
        }

        if (\is_string($fecha)) {
            return new DateTime($fecha, new DateTimeZone('Asia/Tokyo'));
        }

        // Si ya es DateTimeInterface, crear copia en timezone Tokyo
        $dt = DateTime::createFromInterface($fecha);
        $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));

        return $dt;
    }
}
