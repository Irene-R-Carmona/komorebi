<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Models\Cafe;
use App\Models\Favorite;
use App\Services\MenuService;
use App\Services\ReviewService;

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
    private Cafe $cafeModel;

    private Favorite $favoriteModel;

    private MenuService $menuService;

    private ReviewService $reviewService;

    public function __construct()
    {
        $this->cafeModel = new Cafe();
        $this->favoriteModel = new Favorite();
        $this->menuService = new MenuService();
        $this->reviewService = new ReviewService();
    }

    /**
     * GET /cafes
     * Lista todos los cafés activos.
     */
    public function index(): void
    {
        // Obtener filtros de query string
        $category = $this->getQueryParam('categoria');
        $animalType = $this->getQueryParam('animal');
        $orderBy = $this->getQueryParam('orden', 'name');

        // Obtener cafés
        $cafes = $this->cafeModel->findAll(
            category: $category,
            animalType: $animalType,
            orderBy: $orderBy
        );

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
    }

    /**
     * GET /cafes/{slug}
     * Muestra el detalle de un café.
     *
     * @throws NotFoundException Si el café no existe (manejado por ExceptionHandler)
     */
    public function show(string $slug): void
    {

        // Obtener café con sus animales
        $cafe = $this->cafeModel->findWithAnimals($slug);

        // Si no existe, lanzar excepción
        // ExceptionHandler automáticamente renderiza errors/404
        if (!$cafe) {
            throw NotFoundException::forResource('Café', $slug);
        }

        // Verificar si es favorito del usuario actual
        $isFavorite = false;

        if (Session::isAuthenticated()) {
            $isFavorite = $this->favoriteModel->exists(
                Session::userId(),
                (int) $cafe['id']
            );
        }

        // Obtener zonas del café
        $zones = $this->cafeModel->getZones((int) $cafe['id']);

        // Obtener estadísticas de reseñas
        $ratingStats = $this->reviewService->getCafeRatingStats((int) $cafe['id']);

        // Obtener reseñas aprobadas (página 1, 5 por página para mostrar)
        $approvedReviews = $this->reviewService->listApprovedReviews((int) $cafe['id'], 1);

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
            'total_animals' => \count($cafe['animals']),
            'active_animals' => \count(\array_filter(
                $cafe['animals'],
                static fn ($a) => $a['current_status'] === 'active'
            )),
            'favorites_count' => $this->cafeModel->getFavoritesCount((int) $cafe['id']),
        ];

        View::render('public/cafes/show', [
            'titulo' => $cafe['name'],
            'cafe' => $cafe,
            'animales' => $cafe['animals'],
            'zones' => $zones,
            'experiences' => $experiences,
            'isFavorite' => $isFavorite,
            'stats' => $stats,
            'ratingStats' => $ratingStats,
            'approvedReviews' => $approvedReviews,
            'canReview' => $canReview,
            'reviewEligibility' => $reviewEligibility,
        ], ['catalogo.css', 'reviews.css']);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene un parámetro de query string sanitizado.
     */
    private function getQueryParam(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;

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
        // Esto podría cachearse o moverse al modelo
        $cafes = $this->cafeModel->findAll();

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
