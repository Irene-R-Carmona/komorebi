<?php

declare(strict_types=1);

namespace App\Services;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Servicio de Microestaciones Japonesas (24 Sekki)
 *
 * Implementa el calendario solar tradicional japonés dividido en 24 términos solares.
 * Cada término representa aproximadamente 15 días y marca cambios sutiles en la naturaleza.
 *
 * Concepto: Ichi-go ichi-e (一期一会) - cada momento es único e irrepetible.
 */
final class MicroestacionesService
{
    /**
     * 24 Términos Solares (Nijūshi Sekki - 二十四節気)
     *
     * Fechas aproximadas basadas en el calendario gregoriano.
     * Pueden variar ±1 día según el año astronómico.
     */
    private const array SEKKI = [
        [
            'id' => 1,
            'nombre_es' => 'Comienzo de la Primavera',
            'nombre_ja' => '立春',
            'romaji' => 'Risshun',
            'fecha_inicio' => '02-04',
            'descripcion' => 'El inicio de la primavera según el calendario lunar. Los brotes comienzan a aparecer.',
            'icono' => '🌱',
            'color' => '#A8D5BA',
        ],
        [
            'id' => 2,
            'nombre_es' => 'Agua de Lluvia',
            'nombre_ja' => '雨水',
            'romaji' => 'Usui',
            'fecha_inicio' => '02-19',
            'descripcion' => 'La nieve se convierte en lluvia. Los pájaros cantan y los peces nadan bajo el hielo derretido.',
            'icono' => '💧',
            'color' => '#87CEEB',
        ],
        [
            'id' => 3,
            'nombre_es' => 'Despertar de los Insectos',
            'nombre_ja' => '啓蟄',
            'romaji' => 'Keichitsu',
            'fecha_inicio' => '03-06',
            'descripcion' => 'Los insectos emergen de sus refugios invernales al sentir el trueno de primavera.',
            'icono' => '🐞',
            'color' => '#98D8C8',
        ],
        [
            'id' => 4,
            'nombre_es' => 'Equinoccio de Primavera',
            'nombre_ja' => '春分',
            'romaji' => 'Shunbun',
            'fecha_inicio' => '03-21',
            'descripcion' => 'Día y noche tienen la misma duración. Los cerezos comienzan a florecer.',
            'icono' => '🌸',
            'color' => '#FFB7C5',
        ],
        [
            'id' => 5,
            'nombre_es' => 'Claridad Pura',
            'nombre_ja' => '清明',
            'romaji' => 'Seimei',
            'fecha_inicio' => '04-05',
            'descripcion' => 'Todo es fresco y claro. El cielo azul brilla sobre los campos verdes.',
            'icono' => '☀️',
            'color' => '#FFD700',
        ],
        [
            'id' => 6,
            'nombre_es' => 'Lluvia de Grano',
            'nombre_ja' => '穀雨',
            'romaji' => 'Kokū',
            'fecha_inicio' => '04-20',
            'descripcion' => 'La lluvia nutre los cultivos. Es tiempo de sembrar el arroz.',
            'icono' => '🌾',
            'color' => '#DAA520',
        ],
        [
            'id' => 7,
            'nombre_es' => 'Comienzo del Verano',
            'nombre_ja' => '立夏',
            'romaji' => 'Rikka',
            'fecha_inicio' => '05-06',
            'descripcion' => 'El verano comienza. Las ranas empiezan a cantar y los gusanos emergen.',
            'icono' => '🐸',
            'color' => '#7FBF7F',
        ],
        [
            'id' => 8,
            'nombre_es' => 'Grano Pequeño',
            'nombre_ja' => '小満',
            'romaji' => 'Shōman',
            'fecha_inicio' => '05-21',
            'descripcion' => 'Los granos comienzan a llenarse. Todo está creciendo vigorosamente.',
            'icono' => '🌱',
            'color' => '#90EE90',
        ],
        [
            'id' => 9,
            'nombre_es' => 'Grano en Espiga',
            'nombre_ja' => '芒種',
            'romaji' => 'Bōshu',
            'fecha_inicio' => '06-06',
            'descripcion' => 'Tiempo de plantar arroz y cosechar trigo. La mantis religiosa nace.',
            'icono' => '🌾',
            'color' => '#F0E68C',
        ],
        [
            'id' => 10,
            'nombre_es' => 'Solsticio de Verano',
            'nombre_ja' => '夏至',
            'romaji' => 'Geshi',
            'fecha_inicio' => '06-21',
            'descripcion' => 'El día más largo del año. Los lirios comienzan a florecer.',
            'icono' => '☀️',
            'color' => '#FFA500',
        ],
        [
            'id' => 11,
            'nombre_es' => 'Calor Menor',
            'nombre_ja' => '小暑',
            'romaji' => 'Shōsho',
            'fecha_inicio' => '07-07',
            'descripcion' => 'El calor comienza a intensificarse. Los vientos cálidos soplan.',
            'icono' => '🌞',
            'color' => '#FF6347',
        ],
        [
            'id' => 12,
            'nombre_es' => 'Calor Mayor',
            'nombre_ja' => '大暑',
            'romaji' => 'Taisho',
            'fecha_inicio' => '07-23',
            'descripcion' => 'El momento más caluroso del año. Los nenúfares florecen.',
            'icono' => '🔥',
            'color' => '#DC143C',
        ],
        [
            'id' => 13,
            'nombre_es' => 'Comienzo del Otoño',
            'nombre_ja' => '立秋',
            'romaji' => 'Risshū',
            'fecha_inicio' => '08-08',
            'descripcion' => 'El otoño comienza aunque el calor permanece. Los vientos frescos empiezan.',
            'icono' => '🍂',
            'color' => '#D2691E',
        ],
        [
            'id' => 14,
            'nombre_es' => 'Fin del Calor',
            'nombre_ja' => '処暑',
            'romaji' => 'Shosho',
            'fecha_inicio' => '08-23',
            'descripcion' => 'El calor del verano termina. El algodón florece.',
            'icono' => '🌾',
            'color' => '#DAA520',
        ],
        [
            'id' => 15,
            'nombre_es' => 'Rocío Blanco',
            'nombre_ja' => '白露',
            'romaji' => 'Hakuro',
            'fecha_inicio' => '09-08',
            'descripcion' => 'El rocío matutino brilla como perlas blancas. El aire se vuelve fresco.',
            'icono' => '💧',
            'color' => '#E0E0E0',
        ],
        [
            'id' => 16,
            'nombre_es' => 'Equinoccio de Otoño',
            'nombre_ja' => '秋分',
            'romaji' => 'Shūbun',
            'fecha_inicio' => '09-23',
            'descripcion' => 'Día y noche son iguales. Los truenos cesan y los insectos se esconden.',
            'icono' => '🍁',
            'color' => '#CD853F',
        ],
        [
            'id' => 17,
            'nombre_es' => 'Rocío Frío',
            'nombre_ja' => '寒露',
            'romaji' => 'Kanro',
            'fecha_inicio' => '10-08',
            'descripcion' => 'El rocío se enfría. Los gansos salvajes llegan y los crisantemos florecen.',
            'icono' => '🍃',
            'color' => '#B0C4DE',
        ],
        [
            'id' => 18,
            'nombre_es' => 'Descenso de la Escarcha',
            'nombre_ja' => '霜降',
            'romaji' => 'Sōkō',
            'fecha_inicio' => '10-23',
            'descripcion' => 'La primera escarcha cae. Las hojas cambian de color intensamente.',
            'icono' => '🌬️',
            'color' => '#8B4513',
        ],
        [
            'id' => 19,
            'nombre_es' => 'Comienzo del Invierno',
            'nombre_ja' => '立冬',
            'romaji' => 'Rittō',
            'fecha_inicio' => '11-07',
            'descripcion' => 'El invierno comienza. La tierra se congela y las hojas caen.',
            'icono' => '❄️',
            'color' => '#4682B4',
        ],
        [
            'id' => 20,
            'nombre_es' => 'Nieve Menor',
            'nombre_ja' => '小雪',
            'romaji' => 'Shōsetsu',
            'fecha_inicio' => '11-22',
            'descripcion' => 'Comienza a nevar ligeramente. El clima se vuelve frío.',
            'icono' => '❄️',
            'color' => '#F0F8FF',
        ],
        [
            'id' => 21,
            'nombre_es' => 'Nieve Mayor',
            'nombre_ja' => '大雪',
            'romaji' => 'Taisetsu',
            'fecha_inicio' => '12-07',
            'descripcion' => 'La nieve cae abundantemente. Los animales hibernan.',
            'icono' => '❄️',
            'color' => '#FFFAFA',
        ],
        [
            'id' => 22,
            'nombre_es' => 'Solsticio de Invierno',
            'nombre_ja' => '冬至',
            'romaji' => 'Tōji',
            'fecha_inicio' => '12-22',
            'descripcion' => 'La noche más larga del año. El renacimiento de la luz solar.',
            'icono' => '🌙',
            'color' => '#191970',
        ],
        [
            'id' => 23,
            'nombre_es' => 'Frío Menor',
            'nombre_ja' => '小寒',
            'romaji' => 'Shōkan',
            'fecha_inicio' => '01-06',
            'descripcion' => 'El frío se intensifica. Los pozos se congelan.',
            'icono' => '🧊',
            'color' => '#B0E0E6',
        ],
        [
            'id' => 24,
            'nombre_es' => 'Frío Mayor',
            'nombre_ja' => '大寒',
            'romaji' => 'Daikan',
            'fecha_inicio' => '01-20',
            'descripcion' => 'El momento más frío del año. Pero la primavera ya se acerca.',
            'icono' => '❄️',
            'color' => '#4169E1',
        ],
    ];

    /**
     * Obtiene el término solar actual
     *
     * @param DateTimeInterface|null $fecha Fecha a evaluar (null = hoy)
     *
     * @throws DateMalformedStringException
     *
     * @return array Datos del término solar actual
     */
    public function obtenerActual(?DateTimeInterface $fecha = null): array
    {
        if ($fecha === null) {
            $fecha = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
        }

        $fechaActualStr = $fecha->format('m-d');

        $sekkiActual = null;

        // Buscar el término actual comparando fechas
        foreach (self::SEKKI as $index => $sekki) {
            $fechaInicioSekki = $sekki['fecha_inicio'];

            // Obtener el siguiente sekki (con wrap circular)
            $siguienteIndex = ($index + 1) % \count(self::SEKKI);
            $siguienteSekki = self::SEKKI[$siguienteIndex];
            $fechaFinSekki = $siguienteSekki['fecha_inicio'];

            // Comparar fechas teniendo en cuenta el cambio de año
            if ($this->estaEnRango($fechaActualStr, $fechaInicioSekki, $fechaFinSekki)) {
                $sekkiActual = $sekki;
                break;
            }
        }

        // Fallback: si no se encontró, usar el primero
        if ($sekkiActual === null) {
            $sekkiActual = self::SEKKI[0];
        }

        return $sekkiActual;
    }

    /**
     * Obtiene todos los términos solares
     *
     * @return array Lista completa de los 24 sekki
     */
    public function obtenerTodos(): array
    {
        return self::SEKKI;
    }

    /**
     * Obtiene un término específico por ID
     *
     * @param integer $id ID del término (1-24)
     *
     * @return array|null Datos del término o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        return \array_find(self::SEKKI, fn ($sekki) => $sekki['id'] === $id);
    }

    /**
     * Obtiene el término por fecha específica
     *
     * @param string $fecha Fecha en formato 'MM-DD'
     *
     * @throws DateMalformedStringException
     *
     * @return array|null Datos del término o null
     */
    public function obtenerPorFecha(string $fecha): ?array
    {
        $fechaObj = DateTimeImmutable::createFromFormat('m-d', $fecha);

        if (!$fechaObj) {
            return null;
        }

        return $this->obtenerActual($fechaObj);
    }

    /**
     * Verifica si una fecha está en el rango de un sekki
     *
     * @param string $fecha  Fecha a verificar (MM-DD)
     * @param string $inicio Fecha inicio del rango (MM-DD)
     * @param string $fin    Fecha fin del rango (MM-DD)
     *
     * @return boolean
     */
    private function estaEnRango(string $fecha, string $inicio, string $fin): bool
    {
        // Convertir fechas a timestamp del año actual para comparación
        $anio = \date('Y');

        $fechaTs = \strtotime("$anio-$fecha");
        $inicioTs = \strtotime("$anio-$inicio");
        $finTs = \strtotime("$anio-$fin");

        // Caso especial: el rango cruza el año nuevo
        if ($inicioTs > $finTs) {
            // El rango es de diciembre a enero
            return $fechaTs >= $inicioTs || $fechaTs < $finTs;
        }

        return $fechaTs >= $inicioTs && $fechaTs < $finTs;
    }

    /**
     * Obtiene mensaje contextual según el término actual
     *
     * @throws DateMalformedStringException
     *
     * @return string Mensaje poético sobre el momento actual
     */
    public function obtenerMensajeContextual(): string
    {
        $sekkiActual = $this->obtenerActual();

        $mensajes = [
            'Risshun' => 'La vida renace en cada rincón. Descubre la renovación con una taza de café.',
            'Usui' => 'La lluvia nutre la tierra. Deja que nuestro café nutra tu espíritu.',
            'Keichitsu' => 'Despierta como los insectos al sol primaveral. Encuentra tu energía aquí.',
            'Shunbun' => 'En el equilibrio perfecto del equinoccio, encuentra tu centro con nosotros.',
            'Seimei' => 'Todo brilla con claridad. Disfruta de este momento luminoso.',
            'Kokuu' => 'Como la lluvia que nutre los cultivos, nuestro café nutre tu día.',
            'Rikka' => 'El verano comienza. Celebra la abundancia de la vida.',
            'Shōman' => 'Todo crece con plenitud. Crece tú también en nuestro espacio.',
            'Bōshu' => 'Tiempo de cosecha. Recoge momentos preciosos en nuestro café.',
            'Geshi' => 'En el día más largo, aprovecha cada instante de luz.',
            'Shōsho' => 'El calor aumenta. Encuentra tu oasis de frescura con nosotros.',
            'Taisho' => 'En el pico del verano, refresca tu espíritu con nuestra compañía.',
            'Risshū' => 'El otoño llega con su promesa de cambio. Transforma tu día aquí.',
            'Shosho' => 'El calor se retira. Abraza la suave transición de las estaciones.',
            'Hakuro' => 'Como el rocío brillante, encuentra pequeñas joyas de felicidad.',
            'Shūbun' => 'En el equilibrio otoñal, encuentra tu armonía interior.',
            'Kanro' => 'El rocío frío trae claridad. Contempla con nosotros.',
            'Sōkō' => 'Las hojas cambian. Celebra la belleza del cambio.',
            'Rittō' => 'El invierno comienza. Abrígate con nuestra calidez.',
            'Shōsetsu' => 'La primera nieve cae suavemente. Encuentra tu paz invernal.',
            'Taisetsu' => 'Bajo el manto blanco, descubre la quietud profunda.',
            'Tōji' => 'En la noche más larga, somos tu luz cálida.',
            'Shōkan' => 'El frío se intensifica. Nuestro café te espera con calor.',
            'Daikan' => 'En el frío más intenso, la primavera ya se acerca. Ten esperanza.',
        ];

        return $mensajes[$sekkiActual['romaji']] ?? 'Bienvenido a nuestro refugio de serenidad.';
    }
}
