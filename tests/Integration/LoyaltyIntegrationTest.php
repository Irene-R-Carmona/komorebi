<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Flujo completo de fidelización: una reserva marcada como completada
 * por el manager genera exactamente un sello en la tarjeta del usuario,
 * y una segunda llamada no duplica el sello (idempotencia por loyalty_awarded).
 *
 * ¿Qué me quieres demostrar?
 * Que ReservationService::managerUpdateStatus() llama a LoyaltyService::addStamp()
 * cuando el estado pasa a 'completed', que el sello queda registrado en BD
 * y que la columna loyalty_awarded impide dobles sellos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la llamada a addStamp en managerUpdateStatus(), si se elimina
 * la columna loyalty_awarded, si cambia la máquina de estados de reservas
 * o si LoyaltyRepository deja de persistir los sellos correctamente.
 */

namespace Tests\Integration;

use App\Domain\Mappers\CafeMapper;
use App\Repositories\CafeRepository;
use App\Repositories\LoyaltyRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReservationRepository;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\EmailService;
use App\Services\InvoicePDFService;
use App\Services\LoyaltyService;
use App\Services\ReservationService;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Support\BaseIntegrationTest;

#[CoversNothing]
final class LoyaltyIntegrationTest extends BaseIntegrationTest
{
    private ReservationService $reservationService;
    private LoyaltyService $loyaltyService;
    private LoyaltyRepository $loyaltyRepo;
    private ReservationRepository $reservationRepo;

    private const int TEST_USER_ID = 99991;
    private const int TEST_CAFE_ID = 99992;
    private const int TEST_PRODUCT_ID = 99993;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->loyaltyRepo = new LoyaltyRepository(self::$db);
        $this->reservationRepo = new ReservationRepository(self::$db);
        $cafeRepo = new CafeRepository(new CafeMapper(), self::$db);
        $productRepo = new ProductRepository(self::$db);
        $invoiceService = new InvoicePDFService();
        $emailService = new EmailService();
        $this->loyaltyService = new LoyaltyService($this->loyaltyRepo);

        /** @var UserProfileServiceInterface&\PHPUnit\Framework\MockObject\Stub $userProfileStub */
        $userProfileStub = $this->createStub(UserProfileServiceInterface::class);
        $userProfileStub->method('getProfile')->willReturn([
            'id' => self::TEST_USER_ID,
            'email' => 'loyalty-integration@komorebi.test',
            'name' => 'Loyalty Integration User',
        ]);

        $this->reservationService = new ReservationService(
            $this->reservationRepo,
            $cafeRepo,
            $productRepo,
            $invoiceService,
            $emailService,
            eventDispatcher: null,
            userProfileService: $userProfileStub,
            loyaltyService: $this->loyaltyService,
        );
    }

    private function seedTestData(): void
    {
        self::$db->exec('
            INSERT IGNORE INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . self::TEST_USER_ID . ",
                UUID(),
                'loyalty-integration@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Loyalty Integration User',
                NOW(),
                1
            )
        ");

        self::$db->exec('
            INSERT IGNORE INTO cafes (
                id, name, slug, location, category, animal_type,
                description, price_per_hour, opening_time, closing_time,
                capacity_max, is_active, has_reservations
            )
            VALUES (
                ' . self::TEST_CAFE_ID . ",
                'Loyalty Test Café',
                'loyalty-test-cafe',
                'Tokyo Loyalty District',
                'lounge',
                'cat',
                'Café para test de fidelización',
                2000,
                '09:00:00',
                '20:00:00',
                50,
                1,
                1
            )
        ");

        self::$db->exec("
            INSERT IGNORE INTO menu_categories (id, name, slug)
            VALUES (99991, 'Loyalty Test Category', 'loyalty-test-category')
        ");

        self::$db->exec('
            INSERT IGNORE INTO products (
                id, category_id, name, slug, product_type, description,
                price, is_active, duration_minutes, min_pax, max_pax,
                target_cafe_types
            )
            VALUES (
                ' . self::TEST_PRODUCT_ID . ",
                99991,
                'Loyalty Test Pass 1H',
                'loyalty-test-pass-1h',
                'pass',
                'Pase de prueba para fidelización',
                1500,
                1,
                60,
                1,
                4,
                '[\"lounge\"]'
            )
        ");
    }

    /**
     * Flujo completo: reserva activa → manager la marca completada → sello añadido
     */
    public function testManagerCompletingReservationAddsLoyaltyStamp(): void
    {
        // ARRANGE — insertar reserva directamente en estado 'active'
        $stmt = self::$db->prepare('
            INSERT INTO reservations (
                user_id, cafe_id, pass_product_id, pass_name, pass_unit_price,
                pass_duration_minutes, reservation_date, reservation_time, guest_count,
                status, payment_status, loyalty_awarded
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            self::TEST_USER_ID,
            self::TEST_CAFE_ID,
            self::TEST_PRODUCT_ID,
            'Loyalty Test Pass 1H',
            1500,
            60,
            '2026-12-28',
            '10:00:00',
            2,
            'active',
            'paid',
            0,
        ]);
        $reservationId = (int) self::$db->lastInsertId();

        // Verificar tarjeta antes (0 sellos si no existe aún)
        $cardBefore = self::$db->query(
            'SELECT visits_count FROM loyalty_cards WHERE user_id = ' . self::TEST_USER_ID
        )->fetch(PDO::FETCH_ASSOC);
        $stampsBefore = $cardBefore ? (int) $cardBefore['visits_count'] : 0;

        // ACT
        $result = $this->reservationService->managerUpdateStatus(
            $reservationId,
            'completed',
            'Visita completada'
        );

        // ASSERT — la actualización fue exitosa
        $this->assertTrue($result->ok, 'managerUpdateStatus debe retornar ok=true');

        // ASSERT — se añadió exactamente 1 sello
        $cardAfter = self::$db->query(
            'SELECT visits_count FROM loyalty_cards WHERE user_id = ' . self::TEST_USER_ID
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($cardAfter, 'Debe existir una loyalty_card para el usuario');
        $this->assertSame(
            $stampsBefore + 1,
            (int) $cardAfter['visits_count'],
            'Debe haberse añadido exactamente 1 sello'
        );

        // ASSERT — la reserva queda marcada como loyalty_awarded = true
        $reservation = self::$db->query(
            'SELECT loyalty_awarded FROM reservations WHERE id = ' . $reservationId
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $reservation['loyalty_awarded'], 'loyalty_awarded debe ser 1 tras completar');
    }

    /**
     * Idempotencia: si loyalty_awarded ya es true, no se añade un segundo sello
     */
    public function testCompletingAlreadyAwardedReservationDoesNotDuplicateStamp(): void
    {
        // ARRANGE — reserva con loyalty_awarded = 1 (ya fue premiada)
        $stmt = self::$db->prepare('
            INSERT INTO reservations (
                user_id, cafe_id, pass_product_id, pass_name, pass_unit_price,
                pass_duration_minutes, reservation_date, reservation_time, guest_count,
                status, payment_status, loyalty_awarded
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            self::TEST_USER_ID,
            self::TEST_CAFE_ID,
            self::TEST_PRODUCT_ID,
            'Loyalty Test Pass 1H',
            1500,
            60,
            '2026-12-29',
            '11:00:00',
            2,
            'active',
            'paid',
            1, // ya fue premiada
        ]);
        $reservationId = (int) self::$db->lastInsertId();

        // Asegurarse de que existe la tarjeta con un sello previo
        $this->loyaltyService->addStamp(self::TEST_USER_ID, 1, null);
        $cardBefore = self::$db->query(
            'SELECT visits_count FROM loyalty_cards WHERE user_id = ' . self::TEST_USER_ID
        )->fetch(PDO::FETCH_ASSOC);
        $stampsBefore = (int) $cardBefore['visits_count'];

        // ACT — manager intenta completar una reserva ya premiada
        $result = $this->reservationService->managerUpdateStatus(
            $reservationId,
            'completed',
            'Visita completada'
        );

        // ASSERT — la operación fue exitosa
        $this->assertTrue($result->ok, 'managerUpdateStatus debe retornar ok=true aunque ya estuviera premiada');

        // ASSERT — no se añadió ningún sello extra
        $cardAfter = self::$db->query(
            'SELECT visits_count FROM loyalty_cards WHERE user_id = ' . self::TEST_USER_ID
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(
            $stampsBefore,
            (int) $cardAfter['visits_count'],
            'No debe añadirse sello extra cuando loyalty_awarded ya era true'
        );
    }
}
