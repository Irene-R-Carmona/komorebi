<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

/**
 * Página de favoritos del usuario autenticado.
 * Renderiza la vista que consume /api/favorites via Alpine.js.
 */
final class FavoriteController
{
    /**
     * GET /mis-favoritos
     */
    public function index(): void
    {
        $user = Session::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        View::render('user/favorites', [
            'titulo'    => 'Mis Favoritos',
            'csrfToken' => Csrf::token(),
        ], [], 'main');
    }
}
