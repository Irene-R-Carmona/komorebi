<?php

declare(strict_types=1);

use App\Core\Raw;

/**
 * Contenido de la página de Contacto
 *
 * Retorna array con título y datos estructurados para la vista
 */

return [
    'titulo' => 'Contacto - Komorebi Café',
    'meta_descripcion' => 'Contacta con Komorebi Café. Envíanos tu consulta sobre reservas, eventos o información general.',

    'hero' => [
        'titulo' => 'Contacto',
        'subtitulo' => '¿Tienes alguna pregunta? Estamos aquí para ayudarte',
        'icono' => Raw::html('<i class="bi bi-envelope" aria-hidden="true"></i>'),
    ],

    'opciones' => [
        [
            'icono' => Raw::html('<i class="bi bi-envelope" aria-hidden="true"></i>'),
            'titulo' => 'Email General',
            'descripcion' => 'Para consultas generales, sugerencias o colaboraciones.',
            'email' => 'info@komorebi.cafe',
        ],
        [
            'icono' => Raw::html('<i class="bi bi-telephone" aria-hidden="true"></i>'),
            'titulo' => 'Teléfono',
            'descripcion' => 'Llamadas directas para consultas urgentes o reservas.',
            'telefono' => '+34 910 123 456',
            'horario' => 'Lun-Vie: 10:00 - 18:00',
        ],
        [
            'icono' => Raw::html('<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>'),
            'titulo' => 'Ubicación',
            'descripcion' => 'Calle Ficticia 123, 28001 Madrid, España',
            'link' => 'https://maps.google.com',
            'link_texto' => 'Ver en mapa',
        ],
        [
            'icono' => Raw::html('<i class="bi bi-calendar-event" aria-hidden="true"></i>'),
            'titulo' => 'Eventos Privados',
            'descripcion' => 'Organiza eventos, cumpleaños o celebraciones en nuestro espacio.',
            'email' => 'eventos@komorebi.cafe',
        ],
        [
            'icono' => Raw::html('<i class="bi bi-shield-lock" aria-hidden="true"></i>'),
            'titulo' => 'Privacidad y Datos',
            'descripcion' => 'Consultas sobre protección de datos personales (RGPD).',
            'email' => 'privacidad@komorebi.cafe',
        ],
        [
            'icono' => Raw::html('<i class="bi bi-briefcase" aria-hidden="true"></i>'),
            'titulo' => 'Trabaja con Nosotros',
            'descripcion' => 'Envía tu CV y únete al equipo Komorebi.',
            'link' => '/contacto',
            'link_texto' => 'Ver posiciones abiertas',
        ],
    ],
];
