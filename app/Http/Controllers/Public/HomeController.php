<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\Session;
use App\Core\View;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\CafeCatalogRepositoryInterface;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de la Página Principal
 */
final class HomeController
{
    private CafeCatalogRepositoryInterface $cafeRepo;
    private AnimalRepositoryInterface $animalRepo;
    private FavoriteRepositoryInterface $favoriteRepo;

    public function __construct(
        ?CafeCatalogRepositoryInterface $cafeRepo = null,
        ?FavoriteRepositoryInterface $favoriteRepo = null,
        ?AnimalRepositoryInterface $animalRepo = null
    ) {
        $this->cafeRepo = $cafeRepo ?? Container::make(CafeCatalogRepositoryInterface::class);
        $this->favoriteRepo = $favoriteRepo ?? Container::make(FavoriteRepositoryInterface::class);
        $this->animalRepo = $animalRepo ?? Container::make(AnimalRepositoryInterface::class);
    }

    /**
     * GET /
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        // 103 Early Hints — FrankenPHP envía la cabecera antes de las queries
        \header('Link: </css/home.css>; rel=preload; as=style', false);
        if (\function_exists('headers_send')) {
            \headers_send(103);
        }

        // Estadísticas generales
        $cafes = $this->cafeRepo->findActive();
        $totalCafes = \count($cafes);

        // Calcular valoración media de todos los cafés activos
        $ratings = \array_filter(\array_column($cafes, 'rating_avg'), fn($r) => $r !== null && (float) $r > 0);
        $ratingPromedio = $ratings !== []
            ? \number_format(\array_sum($ratings) / \count($ratings), 1)
            : '5.0';

        // Número de especies distintas en el sistema
        $totalEspecies = $this->animalRepo->countDistinctSpecies();

        // Cafés destacados (por rating)
        $featuredCafes = $this->getFeaturedCafes($cafes);

        // Datos del usuario si está autenticado
        $userData = null;
        if (Session::isAuthenticated()) {
            $userData = $this->getUserHomeData();
        }

        View::render('public/home', [
            'titulo' => 'Komorebi Café',
            'totalCafes' => $totalCafes,
            'totalEspecies' => $totalEspecies,
            'ratingPromedio' => $ratingPromedio,
            'featuredCafes' => $featuredCafes,
            'userData' => $userData,
            'categories' => $this->getCategoryStats($cafes),
        ], ['home.css']);

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene los cafés destacados (mejor rating).
     */
    private function getFeaturedCafes(array $cafes): array
    {
        // Ordenar por rating descendente
        \usort($cafes, static fn($a, $b) => (float) ($b['rating_avg'] ?? 0) <=> (float) ($a['rating_avg'] ?? 0));

        return \array_slice($cafes, 0, 3);
    }

    /**
     * Obtiene datos relevantes para el usuario autenticado.
     */
    private function getUserHomeData(): array
    {
        $userId = Session::userId();

        return [
            'name' => Session::userName(),
            'favorites_count' => $this->favoriteRepo->countByUser($userId),
        ];
    }

    /**
     * Obtiene estadísticas por categoría.
     */
    private function getCategoryStats(array $cafes): array
    {
        $stats = [];

        foreach ($cafes as $cafe) {
            $category = $cafe['category'];

            if (!isset($stats[$category])) {
                $stats[$category] = [
                    'name' => $this->getCategoryName($category),
                    'count' => 0,
                    'icon' => $this->getCategoryIcon($category),
                ];
            }

            $stats[$category]['count']++;
        }

        return $stats;
    }

    /**
     * Obtiene el nombre amigable de una categoría.
     */
    private function getCategoryName(string $category): string
    {
        return match ($category) {
            'lounge' => 'Lounge',
            'playroom' => 'Sala de Juegos',
            'farm' => 'Granja',
            'zen' => 'Zen',
            default => \ucfirst($category),
        };
    }

    /**
     * Obtiene el icono de una categoría.
     */
    private function getCategoryIcon(string $category): string
    {
        return match ($category) {
            'lounge' => 'coffee',
            'playroom' => 'game-controller',
            'farm' => 'plant',
            'zen' => 'yin-yang',
            default => 'storefront',
        };
    }
}
