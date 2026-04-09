<?php

declare(strict_types=1);

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
        'icono' => '📧',
    ],

    'opciones' => [
        [
            'icono' => '📧',
            'titulo' => 'Email General',
            'descripcion' => 'Para consultas generales, sugerencias o colaboraciones.',
            'email' => 'info@komorebi.cafe',
        ],
        [
            'icono' => '📞',
            'titulo' => 'Teléfono',
            'descripcion' => 'Llamadas directas para consultas urgentes o reservas.',
            'telefono' => '+34 910 123 456',
            'horario' => 'Lun-Vie: 10:00 - 18:00',
        ],
        [
            'icono' => '📍',
            'titulo' => 'Ubicación',
            'descripcion' => 'Calle Ficticia 123, 28001 Madrid, España',
            'link' => 'https://maps.google.com',
            'link_texto' => 'Ver en mapa',
        ],
        [
            'icono' => '🎉',
            'titulo' => 'Eventos Privados',
            'descripcion' => 'Organiza eventos, cumpleaños o celebraciones en nuestro espacio.',
            'email' => 'eventos@komorebi.cafe',
        ],
        [
            'icono' => '🔒',
            'titulo' => 'Privacidad y Datos',
            'descripcion' => 'Consultas sobre protección de datos personales (RGPD).',
            'email' => 'privacidad@komorebi.cafe',
        ],
        [
            'icono' => '💼',
            'titulo' => 'Trabaja con Nosotros',
            'descripcion' => 'Envía tu CV y únete al equipo Komorebi.',
            'link' => '/contacto',
            'link_texto' => 'Ver posiciones abiertas',
        ],
    ],
];
