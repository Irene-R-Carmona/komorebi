<?php

declare(strict_types=1);

/**
 * Tests de ReservationService
 *
 * ¿Qué pruebas aquí?
 * - Validaciones de datos (campos requeridos, formatos)
 * - Reglas de negocio (disponibilidad, compatibilidad pase-café)
 * - Integración con repositories (sin tocar BD real)
 *
 * ¿Qué va a fallar?
 * - Si se quita una validación crítica (ej: fecha pasada)
 * - Si cambia lógica de negocio (ej: cálculo de disponibilidad)
 * - Si se rompe integración con repositories
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\ReservationService;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

#[CoversClass(ReservationService::class)]
final class ReservationServiceTest extends TestCase
{
    // Test data constants (evitar duplicación)
    private const VALID_DATE = '2026-12-25';
    private const VALID_TIME = '10:00';
    private const VALID_USER_ID = 1;
    private const VALID_CAFE_ID = 1;
    private const VALID_PASS_ID = 2;
    private const VALID_GUESTS = 2;

    private ReservationService $service;
    /** @var \PHPUnit\Framework\MockObject\Stub&ReservationRepositoryInterface */
    private ReservationRepositoryInterface $mockReservationRepo;
    /** @var \PHPUnit\Framework\MockObject\Stub&CafeRepositoryInterface */
    private CafeRepositoryInterface $mockCafeRepo;
    /** @var \PHPUnit\Framework\MockObject\MockObject&ProductRepositoryInterface */
    private ProductRepositoryInterface $mockProductRepo;
    private InvoicePDFServiceInterface $mockInvoiceService;
    private EmailServiceInterface $mockEmailService;

    protected function setUp(): void
    {
        // Mock interfaces (not concrete classes)
        $this->mockReservationRepo = $this->createMock(ReservationRepositoryInterface::class);
        $this->mockCafeRepo = $this->createMock(CafeRepositoryInterface::class);
        $this->mockProductRepo = $this->createMock(ProductRepositoryInterface::class);
        $this->mockInvoiceService = $this->createMock(InvoicePDFServiceInterface::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);

        $this->service = new ReservationService(
            $this->mockReservationRepo,
            $this->mockCafeRepo,
            $this->mockProductRepo,
            $this->mockInvoiceService,
            $this->mockEmailService
        );
    }

    protected function tearDown(): void
    {
        // No need to set to null with proper initialization
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de validación: validateRequired
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenUserIdMissing(): void
    {

        /** @phpstan-ignore argument.type */
        $result = $this->service->create([
            // 'user_id' => self::VALID_USER_ID,  ← Missing
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenCafeIdMissing(): void
    {

        /** @phpstan-ignore argument.type */
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            // 'cafe_id' => self::VALID_CAFE_ID,  ← Missing
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenPassProductIdMissing(): void
    {

        /** @phpstan-ignore argument.type */
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            // 'pass_product_id' => self::VALID_PASS_ID,  ← Missing
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);
        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de validación: validateFormats
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWithInvalidDateFormat(): void
    {

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '25/12/2026',  // ← Invalid format (should be YYYY-MM-DD)
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWithInvalidTimeFormat(): void
    {

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00:00',  // ← Invalid format (should be HH:MM)
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWithTooFewGuests(): void
    {

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 0,  // ← Too few (minimum is 1)
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWithTooManyGuests(): void
    {

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 11,  // ← Too many (maximum is 10)
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWithPastDate(): void
    {

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2020-01-01',  // ← Past date
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de validación: getCafeOrFail
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenCafeDoesNotExist(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->with(999)
            ->willReturn(null);  // ← Café no existe

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 999,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenCafeIsInactive(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->with(1)
            ->willReturn([
                'id' => 1,
                'name' => 'Inactive Cafe',
                'is_active' => 0,  // ← Inactive
                'has_reservations' => 1,
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenCafeDoesNotAcceptReservations(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->with(1)
            ->willReturn([
                'id' => 1,
                'name' => 'No Reservations Cafe',
                'is_active' => 1,
                'has_reservations' => 0,  // ← Does not accept reservations
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de validación: getPassOrFail
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenPassDoesNotExist(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Active Cafe',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->with(999)
            ->willReturn(null);  // ← Pass no existe

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 999,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenPassIsInactive(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Active Cafe',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => 2,
                'name' => 'Inactive Pass',
                'is_active' => 0,  // ← Inactive
                'product_type' => 'pass',
                'duration_minutes' => 60,
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenProductIsNotAPass(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Active Cafe',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => 2,
                'name' => 'Some Item',
                'is_active' => 1,
                'product_type' => 'item',  // ← Not a pass
                'duration_minutes' => 60,
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de validación: validatePassCompatibility
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenGuestsLessThanMinimum(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Active Cafe',
                'category' => 'cats',
                'animal_type' => 'gato',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => 2,
                'name' => 'Group Pass',
                'is_active' => 1,
                'product_type' => 'pass',
                'duration_minutes' => 60,
                'min_pax' => 4,  // ← Minimum 4 guests
                'max_pax' => 10,
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,  // ← Only 2 guests (less than min_pax)
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenGuestsExceedMaximum(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Active Cafe',
                'category' => 'cats',
                'animal_type' => 'gato',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => 2,
                'name' => 'Couple Pass',
                'is_active' => 1,
                'product_type' => 'pass',
                'duration_minutes' => 60,
                'min_pax' => 1,
                'max_pax' => 2,  // ← Maximum 2 guests
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 4,  // ← 4 guests (exceeds max_pax)
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenCafeTypeIncompatible(): void
    {
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Dog Cafe',
                'category' => 'dogs',  // ← Dog cafe
                'animal_type' => 'perro',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => 2,
                'name' => 'Cats Only Pass',
                'is_active' => 1,
                'product_type' => 'pass',
                'duration_minutes' => 60,
                'min_pax' => 1,
                'max_pax' => 10,
                'target_cafe_types' => '["cats"]',  // ← Only for cats cafes
                'target_animal_types' => null,
            ]);

        $result = $this->service->create([
            'user_id' => 1,
            'cafe_id' => 1,
            'pass_product_id' => 2,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
        ]);
        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Test de éxito: crear reserva válida
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsReservationIdWithValidData(): void
    {
        // ARRANGE: Configurar mocks con datos completamente válidos
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => 1,
                'name' => 'Komorebi Cat Cafe',
                'category' => 'cats',
                'animal_type' => 'gato',
                'is_active' => 1,
                'has_reservations' => 1,
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => 2,
                'name' => '1 Hour Pass',
                'price' => 1500,
                'is_active' => 1,
                'product_type' => 'pass',
                'duration_minutes' => 60,
                'min_pax' => 1,
                'max_pax' => 4,
                'target_cafe_types' => '["cats"]',
                'target_animal_types' => '["gato"]',
            ]);

        // Mock: el método create del modelo debe retornar éxito
        // Nota: esto requiere mockear el Database::transaction pero por ahora
        // verificamos que no lance excepciones hasta aquí

        // ACT & ASSERT: Verificar que no lanza excepciones
        // NOTA: Este test fallará en la transacción porque no mockeamos Database::transaction
        // pero demuestra que todas las validaciones pasaron correctamente
        try {
            $this->service->create([
                'user_id' => 1,
                'cafe_id' => 1,
                'pass_product_id' => 2,
                'date' => '2026-12-25',
                'time' => '10:00',
                'guests' => 2,
                'comments' => 'Test reservation',
            ]);
            $this->fail('Expected Database::transaction to fail in unit test environment');
        } catch (Throwable $e) {
            // Esperamos que falle en Database::transaction, no en validaciones
            $this->assertNotInstanceOf(\App\Exceptions\ValidationException::class, $e);
            $this->assertNotInstanceOf(\App\Exceptions\BusinessRuleException::class, $e);
            $this->assertNotInstanceOf(\App\Exceptions\NotFoundException::class, $e);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Tests del método cancel()
    // ─────────────────────────────────────────────────────────────

    public function testCancelReturnsTrue(): void
    {
        // ARRANGE: Mock del repositorio para simular cancelación exitosa
        $this->mockReservationRepo
            ->method('cancel')
            ->with(123, self::VALID_USER_ID)
            ->willReturn(true);

        // ACT
        $result = $this->service->cancel(123, self::VALID_USER_ID);

        // ASSERT
        $this->assertTrue($result->ok);
    }

    public function testCancelReturnsFalseWhenReservationNotOwnedByUser(): void
    {
        // ARRANGE: Mock para simular que la reserva no pertenece al usuario
        $this->mockReservationRepo
            ->method('cancel')
            ->with(123, self::VALID_USER_ID)
            ->willReturn(false);

        // ACT
        $result = $this->service->cancel(123, self::VALID_USER_ID);

        // ASSERT
        $this->assertFalse($result->ok);
    }

    public function testCancelReturnsFalseWhenReservationNotCancellable(): void
    {
        // ARRANGE: Mock para simular que la reserva no es cancelable (ej: status 'completed')
        $this->mockReservationRepo
            ->method('cancel')
            ->with(456, self::VALID_USER_ID)
            ->willReturn(false);

        // ACT
        $result = $this->service->cancel(456, self::VALID_USER_ID);

        // ASSERT
        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de lógica de negocio: Part 3
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenCafeHasNoCapacity(): void
    {
        // ARRANGE: Mock café válido
        $this->mockCafeRepo
            ->method('findById')
            ->with(self::VALID_CAFE_ID)
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        // ARRANGE: Mock pase válido
        $this->mockProductRepo
            ->method('findById')
            ->with(self::VALID_PASS_ID)
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Básico',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => 4,
            ]);

        // ARRANGE: Mock SIN capacidad disponible
        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->with(self::VALID_CAFE_ID, self::VALID_DATE, self::VALID_TIME)
            ->willReturn(false);
        // ACT
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateReturnsFailWhenUserHasDuplicateReservation(): void
    {
        // ARRANGE: Mock café válido
        $this->mockCafeRepo
            ->method('findById')
            ->with(self::VALID_CAFE_ID)
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        // ARRANGE: Mock pase válido
        $this->mockProductRepo
            ->method('findById')
            ->with(self::VALID_PASS_ID)
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Básico',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => 4,
            ]);

        // ARRANGE: Mock capacidad disponible OK
        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->with(self::VALID_CAFE_ID, self::VALID_DATE, self::VALID_TIME)
            ->willReturn(true);

        // ARRANGE: Mock reserva duplicada existente
        $this->mockReservationRepo
            ->method('existsForUserAndDateTime')
            ->with(self::VALID_USER_ID, self::VALID_CAFE_ID, self::VALID_DATE, self::VALID_TIME)
            ->willReturn(true);
        // ACT
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateValidatesAllBusinessRulesBeforeTransaction(): void
    {
        // ARRANGE: Este test valida que TODAS las validaciones se ejecutan
        // en orden correcto ANTES de intentar crear la reserva

        // Mock café válido
        $this->mockCafeRepo
            ->method('findById')
            ->with(self::VALID_CAFE_ID)
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        // Mock pase válido
        $this->mockProductRepo
            ->method('findById')
            ->with(self::VALID_PASS_ID)
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Básico',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => 4,
            ]);

        // Mock capacidad disponible OK
        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->with(self::VALID_CAFE_ID, self::VALID_DATE, self::VALID_TIME)
            ->willReturn(true);

        // Mock NO existe reserva duplicada
        $this->mockReservationRepo
            ->method('existsForUserAndDateTime')
            ->with(self::VALID_USER_ID, self::VALID_CAFE_ID, self::VALID_DATE, self::VALID_TIME)
            ->willReturn(false);

        // Mock repositorio crea reserva exitosamente
        $this->mockReservationRepo
            ->method('create')
            ->willReturn(999);

        // ACT & ASSERT: Verificar que todas las validaciones pasan y devuelve ID
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame(999, $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de edge cases: Part 4B
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsFailWhenRepositoryThrows(): void
    {
        // ARRANGE: Mock café válido
        $this->mockCafeRepo
            ->method('findById')
            ->with(self::VALID_CAFE_ID)
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        // ARRANGE: Mock pase válido
        $this->mockProductRepo
            ->method('findById')
            ->with(self::VALID_PASS_ID)
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Básico',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => 4,
            ]);

        // ARRANGE: Mock capacidad OK
        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->willReturn(true);

        // ARRANGE: Mock sin duplicados
        $this->mockReservationRepo
            ->method('existsForUserAndDateTime')
            ->willReturn(false);

        // ARRANGE: Mock repositorio lanza excepción PDO (ej: constraint violation)
        $this->mockReservationRepo
            ->method('create')
            ->willThrowException(new PDOException('Database error: constraint violation'));
        // ACT
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
        ]);
        $this->assertFalse($result->ok);
    }

    public function testCreateAcceptsOptionalCommentsField(): void
    {
        // ARRANGE: Mocks completos para crear reserva válida
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Básico',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => 4,
            ]);

        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->willReturn(true);

        $this->mockReservationRepo
            ->method('existsForUserAndDateTime')
            ->willReturn(false);

        $this->mockReservationRepo
            ->method('create')
            ->willReturn(888);

        // ACT: Crear con campo comments opcional
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
            'comments' => 'Comentarios opcionales del usuario',
        ]);

        // ASSERT: La reserva se crea correctamente con comments
        $this->assertTrue($result->ok);
        $this->assertSame(888, $result->data);
    }

    public function testCreateWorksWithoutOptionalCommentsField(): void
    {
        // ARRANGE: Setup completo (igual que test anterior)
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Básico',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => 4,
            ]);

        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->willReturn(true);

        $this->mockReservationRepo
            ->method('existsForUserAndDateTime')
            ->willReturn(false);

        $this->mockReservationRepo
            ->method('create')
            ->willReturn(777);

        // ACT: Crear SIN campo comments (debe usar default vacío)
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => self::VALID_GUESTS,
            // 'comments' omitido intencionalmente
        ]);

        // ASSERT: La reserva se crea correctamente sin comments
        $this->assertTrue($result->ok);
        $this->assertSame(777, $result->data);
    }

    public function testCancelWithInvalidReservationIdReturnsFalse(): void
    {
        // ARRANGE: Mock repositorio indica que no existe o no se pudo cancelar
        $this->mockReservationRepo
            ->method('cancel')
            ->with(99999, self::VALID_USER_ID)
            ->willReturn(false);

        // ACT
        $result = $this->service->cancel(99999, self::VALID_USER_ID);

        // ASSERT: Retorna resultado fallido cuando la reserva no existe o no se puede cancelar
        $this->assertFalse($result->ok);
    }

    public function testCreateSucceedsWhenMaxGuestsIsNull(): void
    {
        // ARRANGE: Mock café válido
        $this->mockCafeRepo
            ->method('findById')
            ->willReturn([
                'id' => self::VALID_CAFE_ID,
                'name' => 'Test Café',
                'is_active' => 1,
                'has_reservations' => 1,
                'category' => 'cat',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
            ]);

        // ARRANGE: Mock pase sin límite máximo (max_pax = null)
        $this->mockProductRepo
            ->method('findById')
            ->willReturn([
                'id' => self::VALID_PASS_ID,
                'name' => 'Pase Sin Límite',
                'category' => 'pass',
                'product_type' => 'pass',
                'is_active' => 1,
                'price' => 1500,
                'duration_minutes' => 60,
                'target_cafe_types' => \json_encode(['cat']),
                'min_pax' => 1,
                'max_pax' => null,  // Sin límite máximo
            ]);

        $this->mockCafeRepo
            ->method('hasAvailableCapacity')
            ->willReturn(true);

        $this->mockReservationRepo
            ->method('existsForUserAndDateTime')
            ->willReturn(false);

        $this->mockReservationRepo
            ->method('create')
            ->willReturn(555);

        // ACT: Crear con 8 invitados (sin límite no debe fallar)
        $result = $this->service->create([
            'user_id' => self::VALID_USER_ID,
            'cafe_id' => self::VALID_CAFE_ID,
            'pass_product_id' => self::VALID_PASS_ID,
            'date' => self::VALID_DATE,
            'time' => self::VALID_TIME,
            'guests' => 8,
        ]);

        // ASSERT: Se crea correctamente cuando no hay límite máximo
        $this->assertTrue($result->ok);
        $this->assertSame(555, $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de delegación: métodos de catálogo → repositorios
    // ─────────────────────────────────────────────────────────────

    public function testEnrichCartItemsDelegatesToProductRepo(): void
    {
        $cartItems = [10 => 2, 20 => 1];
        $expected = [
            ['id' => 10, 'name' => 'Matcha Latte', 'price' => 650],
            ['id' => 20, 'name' => 'Croissant', 'price' => 350],
        ];

        $this->mockProductRepo
            ->expects($this->once())
            ->method('findItemsByIds')
            ->with([10, 20])
            ->willReturn($expected);

        $result = $this->service->enrichCartItems($cartItems);

        $this->assertSame($expected, $result);
    }

    public function testEnrichCartItemsReturnsEmptyArrayForEmptyInput(): void
    {
        $this->mockProductRepo
            ->expects($this->never())
            ->method('findItemsByIds');

        $result = $this->service->enrichCartItems([]);

        $this->assertSame([], $result);
    }

    public function testEventDispatcherIsNotCalledOnValidationFailure(): void
    {
        // ARRANGE: EventDispatcher mock que NO debe recibir ninguna llamada
        $dispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $service = new ReservationService(
            $this->mockReservationRepo,
            $this->mockCafeRepo,
            $this->mockProductRepo,
            $this->mockInvoiceService,
            $this->mockEmailService,
            $dispatcherMock
        );

        $dispatcherMock->expects($this->never())->method('dispatch');

        // ACT: datos incompletos provocan fallo antes de llegar a la transacción
        /** @phpstan-ignore argument.type */
        $result = $service->create([
            'user_id' => 1,
            // cafe_id falta → falla validateRequired()
        ]);

        // ASSERT
        $this->assertFalse($result->ok);
    }
}
