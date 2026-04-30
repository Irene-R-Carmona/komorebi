<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Core\View;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\AvailabilityServiceInterface;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\ClimaContextoServiceInterface;
use App\Services\Contracts\FestivosJaponesesServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use DateMalformedStringException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Reservas Compartido
 *
 * Gestiona reservas para todos los roles (usuario, recepción, etc.).
 * Incluye lógica de carrito, validaciones y recomendaciones basadas en clima/festivos.
 */
final class ReservationController
{
    private const string WIZARD_KEY = 'reservation_wizard';
    private const string ROUTE_LOGIN = '/login';
    private const string ROUTE_RESERVAR = '/reservar';
    private const string ROUTE_PASO2 = '/reservar/paso-2';
    private const string ROUTE_PASO3 = '/reservar/paso-3';

    private CartServiceInterface $cartService;
    private ReservationServiceInterface $reservationService;
    private ReservationRepositoryInterface $reservationRepo;
    private ClimaContextoServiceInterface $climaService;
    private FestivosJaponesesServiceInterface $festivosService;
    private AvailabilityServiceInterface $availability;
    private ResponseFactory $response;

    public function __construct(
        ?CartServiceInterface $cartService = null,
        ?ReservationServiceInterface $reservationService = null,
        ?ReservationRepositoryInterface $reservationRepo = null,
        ?ClimaContextoServiceInterface $climaService = null,
        ?FestivosJaponesesServiceInterface $festivosService = null,
        ?AvailabilityServiceInterface $availability = null,
        ?ResponseFactory $response = null
    ) {
        $this->cartService = $cartService ?? Container::make(CartServiceInterface::class);
        $this->reservationService = $reservationService ?? Container::make(ReservationServiceInterface::class);
        $this->reservationRepo = $reservationRepo ?? Container::make(ReservationRepositoryInterface::class);
        $this->climaService = $climaService ?? Container::make(ClimaContextoServiceInterface::class);
        $this->festivosService = $festivosService ?? Container::make(FestivosJaponesesServiceInterface::class);
        $this->availability = $availability ?? Container::make(AvailabilityServiceInterface::class);
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /reservas
     * @throws JsonException
     * @throws DateMalformedStringException
     */
    public function index(): ?ResponseInterface
    {
        if (!Session::isAuthenticated()) {
            View::render('shared/reservas/guest', ['titulo' => 'Reservas'], ['reservas.css']);

            return null;
        }

        $cart = $this->cartService->get();
        $cartDetails = $this->getCartDetails($cart);

        View::render('shared/reservas/index', [
            'titulo' => 'Mis Reservas',
            'cart' => $cart,
            'cartDetails' => $cartDetails,
            'flash' => Flash::consume(),
            'clima' => $this->climaService->obtenerClimaActual(),
            'festivos' => $this->festivosService->obtenerFestivosDelAnio(),
            'cafes' => $this->availability->getAvailableCafesForReservation(),
            'passes' => $this->availability->getAvailablePassesForReservation(),
        ], ['reservas.css']);

        return null;
    }

    /**
     * GET /reservas/{id}/confirmacion
     */
    public function confirmation(ServerRequestInterface $request): ?ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $userId = Session::userId();

        if (!$userId || $id <= 0) {
            Flash::error('Reserva no encontrada.');

            return $this->response->redirect('/reservas');
        }

        $reservation = $this->reservationRepo->findByIdAndUser($id, $userId);

        if (!$reservation) {
            Flash::error('Reserva no encontrada.');

            return $this->response->redirect('/reservas');
        }

        View::render('shared/reservas/confirmation', [
            'titulo' => 'Confirmación de Reserva',
            'reservation' => $reservation,
        ], ['reservas.css']);

        return null;
    }

    /**
     * GET /reservas/mis-reservas
     */
    public function userReservations(): ?ResponseInterface
    {
        $userId = Session::userId();

        if (!$userId) {
            Flash::error('Debes iniciar sesión para ver tus reservas.');

            return $this->response->redirect(self::ROUTE_LOGIN);
        }

        View::render('shared/reservas/lista', [
            'titulo' => 'Mis Reservas',
            'reservations' => $this->reservationService->getByUser($userId),
        ], ['reservas.css']);

        return null;
    }

    /**
     * POST /reservar/paso-1
     */
    public function procesarPaso1(ServerRequestInterface $request): ResponseInterface
    {
        if ($r = $this->requireAuthRedirect()) {
            return $r;
        }

        Csrf::verify($request);

        $body = $request->getParsedBody();
        $cafeId = (int) ($body['cafe_id'] ?? 0);
        $passId = (int) ($body['pass_product_id'] ?? 0);
        $guests = (int) ($body['guests'] ?? 0);

        if ($cafeId <= 0 || $passId <= 0 || $guests < 1 || $guests > 6) {
            Flash::error('Selecciona un café, un pase y el número de personas.');

            return $this->response->redirect(self::ROUTE_RESERVAR);
        }

        $wizard = $this->buildWizardStep1($cafeId, $passId, $guests);
        if ($wizard !== null) {
            Session::set(self::WIZARD_KEY, $wizard);
        } else {
            Flash::error('La selección no está disponible.');
        }

        return $this->response->redirect($wizard !== null ? self::ROUTE_PASO2 : self::ROUTE_RESERVAR);
    }

    /**
     * GET /reservar/paso-2
     */
    public function paso2(): ?ResponseInterface
    {
        if ($r = $this->requireAuthRedirect()) {
            return $r;
        }

        $wizard = Session::get(self::WIZARD_KEY, []);

        if (empty($wizard['cafe_id']) || empty($wizard['pass_product_id'])) {
            Flash::error('Completa el paso 1 primero.');

            return $this->response->redirect(self::ROUTE_RESERVAR);
        }

        View::render('shared/reservas/paso-2', [
            'titulo' => 'Reserva — Fecha y Hora',
            'wizard' => $wizard,
            'festivos' => $this->festivosService->obtenerFestivosDelAnio(),
        ], ['reservas.css']);

        return null;
    }

    /**
     * POST /reservar/paso-2
     */
    public function procesarPaso2(ServerRequestInterface $request): ResponseInterface
    {
        if ($r = $this->requireAuthRedirect()) {
            return $r;
        }

        Csrf::verify($request);

        [$wizard, $flashMsg, $redirectTo] = $this->resolvePaso2Data($request);

        if ($flashMsg !== null) {
            Flash::error($flashMsg);

            return $this->response->redirect($redirectTo);
        }

        Session::set(self::WIZARD_KEY, $wizard);

        return $this->response->redirect(self::ROUTE_PASO3);
    }

    /**
     * GET /reservar/paso-3
     */
    public function paso3(): ?ResponseInterface
    {
        if ($r = $this->requireAuthRedirect()) {
            return $r;
        }

        $wizard = Session::get(self::WIZARD_KEY, []);

        if (empty($wizard['cafe_id']) || empty($wizard['fecha']) || empty($wizard['hora'])) {
            Flash::error('Completa los pasos anteriores primero.');

            return $this->response->redirect(self::ROUTE_RESERVAR);
        }

        $cart = $this->cartService->get();
        $cartTotal = (float) ($cart['totalPrice'] ?? 0);
        $passTotal = (float) ($wizard['pass_price'] ?? 0) * (int) ($wizard['guests'] ?? 1);

        View::render('shared/reservas/paso-3', [
            'titulo' => 'Reserva — Confirmar',
            'wizard' => $wizard,
            'cartDetails' => $this->getCartDetails($cart),
            'passTotal' => $passTotal,
            'grandTotal' => $passTotal + $cartTotal,
        ], ['reservas.css']);

        return null;
    }

    /**
     * POST /reservar
     */
    public function procesarReserva(ServerRequestInterface $request): ResponseInterface
    {
        if ($r = $this->requireAuthRedirect()) {
            return $r;
        }

        Csrf::verify($request);

        [$data, $errorMsg] = $this->resolveWizardData();

        if ($errorMsg !== null) {
            Flash::error($errorMsg);

            return $this->response->redirect(self::ROUTE_RESERVAR);
        }

        return $this->executeReservation($data);
    }

    private function requireAuthRedirect(): ?ResponseInterface
    {
        if (Session::isAuthenticated()) {
            return null;
        }

        return $this->response->redirect(self::ROUTE_LOGIN);
    }

    private function buildWizardStep1(int $cafeId, int $passId, int $guests): ?array
    {
        $cafes = $this->availability->getAvailableCafesForReservation();
        $passes = $this->availability->getAvailablePassesForReservation();

        $cafe = null;
        foreach ($cafes as $c) {
            if ((int) ($c['id'] ?? 0) === $cafeId) {
                $cafe = $c;
                break;
            }
        }

        $pass = null;
        foreach ($passes as $p) {
            if ((int) ($p['id'] ?? 0) === $passId) {
                $pass = $p;
                break;
            }
        }

        if ($cafe === null || $pass === null) {
            return null;
        }

        return [
            'cafe_id' => $cafeId,
            'cafe_name' => (string) ($cafe['name'] ?? ''),
            'pass_product_id' => $passId,
            'pass_name' => (string) ($pass['name'] ?? ''),
            'pass_price' => (float) ($pass['price'] ?? 0),
            'pass_duration' => (int) ($pass['duration_minutes'] ?? 0),
            'guests' => $guests,
        ];
    }

    /**
     * @return array{0: array<string,mixed>, 1: string|null, 2: string}
     */
    private function resolvePaso2Data(ServerRequestInterface $request): array
    {
        $wizard = Session::get(self::WIZARD_KEY, []);

        if (empty($wizard['cafe_id'])) {
            return [$wizard, 'Completa el paso 1 primero.', self::ROUTE_RESERVAR];
        }

        $body = $request->getParsedBody();
        $fecha = (string) ($body['fecha'] ?? '');
        $hora = (string) ($body['hora'] ?? '');

        $fieldError = $this->validatePaso2Fields($fecha, $hora);
        if ($fieldError !== null) {
            return [$wizard, $fieldError, self::ROUTE_PASO2];
        }

        $wizard['fecha'] = $fecha;
        $wizard['hora'] = $hora;
        $wizard['comments'] = (string) ($body['comments'] ?? '');

        return [$wizard, null, self::ROUTE_PASO3];
    }

    private function validatePaso2Fields(string $fecha, string $hora): ?string
    {
        if (empty($fecha) || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < \date('Y-m-d')) {
            return 'Selecciona una fecha válida (no en el pasado).';
        }

        if (empty($hora) || !\preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return 'Selecciona un turno.';
        }

        return null;
    }

    /**
     * @return array{0: array<string,mixed>, 1: string|null}
     */
    private function resolveWizardData(): array
    {
        $wizard = Session::get(self::WIZARD_KEY, []);

        if (empty($wizard['cafe_id']) || empty($wizard['fecha']) || empty($wizard['hora'])) {
            return [[], 'Sesión del wizard expirada. Por favor, comienza de nuevo.'];
        }

        return [[
            'user_id' => (int) Session::userId(),
            'cafe_id' => (int) $wizard['cafe_id'],
            'pass_product_id' => (int) $wizard['pass_product_id'],
            'date' => (string) $wizard['fecha'],
            'time' => (string) $wizard['hora'],
            'guests' => (int) $wizard['guests'],
            'comments' => (string) ($wizard['comments'] ?? ''),
        ], null];
    }

    private function executeReservation(array $data): ResponseInterface
    {
        $result = $this->reservationService->create($data, $this->cartService);

        if (!$result->ok) {
            Flash::error($result->error ?? 'No se pudo crear la reserva.');

            return $this->response->redirect(self::ROUTE_PASO3);
        }

        Session::remove(self::WIZARD_KEY);

        return $this->response->redirect('/reservas/confirmacion/' . (int) $result->data);
    }

    private function getCartDetails(array $cart): array
    {
        if (empty($cart['items'])) {
            return [];
        }

        return $this->reservationService->enrichCartItems($cart['items']);
    }
}
