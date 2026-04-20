<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Http\Transformers\AnimalTransformer;
use App\Http\Transformers\CafeTransformer;
use App\Models\Favorite;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\Contracts\MenuServiceInterface;
use App\Services\Contracts\ReviewQueryServiceInterface;
use App\Services\Contracts\ReviewServiceInterface;
use JsonException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Cafés
 *
 * Gestiona el catálogo público de cafés.
 *
 * Nota: ExceptionHandler maneja automáticamente NotFoundException
 * y renderiza la vista errors/404 apropiada.
 */
final class CafeController
{
    private CafeRepositoryInterface $cafeRepo;

    private Favorite $favoriteModel;

    private MenuServiceInterface $menuService;

    private ReviewQueryServiceInterface $queryService;

    private ReviewServiceInterface $reviewService;

    private CafeTransformer $cafeTransformer;

    private AnimalTransformer $animalTransformer;

    public function __construct(
        ?MenuServiceInterface $menuService = null,
        ?ReviewQueryServiceInterface $queryService = null,
        ?ReviewServiceInterface $reviewService = null,
        ?CafeRepositoryInterface $cafeRepo = null,
        ?Favorite $favoriteModel = null,
        ?CafeTransformer $cafeTransformer = null,
        ?AnimalTransformer $animalTransformer = null,
    ) {
        $this->cafeRepo = $cafeRepo ?? Container::make(CafeRepositoryInterface::class);
        $this->favoriteModel = $favoriteModel ?? new Favorite(Container::make(PDO::class));
        $this->menuService = $menuService ?? Container::make(MenuServiceInterface::class);
        $this->queryService = $queryService ?? Container::make(ReviewQueryServiceInterface::class);
        $this->reviewService = $reviewService ?? Container::make(ReviewServiceInterface::class);
        $this->cafeTransformer = $cafeTransformer ?? new CafeTransformer();
        $this->animalTransformer = $animalTransformer ?? new AnimalTransformer();
    }

    /**
     * GET /cafes
     * Lista todos los cafés activos.
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        // Obtener filtros de query string
        $queryParams = $request->getQueryParams();
        $category = $this->getQueryParam($queryParams, 'categoria');
        $animalType = $this->getQueryParam($queryParams, 'animal');
        $orderBy = $this->getQueryParam($queryParams, 'orden', 'name');

        // Obtener cafés
        $queryFilters = \array_filter([
            'category' => $category,
            'animal_type' => $animalType,
        ]);
        $cafes = $this->cafeRepo->findFiltered($queryFilters);

        // Obtener favoritos del usuario si está logueado
        $favoritos = [];

        if (Session::isAuthenticated()) {
            $favoritos = $this->favoriteModel->getCafeIds(Session::userId());
        }

        // Obtener categorías y tipos de animal únicos (para filtros)
        $filters = $this->getAvailableFilters();

        View::render('public/cafes/index', [
            'titulo' => 'Nuestros Cafés',
            'cafes' => $cafes,
            'favoritos' => $favoritos,
            'filters' => $filters,
            'activeFilters' => [
                'categoria' => $category,
                'animal' => $animalType,
                'orden' => $orderBy,
            ],
        ], ['catalogo.css']);

        return null;
    }

    /**
     * GET /cafes/{slug}
     * Muestra el detalle de un café.
     *
     * @throws NotFoundException Si el café no existe (manejado por ExceptionHandler)
     */
    public function show(ServerRequestInterface $request, string $slug): ?ResponseInterface
    {

        // Obtener café con sus animales
        $cafe = $this->cafeRepo->findWithAnimals($slug);

        // Si no existe, lanzar excepción
        // ExceptionHandler automáticamente renderiza errors/404
        if (!$cafe) {
            throw NotFoundException::forResource('Café', $slug);
        }

        // Extraer animales antes de transformar el café
        $rawAnimals = $cafe['animals'] ?? [];

        // Aplicar CafeTransformer: presentación segura sin campos internos
        $cafe = $this->cafeTransformer->transform($cafe);

        // Verificar si es favorito del usuario actual
        $isFavorite = false;

        if (Session::isAuthenticated()) {
            $isFavorite = $this->favoriteModel->exists(
                Session::userId(),
                (int) $cafe['id']
            );
        }

        // Obtener zonas del café
        $zones = $this->cafeRepo->getZones((int) $cafe['id']);

        // Obtener estadísticas de reseñas
        $ratingStats = $this->queryService->getCafeRatingStats((int) $cafe['id']);

        // Obtener reseñas aprobadas (página 1, 5 por página para mostrar)
        $approvedReviews = $this->queryService->listApprovedReviews((int) $cafe['id'], 1);

        // Obtener experiencias disponibles para este café (filtradas por categoría y animal)
        $experiences = $this->menuService->getPassesForCafe($cafe['category'], $cafe['animal_type']);

        // Verificar elegibilidad del usuario para dejar reseña
        $canReview = false;
        $reviewEligibility = [];

        if (Session::isAuthenticated()) {
            $reviewEligibility = $this->reviewService->canUserReview(
                Session::userId(),
                (int) $cafe['id']
            );
            $canReview = $reviewEligibility['can_review'] ?? false;
        }

        // Obtener estadísticas generales
        $stats = [
            'total_animals' => \count($rawAnimals),
            'active_animals' => \count(\array_filter(
                $rawAnimals,
                static fn ($a) => $a['current_status'] === 'active'
            )),
            'favorites_count' => $this->cafeRepo->getFavoritesCount((int) $cafe['id']),
        ];

        // Decorar animales: merge JSON attributes en el objeto
        $animalesDecoded = \array_map(static function (array $a): array {
            if (empty($a['attributes'])) {
                return $a;
            }

            try {
                $attrs = \json_decode($a['attributes'], true, 512, \JSON_THROW_ON_ERROR) ?? [];
            } catch (JsonException $e) {
                Logger::warning('[CafeController] Error decodificando atributos de animal', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'animal_id' => $a['id'] ?? 'unknown',
                ]);
                $attrs = [];
            }

            return \array_merge($a, $attrs);
        }, $rawAnimals);

        // Aplicar AnimalTransformer: excluye campos operativos/sensibles
        $animalesPrep = $this->animalTransformer->collection($animalesDecoded);

        View::render('public/cafes/show', [
            'titulo' => $cafe['name'],
            'cafe' => $cafe,
            'animales' => $this->animalTransformer->collection($rawAnimals),
            'animalesPrep' => $animalesPrep,
            'zones' => $zones,
            'experiences' => $experiences,
            'isFavorite' => $isFavorite,
            'stats' => $stats,
            'ratingStats' => $ratingStats,
            'approvedReviews' => $approvedReviews,
            'canReview' => $canReview,
            'reviewEligibility' => $reviewEligibility,
        ], ['catalogo.css', 'reviews.css']);

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene un parámetro de query string sanitizado.
     */
    private function getQueryParam(array $queryParams, string $key, ?string $default = null): ?string
    {
        $value = $queryParams[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        // Sanitizar: solo caracteres válidos para filtros
        return \preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
    }

    /**
     * Obtiene los valores únicos para filtros.
     */
    private function getAvailableFilters(): array
    {
        // Esto podría cachearse o moverse al repositorio
        $cafes = $this->cafeRepo->findFiltered([]);

        $categories = \array_unique(\array_column($cafes, 'category'));
        $animalTypes = \array_unique(\array_column($cafes, 'animal_type'));

        \sort($categories);
        \sort($animalTypes);

        return [
            'categories' => $categories,
            'animal_types' => $animalTypes,
            'order_options' => [
                'name' => 'Nombre',
                'rating' => 'Valoración',
                'price_per_hour' => 'Precio',
            ],
        ];
    }
}
