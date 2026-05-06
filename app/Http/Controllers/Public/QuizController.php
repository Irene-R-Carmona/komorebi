<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\CafeRepositoryInterface;
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
    private CafeRepositoryInterface $cafeRepo;

    public function __construct(?ResponseFactory $response = null, ?CafeRepositoryInterface $cafeRepo = null)
    {
        $this->response = $response ?? new ResponseFactory();
        $this->cafeRepo = $cafeRepo ?? Container::make(CafeRepositoryInterface::class);
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
        [
            'id' => 6,
            'pregunta' => '¿Qué tipo de interacción con los animales imaginas?',
            'opciones' => [
                ['texto' => 'Acurrucarme con animales pequeños y suaves en un ambiente íntimo', 'pesos' => ['acogedor' => 3, 'intimo' => 2, 'tranquilo' => 1]],
                ['texto' => 'Jugar activamente con animales que se mueven y hacen piruetas', 'pesos' => ['energico' => 3, 'divertido' => 2, 'social' => 1]],
                ['texto' => 'Alimentar y acariciar animales grandes en espacio abierto', 'pesos' => ['natural' => 3, 'social' => 2, 'energico' => 1]],
                ['texto' => 'Observar animales tranquilos sin interacción obligatoria', 'pesos' => ['zen' => 3, 'tranquilo' => 2, 'natural' => 1]],
            ],
        ],
    ];

    /**
     * Perfiles de cafés mapeados a las cuatro categorías reales de la franquicia.
     * El slug referencia el café representativo de cada categoría (mayor rating).
     * Categorías: lounge · playroom · farm · zen
     */
    private const PERFILES_CAFES = [
        'usagi-paradise' => [
            'nombre' => 'Lounge Íntimo',
            'categoria' => 'lounge',
            'animal_guia' => 'Conejo de Luna',
            'personalidad_guia' => 'Suave y cercano: te invita a quedarte un poco más',
            'caracteristicas' => ['acogedor' => 3, 'intimo' => 3, 'tranquilo' => 2, 'zen' => 1],
            'descripcion' => 'Tu lugar ideal es un lounge tranquilo: ambiente íntimo, animales pequeños y suaves, sin prisa. Perfectos para leer, trabajar o simplemente descansar.',
        ],
        'mame-shiba-cafe' => [
            'nombre' => 'Playroom Animado',
            'categoria' => 'playroom',
            'animal_guia' => 'Shiba Saltarín',
            'personalidad_guia' => 'Desbordante de energía: te contagia las ganas de jugar',
            'caracteristicas' => ['energico' => 3, 'social' => 3, 'divertido' => 3, 'acogedor' => 1],
            'descripcion' => 'Tu café ideal es un playroom lleno de vida: animales juguetones, risas y un ambiente donde la diversión manda. Ideal para ir con amigos o familia.',
        ],
        'capyba-land' => [
            'nombre' => 'Urban Farm',
            'categoria' => 'farm',
            'animal_guia' => 'Capybara Sereno',
            'personalidad_guia' => 'Pausado y generoso: enseña que ir despacio también es avanzar',
            'caracteristicas' => ['natural' => 3, 'social' => 2, 'tranquilo' => 2, 'energico' => 1],
            'descripcion' => 'Tu espacio es una urban farm: animales grandes, espacio abierto y conexión con la naturaleza. Para quienes buscan autenticidad y calma activa.',
        ],
        'slow-life' => [
            'nombre' => 'Zen Sanctuary',
            'categoria' => 'zen',
            'animal_guia' => 'Tortuga Sabia',
            'personalidad_guia' => 'Contemplativa y profunda: no tiene prisa, el tiempo es suyo',
            'caracteristicas' => ['zen' => 3, 'tranquilo' => 3, 'natural' => 2, 'intimo' => 1],
            'descripcion' => 'Tu refugio es un santuario zen: animales que invitan a la calma, observación sin presión y un ambiente diseñado para desconectar y respirar.',
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
        $cafeData = $this->cafeRepo->findBySlug($mejorCafe['slug']);

        // Almacenar resultado en sesión y redirigir (evita document.write en cliente)
        Session::set('quiz_result', [
            'cafe' => $mejorCafe,
            'cafeData' => $cafeData,
            'puntuaciones' => $puntuaciones,
        ]);

        return $this->response->redirect('/quiz/resultado');
    }

    /**
     * GET /quiz/resultado
     * Muestra el resultado del quiz almacenado en sesión tras el POST
     */
    public function resultadoGet(ServerRequestInterface $request): ?ResponseInterface
    {
        $result = Session::get('quiz_result');

        if ($result === null) {
            return $this->response->redirect('/quiz');
        }

        Session::set('quiz_result', null);

        View::render('public/quiz/resultado', [
            'titulo' => 'Tu Café del Alma | Resultado',
            'cafe' => $result['cafe'],
            'cafeData' => $result['cafeData'],
            'puntuaciones' => $result['puntuaciones'],
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
        return \array_find(self::PREGUNTAS, static fn ($pregunta) => $pregunta['id'] === $id);
    }
}
