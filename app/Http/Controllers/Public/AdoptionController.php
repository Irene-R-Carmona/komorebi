<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\NotFoundException;
use App\Services\Contracts\AdoptionServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador público del módulo de adopciones.
 *
 * Permite a los visitantes registrados consultar los animales disponibles
 * y enviar o retirar solicitudes de adopción.
 */
final class AdoptionController
{
    public function __construct(
        private readonly AdoptionServiceInterface $adoptionService,
        private readonly ResponseFactory $response,
    ) {
    }

    /**
     * GET /adopciones
     * Galería pública de animales disponibles para adopción.
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $animals = $this->adoptionService->getAdoptableAnimals();

        View::render('public/adoptions/index', [
            'titulo' => 'Adopciones — Komorebi Café',
            'animals' => $animals,
        ], ['adopciones.css']);

        return null;
    }

    /**
     * GET /adopciones/{id}
     * Detalle del animal y formulario de solicitud de adopción.
     *
     * @throws NotFoundException Si el animal no está disponible para adopción.
     */
    public function show(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $animals = $this->adoptionService->getAdoptableAnimals();

        $animal = null;
        foreach ($animals as $a) {
            if ((int) $a['id'] === $id) {
                $animal = $a;
                break;
            }
        }

        if ($animal === null) {
            throw new NotFoundException('Animal no disponible para adopción');
        }

        $userId = Session::has('user_id') ? (int) Session::get('user_id') : null;
        $myRequests = $userId !== null
            ? $this->adoptionService->getUserRequests($userId)
            : [];

        $alreadyRequested = false;
        foreach ($myRequests as $r) {
            if ((int) $r['animal_id'] === $id && $r['status'] === 'pending') {
                $alreadyRequested = true;
                break;
            }
        }

        View::render('public/adoptions/show', [
            'titulo' => 'Adoptar a ' . $animal['name'] . ' — Komorebi Café',
            'animal' => $animal,
            'already_requested' => $alreadyRequested,
            'is_logged_in' => $userId !== null,
            'csrf_token' => Csrf::token(),
        ], ['adopciones.css']);

        return null;
    }

    /**
     * POST /adopciones/{id}/solicitar
     * Envía una solicitud de adopción para el animal indicado.
     */
    public function store(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $userId = (int) Session::get('user_id');
        $body = (array) ($request->getParsedBody() ?? []);
        $message = isset($body['message']) ? \trim((string) $body['message']) : null;

        $result = $this->adoptionService->requestAdoption($userId, $id, $message ?: null);

        if (!$result->ok) {
            Flash::error($result->error ?? 'No se pudo enviar la solicitud.');

            return $this->response->redirect('/adopciones/' . $id);
        }

        Flash::success('Tu solicitud ha sido enviada. Te avisaremos cuando sea revisada.');

        return $this->response->redirect('/adopciones/' . $id);
    }

    /**
     * POST /adopciones/solicitudes/{id}/retirar
     * El usuario retira su propia solicitud pendiente.
     */
    public function withdraw(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $userId = (int) Session::get('user_id');
        $result = $this->adoptionService->withdrawRequest($userId, $id);

        if (!$result->ok) {
            Flash::error($result->error ?? 'No se pudo retirar la solicitud.');
        } else {
            Flash::success('Tu solicitud ha sido retirada.');
        }

        return $this->response->redirect('/adopciones');
    }
}
