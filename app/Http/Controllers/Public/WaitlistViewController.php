<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Database;
use App\Core\View;
use App\Services\WaitlistService;

/**
 * WaitlistViewController - Vistas públicas para consultar estado de waitlist
 */
final class WaitlistViewController
{
    private WaitlistService $service;

    public function __construct()
    {
        $this->service = new WaitlistService(Database::getConnection());
    }

    /**
     * GET /waitlist/status/{token}
     *
     * Vista HTML para que el usuario consulte su posición en la lista de espera
     */
    public function status(string $token): void
    {
        if (empty($token)) {
            View::render('errors/400', [], [], 'errors');
            return;
        }

        $result = $this->service->getWaitlistStatus($token);

        if (!$result->isOk()) {
            View::render('errors/404', ['error' => $result->getMessage()], [], 'errors');
            return;
        }

        View::render('public/waitlist-status', [
            'titulo' => 'Estado de Lista de Espera - Komorebi Café',
            'waitlist' => $result->getDataOr([]),
        ]);
    }

    /**
     * GET /waitlist/confirm/{token}
     *
     * Vista HTML con formulario para confirmar promoción desde waitlist
     */
    public function confirmView(string $token): void
    {
        if (empty($token)) {
            http_response_code(400);
            require __DIR__ . '/../../../resources/views/errors/400.php';
            return;
        }

        $result = $this->service->getWaitlistStatus($token);

        if (!$result->isOk()) {
            http_response_code(404);
            $error = $result->getMessage();
            require __DIR__ . '/../../../resources/views/errors/404.php';
            return;
        }

        $waitlist = $result->getDataOr([]);

        // Solo mostrar formulario si está en estado 'notified'
        if ($waitlist['status'] !== 'notified') {
            http_response_code(400);
            $error = 'Esta promoción ya fue procesada o no está disponible';
            require __DIR__ . '/../../../resources/views/errors/400.php';
            return;
        }

        require __DIR__ . '/../../../resources/views/public/waitlist-confirm.php';
    }

    /**
     * POST /waitlist/confirm/{token}
     *
     * Procesar confirmación de promoción
     */
    public function confirmSubmit(string $token): void
    {
        if (empty($token)) {
            http_response_code(400);
            require __DIR__ . '/../../../resources/views/errors/400.php';
            return;
        }

        $result = $this->service->confirmPromotion($token, []);

        if (!$result->isOk()) {
            http_response_code(400);
            $error = $result->getMessage();
            require __DIR__ . '/../../../resources/views/errors/400.php';
            return;
        }

        $data = $result->getDataOr([]);

        // Redirigir a página de éxito o mostrar mensaje
        $message = 'Tu reserva ha sido confirmada exitosamente';
        $reservationId = $data['reservation_id'];

        require __DIR__ . '/../../../resources/views/public/waitlist-success.php';
    }
}
