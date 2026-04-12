<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\View;
use App\Models\Waitlist;
use PDO;

/**
 * WaitlistController - Panel de administración de listas de espera
 */
final class WaitlistController
{
    private Waitlist $waitlistModel;

    public function __construct(PDO $db)
    {
        $this->waitlistModel = new Waitlist($db);
    }

    /**
     * Vista principal - listado de todas las waitlists activas
     *
     * TODO(fase2-psr7): Migrar a PSR-7 (retorna void, usa $_GET, http_response_code() y exit)
     *                   antes de añadir tests unitarios. Ver docs/superpowers/plans/2026-04-10-fase2-psr7-migration.md
     *
     * @return void
     */
    public function index(): void
    {
        $filters = [
            'cafe_id' => $_GET['cafe_id'] ?? null,
            'status'  => $_GET['status'] ?? 'waiting',
            'date'    => $_GET['date'] ?? null,
        ];

        // Obtener todas las waitlists activas con información del slot y usuario
        $waitlists = $this->waitlistModel->getAllWithDetails($filters);

        // Obtener resumen por estado
        $summary = $this->waitlistModel->getSummaryByStatus();

        View::render('admin/waitlist/index', [
            'waitlists' => $waitlists,
            'summary'   => $summary,
            'filters'   => $filters,
        ], [], 'backoffice');
    }

    /**
     * Ver detalle de una waitlist específica
     *
     * @param integer $id
     * @return void
     */
    public function show(int $id): void
    {
        $waitlist = $this->waitlistModel->findById($id);

        if (!$waitlist) {
            http_response_code(404);
            View::render('errors/404', [], [], 'errors');
            exit;
        }

        View::render('admin/waitlist/show', [
            'waitlist' => $waitlist,
        ], [], 'backoffice');
    }

    /**
     * Cancelar una waitlist manualmente
     *
     * @param integer $id
     * @return void
     */
    public function cancel(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        $result = $this->waitlistModel->cancelById($id);

        if ($result) {
            header('Location: /admin/waitlists?msg=cancelled');
            exit;
        }

        http_response_code(500);
        echo 'Error cancelando waitlist';
        exit;
    }
}
