<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Models\Cafe;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador del Quiz "Tu Café del Alma"
 *
 * Sistema con enfoque filosófico y algoritmo de matching ponderado.
 */
final class QuizController
{
    private ResponseFactory $response;
    private Cafe $cafeModel;

    public function __construct(?ResponseFactory $response = null, ?Cafe $cafeModel = null)
    {
        $this->response = $response ?? new ResponseFactory();
        $this->cafeModel = $cafeModel ?? new Cafe(Container::make(\PDO::class));
    }

    /**
     * Preguntas filosóficas del quiz
     * Cada respuesta tiene pesos hacia diferentes características de café
     */
    private const PREGUNTAS = [
        [
            'id' => 1,
            'pregunta' => '¿Qué buscas al entrar en un café?',
            'opciones' => [
                ['texto' => 'Un lugar íntimo y cálido para leer o trabajar', 'pesos' => ['acogedor' => 3, 'intimo' => 2, 'tranquilo' => 1]],
                ['texto' => 'Un sitio con buena energía para charlar y conocer gente', 'pesos' => ['energico' => 3, 'social' => 2, 'divertido' => 1]],
                ['texto' => 'Un rincón verde, con plantas y luz natural', 'pesos' => ['natural' => 3, 'zen' => 1, 'tranquilo' => 1]],
                ['texto' => 'Un espacio silencioso para concentrarme y observar', 'pesos' => ['zen' => 3, 'tranquilo' => 2, 'intimo' => 1]],
            ],
        ],
        [
            'id' => 2,
            'pregunta' => '¿Cómo prefieres sentirte después de tu visita?',
            'opciones' => [
                ['texto' => 'Relajado y con sensación de descanso', 'pesos' => ['tranquilo' => 3, 'zen' => 2, 'acogedor' => 1]],
                ['texto' => 'Animado, con ganas de seguir el día', 'pesos' => ['energico' => 3, 'divertido' => 2, 'social' => 1]],
                ['texto' => 'Reconectado con la naturaleza o lo artesanal', 'pesos' => ['natural' => 3, 'acogedor' => 1, 'zen' => 1]],
                ['texto' => 'Con la mente clara y en calma', 'pesos' => ['zen' => 3, 'tranquilo' => 2, 'intimo' => 1]],
            ],
        ],
        [
            'id' => 3,
            'pregunta' => '¿Qué ambiente valoras más para una tarde perfecta?',
            'opciones' => [
                ['texto' => 'Muebles cómodos y luz cálida', 'pesos' => ['acogedor' => 3, 'intimo' => 2, 'natural' => 1]],
                ['texto' => 'Música suave y gente conversando', 'pesos' => ['social' => 3, 'divertido' => 2, 'energico' => 1]],
                ['texto' => 'Plantas, madera y aromas naturales', 'pesos' => ['natural' => 3, 'zen' => 1, 'acogedor' => 1]],
                ['texto' => 'Silencio para leer o contemplar', 'pesos' => ['zen' => 3, 'tranquilo' => 2, 'intimo' => 1]],
            ],
        ],
        [
            'id' => 4,
            'pregunta' => '¿Qué actividad encaja mejor con tu visita ideal?',
            'opciones' => [
                ['texto' => 'Sentarme, leer y disfrutar sin prisa', 'pesos' => ['tranquilo' => 3, 'intimo' => 2, 'zen' => 1]],
                ['texto' => 'Reunirme con amigos y compartir risas', 'pesos' => ['social' => 3, 'divertido' => 2, 'energico' => 1]],
                ['texto' => 'Tomar fotos entre plantas y detalles cuidados', 'pesos' => ['natural' => 3, 'acogedor' => 1, 'divertido' => 1]],
                ['texto' => 'Trabajar concentrado con buena cafeína', 'pesos' => ['intimo' => 3, 'tranquilo' => 2, 'acogedor' => 1]],
            ],
        ],
        [
            'id' => 5,
            'pregunta' => 'Si pudieras elegir un elemento del local, ¿cuál sería?',
            'opciones' => [
                ['texto' => 'Sillones y mantas para acurrucarme', 'pesos' => ['acogedor' => 3, 'intimo' => 2, 'tranquilo' => 1]],
                ['texto' => 'Una barra con ambiente vivo y amable', 'pesos' => ['energico' => 3, 'social' => 2, 'divertido' => 1]],
                ['texto' => 'Plantas, luz natural y mesas de madera', 'pesos' => ['natural' => 3, 'zen' => 1, 'acogedor' => 1]],
                ['texto' => 'Mesas individuales y enchufes para concentrarme', 'pesos' => ['intimo' => 3, 'tranquilo' => 2, 'acogedor' => 1]],
            ],
        ],
    ];

    /**
     * Perfiles de cafés con características
     */
    private const PERFILES_CAFES = [
        'neko' => [
            'nombre' => 'Neko no Niwa',
            'animal_guia' => 'Gato Guardián',
            'personalidad_guia' => 'Observador y sereno: te ofrece espacio y compañía cuando lo necesitas',
            'caracteristicas' => ['acogedor' => 3, 'tranquilo' => 2, 'intimo' => 3, 'zen' => 1],
            'descripcion' => 'Un lugar donde la calma es bienvenida. Ideal si buscas una pausa tranquila con detalles acogedores.',
        ],
        'usagi' => [
            'nombre' => 'Usagi Yume',
            'animal_guia' => 'Conejo de Luna',
            'personalidad_guia' => 'Juguetón y amable: celebra los pequeños placeres',
            'caracteristicas' => ['acogedor' => 2, 'divertido' => 3, 'social' => 2, 'energico' => 2],
            'descripcion' => 'Perfecto para quienes disfrutan de la alegría sencilla: colores suaves, risas y momentos ligeros.',
        ],
        'fukurou' => [
            'nombre' => 'Fukurō Sanctuary',
            'animal_guia' => 'Búho Nocturno',
            'personalidad_guia' => 'Profundo y contemplativo: invita a la reflexión en calma',
            'caracteristicas' => ['zen' => 3, 'tranquilo' => 3, 'intimo' => 2, 'natural' => 1],
            'descripcion' => 'Un refugio silencioso para quienes buscan silencio, lectura y un ambiente para pensar.',
        ],
        'kotori' => [
            'nombre' => 'Kotori Garden',
            'animal_guia' => 'Pájaro Cantor',
            'personalidad_guia' => 'Melódico y libre: hecho para celebrar la ligereza',
            'caracteristicas' => ['energico' => 2, 'divertido' => 3, 'natural' => 3, 'social' => 2],
            'descripcion' => 'Espacios con vegetación y armonía sonora; ideal para quienes buscan inspiración y movimiento suave.',
        ],
    ];

    /**
     * GET /quiz
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        View::render('public/quiz/index', [
            'titulo' => 'Tu Café del Alma | Quiz',
            'preguntas' => self::PREGUNTAS,
        ], ['quiz.css']);

        return null;
    }

    /**
     * POST /quiz/resultado
     * Calcula el resultado del quiz con algoritmo ponderado
     * @throws JsonException
     */
    public function resultado(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->response->redirect('/quiz');
        }

        // Obtener respuestas desde JSON body
        $json = (string) $request->getBody();
        $data = \json_decode($json, true);
        $respuestas = $data['respuestas'] ?? [];

        if (empty($respuestas) || \count($respuestas) < \count(self::PREGUNTAS)) {
            throw ValidationException::withMessage('Por favor, responde todas las preguntas', 400);
        }

        // Calcular puntuaciones
        $puntuaciones = [
            'tranquilo' => 0,
            'energico' => 0,
            'zen' => 0,
            'social' => 0,
            'intimo' => 0,
            'acogedor' => 0,
            'divertido' => 0,
            'natural' => 0,
        ];

        foreach ($respuestas as $preguntaId => $opcionIndex) {
            $pregunta = $this->obtenerPreguntaPorId((int) $preguntaId);
            if (!$pregunta || !isset($pregunta['opciones'][$opcionIndex])) {
                continue;
            }

            $opcion = $pregunta['opciones'][$opcionIndex];
            foreach ($opcion['pesos'] as $caracteristica => $peso) {
                $puntuaciones[$caracteristica] += $peso;
            }
        }

        // Encontrar el café que mejor coincide
        $mejorCafe = $this->encontrarMejorCafe($puntuaciones);

        // Obtener datos reales del café
        $cafeData = $this->cafeModel->findBySlug($mejorCafe['slug']);

        View::render('public/quiz/resultado', [
            'titulo' => 'Tu Café del Alma | Resultado',
            'cafe' => $mejorCafe,
            'cafeData' => $cafeData,
            'puntuaciones' => $puntuaciones,
        ], ['quiz.css']);

        return null;
    }

    /**
     * Encuentra el café que mejor coincide con las puntuaciones
     *
     * @return (int[]|string)[]|null
     *
     * @psalm-return array{nombre: string, animal_guia: string, personalidad_guia: string, caracteristicas: array{energico?: 2, divertido?: 3, natural?: 1|3, social?: 2, zen?: 1|3, tranquilo?: 2|3, intimo?: 2|3, acogedor?: 2|3}, descripcion: string, slug: string}|null
     */
    private function encontrarMejorCafe(array $puntuaciones): array|null
    {
        $mejorCoincidencia = null;
        $mejorPuntuacion = -1;

        foreach (self::PERFILES_CAFES as $slug => $perfil) {
            $puntuacion = 0;

            foreach ($perfil['caracteristicas'] as $caracteristica => $peso) {
                $puntuacion += ($puntuaciones[$caracteristica] ?? 0) * $peso;
            }

            if ($puntuacion > $mejorPuntuacion) {
                $mejorPuntuacion = $puntuacion;
                $mejorCoincidencia = \array_merge($perfil, ['slug' => $slug]);
            }
        }

        return $mejorCoincidencia;
    }

    /**
     * Obtiene una pregunta por su ID
     */
    private function obtenerPreguntaPorId(int $id): ?array
    {
        return \array_find(self::PREGUNTAS, static fn($pregunta) => $pregunta['id'] === $id);
    }
}
