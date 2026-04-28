<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Result;
use App\Domain\DTO\CafeDTO;
use App\Domain\DTO\ProductDTO;
use App\Events\ReservationConfirmedEvent;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Domain\Reservation\ReservationStateMachine;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use DateTimeImmutable;
use Override;
use PDOException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Servicio de Reservas
 *
 * Orquesta la creación de reservas validando reglas de negocio.
 * Usa interfaces Repository para mejor testabilidad (Dependency Inversion Principle).
 */
final class ReservationService implements ReservationServiceInterface
{
    private ReservationRepositoryInterface $reservationRepo;
    private CafeRepositoryInterface $cafeRepo;
    private ProductRepositoryInterface $productRepo;

    // Servicios adicionales
    private InvoicePDFServiceInterface $invoiceService;
    private EmailServiceInterface $emailService;
    private ?EventDispatcherInterface $eventDispatcher;
    private ?UserProfileServiceInterface $userProfileService;

    public function __construct(
        ReservationRepositoryInterface $reservationRepo,
        CafeRepositoryInterface $cafeRepo,
        ProductRepositoryInterface $productRepo,
        InvoicePDFServiceInterface $invoiceService,
        EmailServiceInterface $emailService,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?UserProfileServiceInterface $userProfileService = null
    ) {
        $this->reservationRepo = $reservationRepo;
        $this->cafeRepo = $cafeRepo;
        $this->productRepo = $productRepo;
        $this->invoiceService = $invoiceService;
        $this->emailService = $emailService;
        $this->eventDispatcher = $eventDispatcher;
        $this->userProfileService = $userProfileService;
    }

    // ─────────────────────────────────────────────────────────────
    // Creación de Reservas
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea una nueva reserva con validación completa.
     *
     * @param array{
     *     user_id: int,
     *     cafe_id: int,
     *     pass_product_id: int,
     *     date: string,
     *     time: string,
     *     guests: int,
     *     comments?: string
     * } $data
     * @param CartServiceInterface|null $cart Carrito para añadir items
     * @return Result Result con el ID de la reserva creada (data), o fallo con error
     */
    #[Override]
    public function create(array $data, CartServiceInterface|null $cart = null): Result
    {
        try {
            // Validar datos requeridos
            $this->validateRequired($data);

            $userId = (int) $data['user_id'];
            $cafeId = (int) $data['cafe_id'];
            $passId = (int) $data['pass_product_id'];
            $date = (string) $data['date'];
            $time = (string) $data['time'];
            $guests = (int) $data['guests'];
            $comments = (string) ($data['comments'] ?? '');

            // Validar formatos
            $this->validateFormats($date, $time, $guests);

            // Obtener y validar café
            $cafe = $this->getCafeOrFail($cafeId);

            // Obtener y validar pase
            $pass = $this->getPassOrFail($passId);

            // DEBUG: Log de validación
            Logger::debug('Pass obtenido para validación', [
                'pass_id' => $passId,
                'pass_name' => $pass->name,
                'target_cafe_types' => $pass->target_cafe_types,
                'target_cafe_types_type' => \gettype($pass->target_cafe_types),
                'target_animal_types' => $pass->target_animal_types,
                'is_active' => $pass->is_active,
            ]);

            // Validar compatibilidad pase-café-guests
            $this->validatePassCompatibility($pass, $cafe, $guests);

            // Validar horario
            $this->validateTimeSlot($cafe, $pass, $time);

            // Validar capacidad disponible del café
            if (!$this->cafeRepo->hasAvailableCapacity($cafeId, $date, $time)) {
                return Result::fail('El café no tiene capacidad disponible en este horario', 'cafe_no_capacity');
            }

            // Validar reserva duplicada
            if ($this->reservationRepo->existsForUserAndDateTime($userId, $cafeId, $date, $time)) {
                return Result::fail('Ya tienes una reserva para este café en esta fecha y horario', 'duplicate_reservation');
            }

            // Crear reserva en transacción
            $reservationId = Database::transaction(function () use (
                $userId,
                $cafeId,
                $passId,
                $pass,
                $date,
                $time,
                $guests,
                $comments,
                $cart
            ) {
                // Preparar datos para inserción
                $reservationData = [
                    'user_id' => $userId,
                    'cafe_id' => $cafeId,
                    'pass_product_id' => $passId,
                    'pass_name' => $pass->name,
                    'pass_unit_price' => (int) $pass->price,
                    'pass_duration_minutes' => $pass->duration_minutes ?? 0,
                    'reservation_date' => $date,
                    'reservation_time' => $time . ':00',
                    'guest_count' => $guests,
                    'notes' => $comments !== '' ? $comments : null,
                    'status' => 'confirmed',
                ];

                // Usar repositorio para insertar (todas las validaciones ya se hicieron)
                $reservationId = $this->reservationRepo->create($reservationData);

                // Transferir items del carrito si existe
                if ($cart !== null && !$cart->isEmpty()) {
                    $cart->transferToReservation($reservationId);
                }

                // Generar factura PDF y enviar email de confirmación
                $this->sendReservationConfirmationWithInvoice($reservationId, $userId);

                return $reservationId;
            });

            if ($this->eventDispatcher !== null) {
                try {
                    $profileService = $this->userProfileService ?? \App\Core\Container::make(UserProfileServiceInterface::class);
                    $userProfile = $profileService->getProfile($userId);
                    $this->eventDispatcher->dispatch(new ReservationConfirmedEvent(
                        $reservationId,
                        $userId,
                        (string) ($userProfile['email'] ?? ''),
                        $date,
                        $time,
                        $guests,
                        new DateTimeImmutable(),
                    ));
                } catch (Throwable $e) {
                    Logger::error('[ReservationService] Error dispatching ReservationConfirmedEvent', [
                        'reservation_id' => $reservationId,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            return Result::ok($reservationId);
        } catch (ValidationException | BusinessRuleException $e) {
            $code = $e instanceof BusinessRuleException ? ($e->getRuleCode() ?? 'business_rule_error') : 'validation_error';

            return Result::fail($e->getMessage(), $code);
        } catch (NotFoundException $e) {
            return Result::fail($e->getMessage(), 'not_found');
        } catch (PDOException $e) {
            Logger::error('[ReservationService] DB error in create()', ['exception' => $e->getMessage()]);

            return Result::fail('Error de base de datos', 'db_error');
        }
    }

    /**
     * Cancela una reserva verificando que pertenezca al usuario.
     *     */
    #[Override]
    public function cancel(int $reservationId, int $userId): Result
    {
        // Usando el nuevo repositorio
        $success = $this->reservationRepo->cancel($reservationId, $userId);

        if (!$success) {
            Logger::warning('Failed to cancel reservation', [
                'reservation_id' => $reservationId,
                'user_id' => $userId,
            ]);

            return Result::fail('No se pudo cancelar la reserva');
        }

        return Result::ok(null);
    }

    #[Override]
    public function adminCancel(int $id): Result
    {
        $reservation = $this->reservationRepo->findById($id);

        if ($reservation === null) {
            return Result::fail('Reserva no encontrada', 'not_found');
        }

        if (!ReservationStateMachine::isValidTransition($reservation->status, 'cancelled')) {
            return Result::fail('No se puede cancelar la reserva en su estado actual', 'invalid_transition');
        }

        $success = $this->reservationRepo->updateStatus($id, 'cancelled');

        if (!$success) {
            Logger::warning('[ReservationService] adminCancel failed', ['reservation_id' => $id]);
            return Result::fail('No se pudo cancelar la reserva');
        }

        return Result::ok(null);
    }

    #[Override]
    public function adminConfirm(int $id): Result
    {
        $reservation = $this->reservationRepo->findById($id);

        if ($reservation === null) {
            return Result::fail('Reserva no encontrada', 'not_found');
        }

        if (!ReservationStateMachine::isValidTransition($reservation->status, 'confirmed')) {
            return Result::fail('No se puede confirmar la reserva en su estado actual', 'invalid_transition');
        }

        $success = $this->reservationRepo->updateStatus($id, 'confirmed');

        if (!$success) {
            Logger::warning('[ReservationService] adminConfirm failed', ['reservation_id' => $id]);
            return Result::fail('No se pudo confirmar la reserva');
        }

        return Result::ok(null);
    }

    // ─────────────────────────────────────────────────────────────
    // Consultas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene las reservas de un usuario.
     */
    #[Override]
    public function getByUser(int $userId, ?string $status = null): array
    {
        // Usar siempre el repositorio (ya no hay lógica especial por status)
        return $this->reservationRepo->findByUser($userId, $status);
    }

    /**
     * Obtiene las próximas reservas de un usuario.
     */
    #[Override]
    public function getUpcoming(int $userId, int $limit = 5): array
    {
        return $this->reservationRepo->findUpcomingByUser($userId, $limit);
    }

    /**
     * Enriquece items del carrito con información de productos
     *
     * @param array $cartItems Items del carrito [product_id => quantity]
     * @return array Productos con información completa
     */
    #[Override]
    public function enrichCartItems(array $cartItems): array
    {
        if (empty($cartItems)) {
            return [];
        }

        $ids = \array_map('intval', \array_keys($cartItems));

        return $this->productRepo->findItemsByIds($ids);
    }

    // ─────────────────────────────────────────────────────────────
    // Validaciones Privadas
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws ValidationException
     */
    private function validateRequired(array $data): void
    {
        $required = ['user_id', 'cafe_id', 'pass_product_id', 'date', 'time', 'guests'];

        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw ValidationException::multipleRequired($missing);
        }
    }

    /**
     * @throws ValidationException
     * @throws BusinessRuleException
     */
    private function validateFormats(string $date, string $time, int $guests): void
    {
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::invalidFormat('date', 'YYYY-MM-DD');
        }

        if (!\preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw ValidationException::invalidFormat('time', 'HH:MM');
        }

        if ($guests < 1 || $guests > 10) {
            throw ValidationException::outOfRange('guests', 1, 10);
        }

        // Validar que no sea fecha pasada
        $dateTime = \strtotime("$date $time:00");
        if ($dateTime === false || $dateTime < \time()) {
            throw BusinessRuleException::pastDate();
        }
    }

    /**
     * @throws BusinessRuleException
     * @throws NotFoundException
     */
    private function getCafeOrFail(int $cafeId): \App\Domain\DTO\CafeDTO
    {
        $cafe = $this->cafeRepo->findById($cafeId);

        if (!$cafe) {
            throw NotFoundException::cafe($cafeId);
        }

        if (!$cafe->is_active || !$cafe->has_reservations) {
            throw BusinessRuleException::cafeNotAcceptingReservations();
        }

        return $cafe;
    }

    /**
     * @throws BusinessRuleException
     * @throws NotFoundException
     */
    private function getPassOrFail(int $passId): ProductDTO
    {
        $pass = $this->productRepo->findById($passId);

        if (!$pass) {
            throw NotFoundException::pass($passId);
        }

        if (!$pass->is_active) {
            throw BusinessRuleException::passNotAvailable();
        }

        if ($pass->product_type !== 'pass') {
            throw BusinessRuleException::productNotAvailable();
        }

        if (empty($pass->duration_minutes) || $pass->duration_minutes <= 0) {
            throw new BusinessRuleException(
                'El pase no tiene una duración válida',
                'invalid_pass_duration'
            );
        }

        return $pass;
    }

    /**
     * @throws BusinessRuleException
     */
    private function validatePassCompatibility(ProductDTO $pass, CafeDTO $cafe, int $guests): void
    {
        // Validar número de personas
        $minPax = $pass->min_pax ?? 1;
        $maxPax = $pass->max_pax;

        if ($guests < $minPax) {
            throw BusinessRuleException::minimumGuestsRequired($minPax);
        }

        if ($maxPax !== null && $guests > $maxPax) {
            throw BusinessRuleException::maximumGuestsExceeded($maxPax);
        }

        // Validar tipo de café
        $targetCafeTypes = $this->parseJsonArray($pass->target_cafe_types);
        if (!empty($targetCafeTypes) && !\in_array($cafe->category, $targetCafeTypes, true)) {
            throw new BusinessRuleException(
                'Este pase no está disponible para este tipo de café',
                'pass_incompatible_cafe_type',
                ['cafe_type' => $cafe->category, 'allowed_types' => $targetCafeTypes]
            );
        }

        // Validar tipo de animal
        $targetAnimalTypes = $this->parseJsonArray($pass->target_animal_types);
        if (!empty($targetAnimalTypes) && !\in_array($cafe->animal_type, $targetAnimalTypes, true)) {
            throw new BusinessRuleException(
                'Este pase no está disponible para este tipo de animal',
                'pass_incompatible_animal_type',
                ['animal_type' => $cafe->animal_type, 'allowed_types' => $targetAnimalTypes]
            );
        }
    }

    /**
     * @throws BusinessRuleException
     */
    private function validateTimeSlot(CafeDTO $cafe, ProductDTO $pass, string $time): void
    {
        $openMinutes = $this->timeToMinutes($cafe->opening_time);
        $closeMinutes = $this->timeToMinutes($cafe->closing_time);
        $startMinutes = $this->timeToMinutes($time);
        $duration = $pass->duration_minutes ?? 0;

        // El pase debe empezar dentro del horario
        if ($startMinutes < $openMinutes) {
            throw new BusinessRuleException(
                'El café aún no está abierto a esa hora',
                'cafe_not_open',
                ['opening_time' => $cafe->opening_time, 'requested_time' => $time]
            );
        }

        // El pase debe terminar antes del cierre
        $latestStart = $closeMinutes - $duration;
        if ($startMinutes > $latestStart) {
            throw new BusinessRuleException(
                'No hay tiempo suficiente para este pase antes del cierre',
                'insufficient_time_before_close',
                ['closing_time' => $cafe->closing_time, 'duration_minutes' => $duration]
            );
        }

        // Validar atributos especiales del pase (horarios restringidos)
        $this->validatePassTimeRestrictions($pass, $startMinutes, $duration);
    }

    /**
     * @throws BusinessRuleException
     */
    private function validatePassTimeRestrictions(ProductDTO $pass, int $startMinutes, int $duration): void
    {
        $attributes = $this->parseJsonArray($pass->attributes);

        if (empty($attributes)) {
            return;
        }

        // Hora mínima permitida
        if (isset($attributes['allowed_start'])) {
            $allowedStart = $this->timeToMinutes($attributes['allowed_start']);
            if ($startMinutes < $allowedStart) {
                throw new BusinessRuleException(
                    'Este pase solo está disponible a partir de cierta hora',
                    'pass_time_restriction_start',
                    ['allowed_start' => $attributes['allowed_start']]
                );
            }
        }

        // Hora máxima permitida
        if (isset($attributes['allowed_end'])) {
            $allowedEnd = $this->timeToMinutes($attributes['allowed_end']);
            $latestByWindow = $allowedEnd - $duration;
            if ($startMinutes > $latestByWindow) {
                throw new BusinessRuleException(
                    'Este pase no puede empezar tan tarde',
                    'pass_time_restriction_end',
                    ['allowed_end' => $attributes['allowed_end']]
                );
            }
        }
    }

    private function timeToMinutes(string $time): int
    {
        $parts = \explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $minutes;
    }

    /**
     * Convierte un JSON string a array PHP
     * Maneja casos donde el valor ya es array o null
     *
     * @param string|array|null $json JSON string, array o null
     * @return array Array decodificado o vacío si falla
     */
    private function parseJsonArray(string|array|null $json): array
    {
        if (empty($json)) {
            return [];
        }
        if (\is_array($json)) {
            return $json;
        }

        $decoded = \json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }

    // ─────────────────────────────────────────────────────────────
    // Email y PDF
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera factura PDF y envía email de confirmación de reserva
     *
     * @param int $reservationId
     * @param int $userId
     * @return void
     */
    private function sendReservationConfirmationWithInvoice(int $reservationId, int $userId): void
    {
        try {
            // Obtener detalles completos de la reserva con información del café
            $reservation = $this->reservationRepo->findByIdWithCafeDetails($reservationId);
            if (!$reservation) {
                Logger::warning('Reserva no encontrada para envío de email', ['reservation_id' => $reservationId]);

                return;
            }

            // Obtener usuario
            $profileService = $this->userProfileService ?? \App\Core\Container::make(UserProfileServiceInterface::class);
            $user = $profileService->getProfile($userId);

            // Generar PDF
            $pdfPath = $this->invoiceService->generateReservationInvoice($reservation, $user);

            // Enviar email con PDF adjunto
            $this->emailService->sendReservationConfirmation(
                $user['email'],
                $user['name'],
                $reservation,
                $pdfPath
            );

            Logger::info('Email de confirmación enviado con factura PDF', [
                'reservation_id' => $reservationId,
                'user_id' => $userId,
                'pdf_path' => $pdfPath,
            ]);
        } catch (Throwable $e) {
            // No fallar la reserva si falla el email
            Logger::error('Error al enviar email de confirmación con factura: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine(), [
                'reservation_id' => $reservationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
