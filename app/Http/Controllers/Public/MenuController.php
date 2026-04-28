<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\View;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\MenuServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MenuController
{
    private MenuServiceInterface $menuService;
    private CartServiceInterface $cartService;

    public function __construct(
        ?MenuServiceInterface $menuService = null,
        ?CartServiceInterface $cartService = null,
    ) {
        $this->menuService = $menuService ?? Container::make(MenuServiceInterface::class);
        $this->cartService = $cartService ?? Container::make(CartServiceInterface::class);
    }

    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        // Leer parámetros de alérgenos a excluir desde la querystring
        $queryParams = $request->getQueryParams();
        $excludeAllergens = [];
        if (!empty($queryParams['exclude_allergens']) && \is_array($queryParams['exclude_allergens'])) {
            $excludeAllergens = \array_values(\array_filter(\array_map('intval', $queryParams['exclude_allergens']), static fn($v) => $v > 0));
        }

        // 103 Early Hints — FrankenPHP envía la cabecera antes de las queries
        \header('Link: </css/menu.css>; rel=preload; as=style', false);
        if (\function_exists('headers_send')) {
            \headers_send(103);
        }

        $data = $this->menuService->getMenuForView($excludeAllergens);
        $data['excludeAllergens'] = $excludeAllergens;

        $cart = $this->cartService->get();
        $cart['total_qty'] = $cart['totalQty'] ?? 0;
        unset($cart['totalQty']);
        $data['cartInicial'] = $cart;

        View::render('public/menu/index', $data, ['menu.css']);

        return null;
    }
}
