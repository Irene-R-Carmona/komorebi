<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests de Integración de WaitlistService
 *
 * Valida operaciones con MySQL real usando transacciones para aislamiento.
 * Estos tests NO usan mocks - ejecutan queries reales contra la BD.
 */

namespace Tests\Integration;

use App\Models\Waitlist;
use App\Repositories\WaitlistRepository;
use App\Services\EmailService;
use App\Services\WaitlistService;
use PDO;
use Tests\Support\BaseIntegrationTest;

final class WaitlistIntegrationTest extends BaseIntegrationTest
{
    private WaitlistService $service;

    // IDs únicos para tests
    private const TEST_USER_ID = 88888;
    private const TEST_USER_ID_2 = 88887;
    private const TEST_USER_ID_3 = 88886;
    private const TEST_CAFE_ID = 88885;
    private const TEST_SLOT_ID = 88884;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->service = new WaitlistService(
            self::$db,
            new EmailService(),
            new WaitlistRepository(self::$db)
        );
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Limpiar datos previos si existen
        self::$db->exec('DELETE FROM waitlist WHERE time_slot_id = ' . self::TEST_SLOT_ID);
        self::$db->exec('DELETE FROM time_slots WHERE id = ' . self::TEST_SLOT_ID);
        self::$db->exec('DELETE FROM cafes WHERE id = ' . self::TEST_CAFE_ID);
        self::$db->exec('DELETE FROM users WHERE id IN (' . self::TEST_USER_ID . ', ' . self::TEST_USER_ID_2 . ', ' . self::TEST_USER_ID_3 . ')');

        // Usuario de prueba 1
        self::$db->exec('
            INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . self::TEST_USER_ID . ",
                UUID(),
                'waitlist-test-1@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Waitlist Test User 1',
                NOW(),
                1
            )
        ");

        // Usuario de prueba 2
        self::$db->exec('
            INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . self::TEST_USER_ID_2 . ",
                UUID(),
                'waitlist-test-2@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Waitlist Test User 2',
                NOW(),
                1
            )
        ");

        // Usuario de prueba 3
        self::$db->exec('
            INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . self::TEST_USER_ID_3 . ",
                UUID(),
                'waitlist-test-3@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Waitlist Test User 3',
                NOW(),
                1
            )
        ");

        // Café de prueba
        self::$db->exec('
            INSERT INTO cafes (
                id, name, slug, location, category, animal_type,
                description, price_per_hour, opening_time, closing_time,
                capacity_max, is_active, has_reservations
            )
            VALUES (
                ' . self::TEST_CAFE_ID . ",
                'Waitlist Test Café',
                'waitlist-test-cafe',
                'Tokyo Test District',
                'lounge',
                'cat',
                'Café de prueba para integration tests de waitlist',
                2000,
                '09:00:00',
                '20:00:00',
                5,
                1,
                1
            )
        ");

        // Time slot de prueba (SIN disponibilidad para permitir waitlist)
        self::$db->exec('
            INSERT INTO time_slots (
                id, cafe_id, slot_date, slot_time,
                total_capacity, available_spots, reserved_spots,
                duration_minutes, is_blocked,
                created_at, updated_at
            )
            VALUES (
                ' . self::TEST_SLOT_ID . ',
                ' . self::TEST_CAFE_ID . ",
                '2026-12-25',
                '14:00',
                5,
                0,
                5,
                60,
                0,
                NOW(),
                NOW()
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Integration Tests
    // ─────────────────────────────────────────────────────────────

    public function testJoinWaitlistInsertsIntoDatabaseWithCorrectPosition(): void
    {
        // ACT: Añadir primer usuario a waitlist
        $result1 = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID, [
            'email' => 'waitlist-test-1@komorebi.test',
            'user_name' => 'User 1',
            'guest_count' => 2,
        ]);

        // ASSERT: Éxito y posición 1
        $this->assertTrue($result1->ok);
        $this->assertSame(1, $result1->data['position'] ?? null);

        // ACT: Añadir segundo usuario
        $result2 = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID_2, [
            'email' => 'waitlist-test-2@komorebi.test',
            'user_name' => 'User 2',
            'guest_count' => 1,
        ]);

        // ASSERT: Posición 2
        $this->assertSame(2, $result2->data['position'] ?? null);

        // Verificar estado en BD
        $stmt = self::$db->query('
            SELECT COUNT(*) FROM waitlist
            WHERE time_slot_id = ' . self::TEST_SLOT_ID . '
        ');
        $count = $stmt->fetchColumn();
        $this->assertSame(2, (int) $count);
    }

    public function testPromoteNextUpdatesStatusInDatabase(): void
    {
        // ARRANGE: Añadir usuario a waitlist
        $result = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID, [
            'email' => 'waitlist-test-1@komorebi.test',
            'user_name' => 'User 1',
            'guest_count' => 2,
        ]);
        $token = $result->data['token'] ?? null;

        // ACT: Promocionar el siguiente en la cola
        $promoteResult = $this->service->promoteNext(self::TEST_SLOT_ID);

        // ASSERT: Promoción exitosa
        $this->assertTrue($promoteResult->ok);
        $this->assertTrue($promoteResult->data['promoted'] ?? false);

        // Verificar estado 'notified' en BD
        $stmt = self::$db->prepare('
            SELECT status FROM waitlist WHERE token = ?
        ');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(Waitlist::STATUS_NOTIFIED, $row['status'] ?? null);
    }

    public function testGetWaitlistStatusRetrievesDataFromDatabase(): void
    {
        // ARRANGE: Añadir usuario a waitlist
        $result = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID, [
            'email' => 'waitlist-test-1@komorebi.test',
            'user_name' => 'User 1',
            'guest_count' => 2,
        ]);
        $token = $result->data['token'] ?? '';

        // ACT: Consultar estado
        $statusResult = $this->service->getWaitlistStatus($token);

        // ASSERT: Datos correctos
        $this->assertTrue($statusResult->ok);
        $this->assertIsArray($statusResult->data);
        $this->assertArrayHasKey('position', $statusResult->data);
        $this->assertSame(1, $statusResult->data['position']);
        $this->assertArrayHasKey('time_slot', $statusResult->data);
    }

    public function testMultipleWaitlistEntriesMaintainCorrectOrdering(): void
    {
        // ARRANGE: Añadir 3 usuarios a la waitlist
        $result1 = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID, [
            'email' => 'waitlist-test-1@komorebi.test',
            'user_name' => 'User 1',
            'guest_count' => 2,
        ]);

        $result2 = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID_2, [
            'email' => 'waitlist-test-2@komorebi.test',
            'user_name' => 'User 2',
            'guest_count' => 1,
        ]);

        $result3 = $this->service->joinWaitlist(self::TEST_SLOT_ID, self::TEST_USER_ID_3, [
            'email' => 'waitlist-test-3@komorebi.test',
            'user_name' => 'User 3',
            'guest_count' => 3,
        ]);

        // ASSERT: Posiciones correctas
        $this->assertSame(1, $result1->data['position'] ?? null);
        $this->assertSame(2, $result2->data['position'] ?? null);
        $this->assertSame(3, $result3->data['position'] ?? null);

        // Verificar orden en BD
        $stmt = self::$db->query('
            SELECT user_id, position FROM waitlist
            WHERE time_slot_id = ' . self::TEST_SLOT_ID . '
            ORDER BY position ASC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $rows);
        $this->assertSame(self::TEST_USER_ID, (int) $rows[0]['user_id']);
        $this->assertSame(self::TEST_USER_ID_2, (int) $rows[1]['user_id']);
        $this->assertSame(self::TEST_USER_ID_3, (int) $rows[2]['user_id']);
    }

    public function testConfirmPromotionFailsWhenTokenExpired(): void
    {
        // ARRANGE: Crear entrada waitlist con token expirado directamente en BD
        $token = \bin2hex(\random_bytes(16));
        $expiredTime = \date('Y-m-d H:i:s', \time() - 3600); // 1 hora atrás

        self::$db->exec('
            INSERT INTO waitlist (
                time_slot_id, user_id, position, token, status,
                contact_email, guest_count, expires_at, response_timeout_minutes
            )
            VALUES (
                ' . self::TEST_SLOT_ID . ',
                ' . self::TEST_USER_ID . ",
                1,
                '{$token}',
                '" . Waitlist::STATUS_NOTIFIED . "',
                'expired@test.com',
                2,
                '{$expiredTime}',
                15
            )
        ");

        // ACT: Intentar confirmar promoción
        $result = $this->service->confirmPromotion($token, [
            'pass_product_id' => 1,
            'pass_name' => 'Pase 1H',
            'pass_unit_price' => 1500,
            'pass_duration_minutes' => 60,
        ]);

        // ASSERT: Debe fallar por expiración
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('expirado', \strtolower($result->error ?? ''));
    }
}
