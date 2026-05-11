<?php

declare(strict_types=1);

namespace App\Http\Controllers\Keeper;

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
 * Controlador de gestión de solicitudes de adopción (rol Keeper).
 *
 * Permite al keeper revisar las solicitudes pendientes, aprobarlas o rechazarlas,
 * y consultar el historial de adopciones procesadas.
 */
final class AdoptionController
{
    public function __construct(
        private readonly AdoptionServiceInterface $adoptionService,
        private readonly ResponseFactory $response,
    ) {
    }

    /**
     * GET /keeper/adopciones
     * Lista de solicitudes pendientes de revisión.
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $pending = $this->adoptionService->getPendingRequests($cafeId);

        View::render('backoffice/keeper/adoptions/index', [
            'titulo' => 'Solicitudes de Adopción Pendientes',
            'pending' => $pending,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /keeper/adopciones/historial
     * Historial de solicitudes aprobadas y rechazadas.
     */
    public function history(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $processed = $this->adoptionService->getProcessedRequests($cafeId);

        View::render('backoffice/keeper/adoptions/history', [
            'titulo' => 'Historial de Adopciones',
            'processed' => $processed,
        ], [], 'backoffice');

        return null;
    }

    /**
     * GET /keeper/adopciones/{id}
     * Detalle de una solicitud de adopción.
     *
     * @throws NotFoundException Si la solicitud no existe.
     */
    public function show(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $adoptionRequest = $this->adoptionService->getPendingRequests($cafeId);

        // Buscar la solicitud específica dentro de los datos del servicio
        // (usamos el método del repo directamente a través del servicio)
        // En la práctica se resuelve exponiéndolo via AdoptionService::getRequestById
        // Para minimizar la superficie de la interface, el controller recarga la vista
        // con todos los pendientes filtrados por id.
        $found = null;
        foreach ($adoptionRequest as $r) {
            if ((int) $r['id'] === $id) {
                $found = $r;
                break;
            }
        }

        if ($found === null) {
            throw new NotFoundException('Solicitud de adopción no encontrada');
        }

        View::render('backoffice/keeper/adoptions/show', [
            'titulo' => 'Detalle de Solicitud de Adopción',
            'request' => $found,
            'csrf_token' => Csrf::token(),
        ], [], 'backoffice');

        return null;
    }

    /**
     * POST /keeper/adopciones/{id}/aprobar
     * Aprueba una solicitud de adopción.
     */
    public function approve(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $keeperId = (int) Session::get('user_id');
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $result = $this->adoptionService->approveRequest($keeperId, $id, $cafeId);

        if (!$result->ok) {
            Flash::error($result->error ?? 'No se pudo aprobar la adopción.');

            return $this->response->redirect('/keeper/adopciones');
        }

        Flash::success('Adopción aprobada. El animal ha sido marcado como adoptado.');

        return $this->response->redirect('/keeper/adopciones');
    }

    /**
     * POST /keeper/adopciones/{id}/rechazar
     * Rechaza una solicitud de adopción con notas opcionales.
     */
    public function reject(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $keeperId = (int) Session::get('user_id');
        $cafeId = (int) (Session::user()['cafe_id'] ?? 0);
        $body = (array) ($request->getParsedBody() ?? []);
        $notes = isset($body['keeper_notes']) ? \trim((string) $body['keeper_notes']) : null;

        $result = $this->adoptionService->rejectRequest($keeperId, $id, $notes ?: null, $cafeId);

        if (!$result->ok) {
            Flash::error($result->error ?? 'No se pudo rechazar la solicitud.');

            return $this->response->redirect('/keeper/adopciones/' . $id);
        }

        Flash::success('Solicitud rechazada.');

        return $this->response->redirect('/keeper/adopciones');
    }
}
