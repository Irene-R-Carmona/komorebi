<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Container;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Raw;
use App\Core\Session;
use App\Core\View;
use App\Core\WideEvent;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Transformers\ReservationTransformer;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\CartService;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\ClimaContextoServiceInterface;
use App\Services\FestivosJaponesesService;
use App\Services\ReservationService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Throwable;

/**
 * Controlador de Reservas Compartido
 *
 * Gestiona reservas para todos los roles (usuario, recepción, etc.).
 * Incluye lógica de carrito, validaciones y recomendaciones basadas en clima/festivos.
 */
final class ReservationController
{
    private CartServiceInterface $cartService;
    private ReservationService $reservationService;
    private AvailabilityService $availabilityService;
    private Reservation $reservationModel;
    private ClimaContextoServiceInterface $climaService;
    private FestivosJaponesesService $festivosService;
    private ResponseFactory $response;

    public function __construct(
        ?CartServiceInterface $cartService = null,
        ?ReservationService $reservationService = null,
        ?AvailabilityService $availabilityService = null,
        ?Reservation $reservationModel = null,
        ?ClimaContextoServiceInterface $climaService = null,
        ?FestivosJaponesesService $festivosService = null,
        ?ResponseFactory $response = null
    ) {
        $this->cartService = $cartService ?? new CartService();
        $this->reservationService = $reservationService ?? Container::make(ReservationService::class);
        $this->availabilityService = $availabilityService ?? new AvailabilityService();
        $this->reservationModel = $reservationModel ?? new Reservation();
        $this->climaService = $climaService ?? Container::make(ClimaContextoServiceInterface::class);
        $this->festivosService = $festivosService ?? new FestivosJaponesesService();
        $this->response = $response ?? new ResponseFactory();
    }

    /**
     * GET /reservas
     * Muestra la página principal de reservas
     * @throws JsonException
     * @throws \DateMalformedStringException
     */
    public function index(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!Session::isAuthenticated()) {
            View::render('shared/reservas/guest', [
                'titulo' => 'Reservas',
            ], ['reservas.css']);

            return null;
        }

        $userId = Session::userId();

        $result = $this->reservationModel->findByUser($userId);
        $misReservas = new ReservationTransformer()->collection($result['data'] ?? []);

        $cafes = $this->availabilityService->getAvailableCafesForReservation();
        $cafesById = $this->availabilityService->getAvailableCafesById();

        $passes = $this->availabilityService->getAvailablePassesForReservation();

        $cart = $this->cartService->get();
        $cartDetails = $this->getCartDetails($cart);

        $clima = $this->climaService->obtenerClimaActual();
        $festivosDelMes = $this->festivosService->obtenerFestivosDelAnio();

        View::render('shared/reservas/index', [
            'titulo' => 'Mis Reservas',
            'reservas' => $misReservas,
            'cafes' => $cafes,
            'cafesById' => $cafesById,
            'cafesJson' => Raw::json($cafes),
            'passes' => $passes,
            'passesJson' => Raw::json($passes),
            'cart' => $cart,
            'cartDetails' => $cartDetails,
            'flash' => Flash::consume(),
            'clima' => $clima,
            'festivos' => $festivosDelMes,
        ], ['reservas.css']);

        return null;
    }

    /**
     * POST /reservas/crear
     * Crea una nueva reserva desde el carrito
     *
     * @throws Throwable
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        if (!Session::isAuthenticated()) {
            throw ValidationException::withMessage('Debes iniciar sesión para hacer una reserva.', 401);
        }

        $userId = Session::userId();

        $body = (array) $request->getParsedBody();
        $cafeId = isset($body['cafe_id']) ? \filter_var($body['cafe_id'], FILTER_VALIDATE_INT) : false;
        $passProductId = isset($body['pass_product_id']) ? \filter_var($body['pass_product_id'], FILTER_VALIDATE_INT) : false;
        $fecha = \trim((string) ($body['fecha'] ?? ''));
        $hora = \trim((string) ($body['hora'] ?? ''));
        $personas = isset($body['personas']) ? \filter_var($body['personas'], FILTER_VALIDATE_INT) : false;
        $comentarios = \trim(\strip_tags((string) ($body['comentarios'] ?? '')));

        $errors = [];
        if (!$cafeId) {
            $errors['cafe_id'] = 'Debes seleccionar un café.';
        }
        if (!$passProductId) {
            $errors['pass_product_id'] = 'Debes seleccionar un pase.';
        }
        if (!$fecha || !$hora) {
            $errors['datetime'] = 'Fecha y hora son requeridas.';
        }
        if (!$personas || $personas < 1) {
            $errors['personas'] = 'Número de personas inválido.';
        }

        if (!empty($errors)) {
            throw ValidationException::fromArray($errors);
        }

        Logger::debug('Validaciones OK - Llamando a ReservationService::create()', [
            'cafe_id' => $cafeId,
            'pass_id' => $passProductId,
            'fecha' => $fecha,
            'hora' => $hora,
            'personas' => $personas,
        ]);

        WideEvent::setSection('reservation', [
            'cafe_id' => $cafeId,
            'pass_product_id' => $passProductId,
            'date' => $fecha,
            'time' => $hora,
            'guests' => $personas,
        ]);

        $result = $this->reservationService->create([
            'user_id' => $userId,
            'cafe_id' => $cafeId,
            'pass_product_id' => $passProductId,
            'date' => $fecha,
            'time' => $hora,
            'guests' => $personas,
            'comments' => $comentarios,
        ], $this->cartService);

        if (!$result->ok) {
            Flash::error($result->getMessage());

            return $this->response->redirect('/reservas');
        }

        $this->cartService->clear();

        Flash::success('¡Reserva confirmada! Te esperamos.');

        return $this->response->redirect('/reservas');
    }

    /**
     * POST /reservas/cancelar
     * Cancela una reserva existente
     * @throws JsonException
     * @throws NotFoundException
     * @throws ValidationException
     * @throws RandomException
     */
    public function cancel(ServerRequestInterface $request): ResponseInterface
    {
        if (!Session::isAuthenticated()) {
            throw ValidationException::withMessage('Debes iniciar sesión.', 401);
        }

        $userId = Session::userId();
        $body = (array) $request->getParsedBody();
        $reservationId = isset($body['id']) ? \filter_var($body['id'], FILTER_VALIDATE_INT) : false;

        if (!$reservationId || $reservationId <= 0) {
            throw ValidationException::withMessage('Reserva inválida');
        }

        $reservation = $this->reservationModel->findById($reservationId);
        if (!$reservation) {
            throw NotFoundException::forResource('Reserva', $reservationId);
        }

        if ($reservation['user_id'] !== $userId) {
            throw ValidationException::withMessage('No puedes cancelar esta reserva');
        }

        $cancelableStates = ['pending', 'confirmed', 'active'];
        if (!\in_array($reservation['status'], $cancelableStates, true)) {
            throw ValidationException::withMessage('Esta reserva no puede ser cancelada (estado: ' . $reservation['status'] . ')');
        }

        try {
            $this->reservationService->cancel($reservationId, $userId);
            Flash::success('Reserva cancelada correctamente. Se procesará el reembolso en 3-5 días hábiles.');
        } catch (\RuntimeException $e) {
            Flash::error($e->getMessage());
        }

        return $this->response->redirect('/reservas');
    }

    /**
     * GET /reservas/{id}/confirmacion
     * Muestra la página de confirmación de una reserva del usuario autenticado.
     */
    public function confirmation(ServerRequestInterface $request): ?ResponseInterface
    {
        $id = (int) $request->getAttribute('id');
        $userId = Session::userId();

        if (!$userId || $id <= 0) {
            Flash::error('Reserva no encontrada.');

            return $this->response->redirect('/reservas');
        }

        $reservation = $this->reservationModel->findByIdAndUser($id, $userId);

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
     * Lista las reservas del usuario autenticado.
     */
    public function userReservations(ServerRequestInterface $request): ?ResponseInterface
    {
        $userId = Session::userId();

        if (!$userId) {
            Flash::error('Debes iniciar sesión para ver tus reservas.');

            return $this->response->redirect('/login');
        }

        $reservations = $this->reservationService->getByUser($userId);

        View::render('shared/reservas/lista', [
            'titulo' => 'Mis Reservas',
            'reservations' => $reservations,
        ], ['reservas.css']);

        return null;
    }

    /**
     * Obtiene detalles del carrito para la vista
     *
     * @param array $cart Datos del carrito
     * @return array Detalles formateados
     */
    private function getCartDetails(array $cart): array
    {
        if (empty($cart['items'])) {
            return [];
        }

        return $this->reservationService->enrichCartItems($cart['items']);
    }
}
