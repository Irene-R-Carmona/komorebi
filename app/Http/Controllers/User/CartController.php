<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Core\Csrf;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Página del carrito del usuario autenticado.
 * Renderiza la vista que consume /api/cart via Alpine.js.
 */
final class CartController
{
    private ResponseFactory $response;

    public function __construct(?ResponseFactory $response = null)
    {
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /carrito
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $user = Session::user();
        if (!$user) {
            return $this->response->redirect('/login');
        }

        View::render('user/cart', [
            'titulo' => 'Mi Carrito',
            'csrfToken' => Csrf::token(),
        ], [], 'main');

        return null;
    }
}
