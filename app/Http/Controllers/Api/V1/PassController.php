<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Repositories\Contracts\PassInclusionRepositoryInterface;
use App\Services\Contracts\AvailabilityServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API pública de pases disponibles.
 *
 * Respuesta cacheable con ETag y Cache-Control público.
 */
final class PassController extends AbstractApiController
{
    public function __construct(
        ResponseFactory $response,
        private readonly AvailabilityServiceInterface $availability,
        private readonly PassInclusionRepositoryInterface $passInclusionRepo,
    ) {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/passes
     *
     * Devuelve los pases disponibles para reserva, enriquecidos con sus
     * inclusiones (categorías de producto cubiertas por el precio del pase).
     * Respuesta cacheable con ETag + Cache-Control público.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $passes = $this->availability->getAvailablePassesForReservation();

        foreach ($passes as &$pass) {
            $pass['inclusions'] = $this->passInclusionRepo->findByPassId((int) $pass['id']);
        }
        unset($pass);

        $etag = $this->makeEtag($passes);
        $cc = 'public, max-age=300';

        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->notModified($etag, $cc);
        }

        return $this->success(
            ['items' => $passes, 'total' => \count($passes)],
            200,
            ['Cache-Control' => $cc, 'ETag' => $etag]
        );
    }

    /**
     * GET /api/v1/passes/slots
     *
     * Devuelve los turnos disponibles para un pase, café, fecha y número de
     * personas. Filtra por horario del café y duración del pase usando
     * AvailabilityService, que es la implementación correcta.
     *
     * Query params requeridos: cafe_id, pass_id, date (YYYY-MM-DD), guests
     */
    public function slots(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $cafeId = isset($params['cafe_id']) ? (int) $params['cafe_id'] : 0;
        $passId = isset($params['pass_id']) ? (int) $params['pass_id'] : 0;
        $date = \trim((string) ($params['date'] ?? ''));
        $guests = isset($params['guests']) ? (int) $params['guests'] : 0;

        if ($cafeId <= 0 || $passId <= 0 || $guests <= 0) {
            return $this->unprocessable(
                'Los parámetros cafe_id, pass_id y guests son requeridos y deben ser positivos.',
                'params_required'
            );
        }

        if ($date === '' || \preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return $this->unprocessable('Fecha inválida (formato esperado: YYYY-MM-DD).', 'invalid_date');
        }

        $result = $this->availability->getAvailableSlots($cafeId, $passId, $date, $guests);

        if (!$result->ok) {
            return $this->unprocessable($result->error ?? 'Error al obtener turnos.', $result->code ?? 'slots_error');
        }

        return $this->success($result->data);
    }
}
