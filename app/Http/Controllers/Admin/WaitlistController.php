<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Core\View;
use App\Http\Transformers\WaitlistTransformer;
use App\Models\Waitlist;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WaitlistController - Panel de administración de listas de espera
 */
final class WaitlistController
{
    private Waitlist $waitlistModel;
    private WaitlistTransformer $waitlistTransformer;

    public function __construct(
        PDO $db,
        private readonly ResponseFactory $response,
        ?WaitlistTransformer $waitlistTransformer = null,
    ) {
        $this->waitlistModel = new Waitlist($db);
        $this->waitlistTransformer = $waitlistTransformer ?? new WaitlistTransformer();
    }

    /**
     * Vista principal - listado de todas las waitlists activas
     *
     * PENDING(fase2-psr7): Migrar a PSR-7 (retorna void, usa $_GET, http_response_code() y exit)
     *                   antes de añadir tests unitarios. Ver docs/superpowers/plans/2026-04-10-fase2-psr7-migration.md
     *
     * @return ?ResponseInterface
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $filters = [
            'cafe_id' => $queryParams['cafe_id'] ?? null,
            'status'  => $queryParams['status'] ?? 'waiting',
            'date'    => $queryParams['date'] ?? null,
        ];

        // Obtener todas las waitlists activas con información del slot y usuario
        $waitlists = $this->waitlistTransformer->collection(
            $this->waitlistModel->getAllWithDetails($filters)
        );

        // Obtener resumen por estado
        $summary = $this->waitlistModel->getSummaryByStatus();

        View::render('admin/waitlist/index', [
            'waitlists' => $waitlists,
            'summary'   => $summary,
            'filters'   => $filters,
        ], [], 'backoffice');
        return null;
    }

    /**
     * Ver detalle de una waitlist específica
     *
     * @param integer $id
     * @return ?ResponseInterface
     */
    public function show(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        $rawWaitlist = $this->waitlistModel->findById($id);

        if (!$rawWaitlist) {
            View::render('errors/404', [], [], 'errors');
            return null;
        }

        $waitlist = $this->waitlistTransformer->transform($rawWaitlist);

        View::render('admin/waitlist/show', [
            'waitlist' => $waitlist,
        ], [], 'backoffice');
        return null;
    }

    /**
     * Cancelar una waitlist manualmente
     *
     * @param integer $id
     * @return ?ResponseInterface
     */
    public function cancel(ServerRequestInterface $request, int $id): ?ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->response->html('Method not allowed', 405);
        }

        $result = $this->waitlistModel->cancelById($id);

        if ($result) {
            return $this->response->redirect('/admin/waitlists?msg=cancelled');
        }

        return $this->response->html('Error cancelando waitlist', 500);
    }
}
