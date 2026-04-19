<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Container;
use App\Core\View;
use App\Services\Contracts\WaitlistServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WaitlistViewController - Vistas públicas para consultar estado de waitlist
 */
final class WaitlistViewController
{
    private WaitlistServiceInterface $service;

    public function __construct(?WaitlistServiceInterface $service = null)
    {
        $this->service = $service ?? Container::make(WaitlistServiceInterface::class);
    }

    /**
     * GET /waitlist/status/{token}
     *
     * Vista HTML para que el usuario consulte su posición en la lista de espera
     */
    public function status(ServerRequestInterface $request, string $token): ?ResponseInterface
    {
        if (empty($token)) {
            View::render('errors/400', [], [], 'errors');

            return null;
        }

        $result = $this->service->getWaitlistStatus($token);

        if (!$result->ok) {
            View::render('errors/404', ['error' => $result->error ?? ''], [], 'errors');

            return null;
        }

        View::render('public/waitlist-status', [
            'titulo' => 'Estado de Lista de Espera - Komorebi Café',
            'waitlist' => $result->data ?? [],
        ], ['waitlist-status.css']);

        return null;
    }

    /**
     * GET /waitlist/confirm/{token}
     *
     * Vista HTML con formulario para confirmar promoción desde waitlist
     */
    public function confirmView(ServerRequestInterface $request, string $token): ?ResponseInterface
    {
        if (empty($token)) {
            View::render('errors/400', [], [], 'errors');

            return null;
        }

        $result = $this->service->getWaitlistStatus($token);

        if (!$result->ok) {
            View::render('errors/404', ['error' => $result->error ?? ''], [], 'errors');

            return null;
        }

        $waitlist = $result->data ?? [];

        // Solo mostrar formulario si está en estado 'notified'
        if ($waitlist['status'] !== 'notified') {
            View::render('errors/400', [], [], 'errors');

            return null;
        }

        View::render('public/waitlist-confirm', [
            'titulo' => 'Confirmar Reserva - Komorebi Café',
            'waitlist' => $waitlist,
        ], ['waitlist-confirm.css']);

        return null;
    }

    /**
     * POST /waitlist/confirm/{token}
     *
     * Procesar confirmación de promoción
     */
    public function confirmSubmit(ServerRequestInterface $request, string $token): ?ResponseInterface
    {
        if (empty($token)) {
            View::render('errors/400', [], [], 'errors');

            return null;
        }

        $result = $this->service->confirmPromotion($token, []);

        if (!$result->ok) {
            View::render('errors/400', ['error' => $result->error ?? ''], [], 'errors');

            return null;
        }

        $data = $result->data ?? [];

        // Redirigir a página de éxito o mostrar mensaje
        $message = 'Tu reserva ha sido confirmada exitosamente';
        $reservationId = $data['reservation_id'];

        View::render('public/waitlist-success', [
            'message' => $message,
            'reservationId' => $reservationId,
        ]);

        return null;
    }
}
