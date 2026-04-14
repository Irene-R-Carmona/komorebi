<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Core\Container;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Services\Contracts\WaitlistServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WaitlistController - Gestión de listas de espera del usuario
 */
final class WaitlistController
{
    private WaitlistServiceInterface $service;

    public function __construct()
    {
        $this->service = Container::make(WaitlistServiceInterface::class);
    }

    /**
     * GET /user/waitlists
     *
     * Mostrar todas las listas de espera del usuario autenticado
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            View::render('errors/401', [], [], 'errors');
            return null;
        }

        $result = $this->service->getUserWaitlists((int) $userId, true);

        if (!$result->ok) {
            View::render('errors/500', ['error' => $result->error], [], 'errors');
            return null;
        }

        View::render('user/waitlists', [
            'titulo' => 'Mis Listas de Espera - Komorebi Café',
            'waitlists' => $result->data,
            'extraCss' => ['loyalty.css']
        ]);
        return null;
    }
}
