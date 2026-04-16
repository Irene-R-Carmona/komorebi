<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\View;
use App\Services\Contracts\MenuServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MenuController
{
    private MenuServiceInterface $menuService;

    public function __construct(?MenuServiceInterface $menuService = null)
    {
        $this->menuService = $menuService ?? Container::make(MenuServiceInterface::class);
    }

    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        // Leer parámetros de alérgenos a excluir desde la querystring
        $queryParams = $request->getQueryParams();
        $excludeAllergens = [];
        if (!empty($queryParams['exclude_allergens']) && \is_array($queryParams['exclude_allergens'])) {
            $excludeAllergens = \array_values(\array_filter(\array_map('intval', $queryParams['exclude_allergens']), static fn ($v) => $v > 0));
        }

        $data = $this->menuService->getMenuForView($excludeAllergens);
        // Pasar también el array de alérgenos excluidos a la vista para que Alpine lo consuma
        $data['excludeAllergens'] = $excludeAllergens;

        // Render with the standard layout so CSS/JS are included
        View::render('public/menu/index', $data, ['menu.css']);

        return null;
    }
}
