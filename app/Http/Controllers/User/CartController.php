<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

/**
 * Página del carrito del usuario autenticado.
 * Renderiza la vista que consume /api/cart via Alpine.js.
 */
final class CartController
{
    /**
     * GET /carrito
     */
    public function index(): void
    {
        $user = Session::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        View::render('user/cart', [
            'titulo'    => 'Mi Carrito',
            'csrfToken' => Csrf::token(),
        ], [], 'main');
    }
}
