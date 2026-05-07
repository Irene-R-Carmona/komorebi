<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Core\Container;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
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

    private ResponseFactory $response;

    public function __construct(
        ?WaitlistServiceInterface $service = null,
        ?ResponseFactory $response = null,
    ) {
        $this->service = $service ?? Container::make(WaitlistServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
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
            'waitlists' => $result->data['waitlists'] ?? [],
            'extraCss' => ['loyalty.css'],
        ]);

        return null;
    }

    /**
     * POST /user/waitlists/{id}/cancel
     *
     * Cancelar una entrada de lista de espera del usuario autenticado
     */
    public function cancel(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $userId = (int) Session::get('user_id');

        if ($id <= 0 || $userId <= 0) {
            Flash::error('No se pudo cancelar la lista de espera.');

            return $this->response->redirect('/user/waitlists');
        }

        $result = $this->service->cancelWaitlist($id, $userId);

        if (!$result->ok) {
            Flash::error($result->error ?? 'No se pudo cancelar la lista de espera.');
        } else {
            Flash::success('Lista de espera cancelada correctamente.');
        }

        return $this->response->redirect('/user/waitlists');
    }
}
