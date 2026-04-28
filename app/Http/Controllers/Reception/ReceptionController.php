<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reception;

use App\Core\Container;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\ValidationException;
use App\Services\ContextServiceInstance;
use App\Services\Contracts\ReceptionServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Recepción
 *
 * Gestiona la recepción de huéspedes: check-in, check-out y trackers.
 */
final class ReceptionController
{
    private ReceptionServiceInterface $service;

    private ?ContextServiceInstance $context = null;

    public function __construct(
        ?ReceptionServiceInterface $service = null,
        ?ContextServiceInstance $context = null,
    ) {
        $this->service = $service ?? Container::make(ReceptionServiceInterface::class);
        $this->context = $context;
    }

    private function context(): ContextServiceInstance
    {
        return $this->context ??= \App\Core\Container::make(ContextServiceInstance::class);
    }

    /**
     * GET /ops/reception
     * Muestra el panel de recepción con reservas próximas y grupos activos.
     *
     * @throws ValidationException Si falta contexto de sede
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context()->getCafeId();

        if ($cafeId === null) {
            throw ValidationException::withMessage('Recepción requiere un contexto de sede');
        }

        // Obtener datos del servicio
        $reservasRaw = $this->service->getPendingArrivals($cafeId);
        $activeGroups = $this->service->getActiveGroups($cafeId);
        $freeTrackers = $this->service->getAvailableTrackers($cafeId);
        $capInfo = $this->service->getCapacityInfo($cafeId);

        // Procesar reservas para presentación
        $reservasUI = $this->processReservationsForDisplay($reservasRaw);

        // Calcular ocupación actual
        $ocupacion = \array_sum(\array_column($activeGroups, 'guests'));

        // Guardar nombre de sede en sesión para el layout
        $cafeName = $this->context()->getCafeName();
        Session::set('user_cafe_name', $cafeName);

        // Renderizar vista
        View::render('reception/index', [
            'titulo' => 'Recepción - ' . $cafeName,
            'cafe_id' => $cafeId,
            'reservas' => $reservasUI,
            'active_groups' => $activeGroups,
            'free_trackers' => $freeTrackers,
            'ocupacion' => $ocupacion,
            'cap_max' => $capInfo['capacity_max'] ?? 0,
            'extraJs' => ['sections/reception.js'],
        ], ['workspaces/reception.css'], 'reception');

        return null;
    }

    /**
     * GET /ops/reception/reservations
     * Lista las reservas del día actual para la sede activa.
     *
     * @throws ValidationException Si falta contexto de sede
     */
    public function todayReservations(ServerRequestInterface $request): ?ResponseInterface
    {
        $cafeId = $this->context()->getCafeId();

        if ($cafeId === null) {
            throw ValidationException::withMessage('Recepción requiere un contexto de sede');
        }

        $reservations = $this->service->getPendingArrivals($cafeId);

        View::render('reception/index', [
            'titulo' => 'Reservas de hoy - ' . $this->context()->getCafeName(),
            'reservations' => $reservations,
        ], [], 'reception');

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    private function processReservationsForDisplay(array $reservasRaw): array
    {
        $reservasUI = [];
        $ahora = \time();

        foreach ($reservasRaw as $r) {
            $horaReserva = \strtotime($r['reservation_date'] . ' ' . $r['reservation_time']);
            $diffMinutos = (int) \round(($horaReserva - $ahora) / 60);

            if ($diffMinutos < -15) {
                $r['ui_state'] = 'late';
                $r['ui_label'] = 'RETRASO';
            } elseif ($diffMinutos <= 15) {
                $r['ui_state'] = 'now';
                $r['ui_label'] = 'AHORA';
            } else {
                $r['ui_state'] = 'future';
                $r['ui_label'] = 'LLEGADA';
            }

            $r['ui_time'] = \substr($r['reservation_time'], 0, 5);
            $reservasUI[] = $r;
        }

        return $reservasUI;
    }
}
