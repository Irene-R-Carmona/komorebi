<?php

/**
 * ¿Qué prueba aquí? Factorías de filas PDO canónicas para tests de repositorios.
 * ¿Qué me quieres demostrar? Centralizar los arrays de fila que devuelve PDO para evitar
 *   la duplicación entre tests y garantizar coherencia con el schema real.
 * ¿Qué va a fallar en este test si se cambia el código? No aplica — es infraestructura de tests.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

/**
 * Factorías estáticas de filas PDO canónicas.
 *
 * Cada método devuelve un array que imita lo que retornaría PDO::fetch()/fetchAll()
 * con PDO::FETCH_ASSOC para la tabla correspondiente.
 *
 * Uso: pasar como argumento a RepositoryTestCase::makePdo():
 *   $pdo = $this->makePdo(fetchAllReturn: [RowFactory::userRow()]);
 *   $pdo = $this->makePdo(fetchReturn: RowFactory::reservationRow(['status' => 'cancelled']));
 *
 * Para sobreescribir un campo: RowFactory::userRow(['email' => 'otro@mail.com'])
 */
final class RowFactory
{
    // ─────────────────────────────────────────────────────────────
    // Usuarios
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function userRow(array $overrides = []): array
    {
        return \array_merge([
            'id'          => 1,
            'uuid'        => 'a1b2c3d4-0000-0000-0000-000000000001',
            'name'        => 'Test User',
            'email'       => 'user@test.com',
            'password'    => '$2y$12$hashhashhash',
            'avatar'      => null,
            'roles'       => 'user',
            'is_active'   => 1,
            'cafe_id'     => null,
            'preferences' => null,
            'created_at'  => '2024-01-01 10:00:00',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Reservas
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function reservationRow(array $overrides = []): array
    {
        return \array_merge([
            'id'             => 1,
            'uuid'           => 'b1b2c3d4-0000-0000-0000-000000000001',
            'cafe_id'           => 1,
            'user_id'           => 1,
            'reservation_date'  => '2025-06-15',
            'reservation_time'  => '11:00:00',
            'guest_count'       => 2,
            'status'         => 'confirmed',
            'time_slot_id'   => null,
            'pass_name'      => null,
            'check_in_at'    => null,
            'check_out_at'   => null,
            'final_amount'   => null,
            'payment_status' => null,
            'payment_method' => null,
            'notes'          => null,
            'created_at'     => '2025-05-01 09:00:00',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Productos
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function productRow(array $overrides = []): array
    {
        return \array_merge([
            'id'                  => 1,
            'name'                => 'Café Matcha',
            'slug'                => 'cafe-matcha',
            'description'         => 'Delicioso matcha latte',
            'price'               => 4.50,
            'category_id'         => 2,
            'category_name'       => 'Bebidas',
            'allergens'           => '[]',
            'is_active'           => 1,
            'image_url'           => null,
            'product_type'        => 'drink',
            'min_pax'             => null,
            'max_pax'             => null,
            'duration_minutes'    => null,
            'attributes'          => null,
            'target_cafe_types'   => null,
            'target_animal_types' => null,
            'stock_quantity'      => null,
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Reseñas
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function reviewRow(array $overrides = []): array
    {
        return \array_merge([
            'id'         => 1,
            'user_id'    => 1,
            'cafe_id'    => 1,
            'cafe_name'  => 'Komorebi Madrid',
            'user_name'  => 'Test User',
            'rating'     => 5,
            'title'      => 'Fantástico',
            'body'       => 'Me encantó la experiencia.',
            'status'     => 'approved',
            'created_at' => '2024-03-10 12:00:00',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Cafeterías
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function cafeRow(array $overrides = []): array
    {
        return \array_merge([
            'id'           => 1,
            'name'         => 'Komorebi Madrid',
            'slug'         => 'komorebi-madrid',
            'description'  => 'Cafetería de animales en Madrid.',
            'address'      => 'Calle Mayor 1, Madrid',
            'phone'        => '+34900000001',
            'email'        => 'madrid@komorebi.es',
            'is_active'    => 1,
            'capacity'     => 20,
            'opening_time' => '09:00:00',
            'closing_time' => '20:00:00',
            'created_at'   => '2024-01-01 00:00:00',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Fidelización
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function loyaltyCardRow(array $overrides = []): array
    {
        return \array_merge([
            'id'                     => 1,
            'user_id'                => 1,
            'stamps'                 => 3,
            'current_tier'           => 'bronze',
            'visits_count'           => 3,
            'total_rewards_redeemed' => 0,
            'last_stamp_at'          => '2025-04-01 10:00:00',
            'created_at'             => '2025-01-01 00:00:00',
            'updated_at'             => '2025-04-01 10:00:00',
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Lista de espera
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function waitlistEntryRow(array $overrides = []): array
    {
        return \array_merge([
            'id'               => 1,
            'token'            => 'wl-token-0001',
            'status'           => 'waiting',
            'position'         => 1,
            'time_slot_id'     => 10,
            'user_id'          => 1,
            'slot_date'        => '2025-06-20',
            'slot_time'        => '11:00:00',
            'cafe_name'        => 'Komorebi Madrid',
            'guest_count'      => 2,
            'contact_email'    => 'user@test.com',
            'expires_at'       => null,
            'special_requests' => null,
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Franjas horarias
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function timeSlotRow(array $overrides = []): array
    {
        return \array_merge([
            'id'             => 10,
            'cafe_id'        => 1,
            'date'           => '2025-06-20',
            'start_time'     => '11:00:00',
            'end_time'       => '12:00:00',
            'max_capacity'   => 10,
            'current_count'  => 3,
            'is_active'      => 1,
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // Animales
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    public static function animalRow(array $overrides = []): array
    {
        return \array_merge([
            'id'             => 1,
            'name'           => 'Mochi',
            'species_type'   => 'cat',
            'breed'          => 'Maine Coon',
            'birth_date'     => '2020-03-15',
            'cafe_id'        => 1,
            'current_status' => 'healthy',
            'is_active'      => 1,
            'created_at'     => '2023-01-01 00:00:00',
        ], $overrides);
    }
}
