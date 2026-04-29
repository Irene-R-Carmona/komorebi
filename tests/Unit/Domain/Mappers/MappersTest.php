<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mappers;

use App\Domain\Mappers\AllergenMapper;
use App\Domain\Mappers\AnimalHealthCheckMapper;
use App\Domain\Mappers\AnimalIncidentMapper;
use App\Domain\Mappers\AnimalMapper;
use App\Domain\Mappers\AuditLogMapper;
use App\Domain\Mappers\AuthAuditLogMapper;
use App\Domain\Mappers\CafeMapper;
use App\Domain\Mappers\FavoriteMapper;
use App\Domain\Mappers\LoyaltyCardMapper;
use App\Domain\Mappers\LoyaltyRewardCatalogMapper;
use App\Domain\Mappers\LoyaltyRewardMapper;
use App\Domain\Mappers\MenuCategoryMapper;
use App\Domain\Mappers\PermissionMapper;
use App\Domain\Mappers\ProductMapper;
use App\Domain\Mappers\ReservationItemMapper;
use App\Domain\Mappers\ReservationMapper;
use App\Domain\Mappers\ReviewMapper;
use App\Domain\Mappers\RoleMapper;
use App\Domain\Mappers\SettingMapper;
use App\Domain\Mappers\StaffShiftMapper;
use App\Domain\Mappers\SupervisorAssignmentMapper;
use App\Domain\Mappers\TimeSlotMapper;
use App\Domain\Mappers\TrackerMapper;
use App\Domain\Mappers\UserMapper;
use App\Domain\Mappers\WaitlistMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * Todos los Mappers de dominio que convierten filas PDO en DTOs.
 * ¿Qué me quieres demostrar?
 * Que cada Mapper convierte correctamente tanto filas completas como filas mínimas.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se añaden/eliminan campos obligatorios o se cambia el casting de tipos en toDTO().
 */
#[CoversClass(AllergenMapper::class)]
#[CoversClass(AnimalHealthCheckMapper::class)]
#[CoversClass(AnimalIncidentMapper::class)]
#[CoversClass(AnimalMapper::class)]
#[CoversClass(AuditLogMapper::class)]
#[CoversClass(AuthAuditLogMapper::class)]
#[CoversClass(CafeMapper::class)]
#[CoversClass(FavoriteMapper::class)]
#[CoversClass(LoyaltyCardMapper::class)]
#[CoversClass(LoyaltyRewardCatalogMapper::class)]
#[CoversClass(LoyaltyRewardMapper::class)]
#[CoversClass(MenuCategoryMapper::class)]
#[CoversClass(PermissionMapper::class)]
#[CoversClass(ProductMapper::class)]
#[CoversClass(ReservationItemMapper::class)]
#[CoversClass(ReservationMapper::class)]
#[CoversClass(ReviewMapper::class)]
#[CoversClass(RoleMapper::class)]
#[CoversClass(SettingMapper::class)]
#[CoversClass(StaffShiftMapper::class)]
#[CoversClass(SupervisorAssignmentMapper::class)]
#[CoversClass(TimeSlotMapper::class)]
#[CoversClass(TrackerMapper::class)]
#[CoversClass(UserMapper::class)]
#[CoversClass(WaitlistMapper::class)]
final class MappersTest extends TestCase
{
    // -------------------------------------------------------------------------
    // AllergenMapper
    // -------------------------------------------------------------------------

    public function testAllergenMapperMapsFullRow(): void
    {
        $mapper = new AllergenMapper();
        $dto = $mapper->toDTO([
            'id'            => '3',
            'code'          => 'gluten',
            'name'          => 'Gluten',
            'japanese_name' => 'グルテン',
            'icon_class'    => 'fa-wheat',
            'icon_color'    => '#ff0',
            'severity'      => 'high',
            'description'   => 'Wheat-based allergen',
        ]);

        self::assertSame(3, $dto->id);
        self::assertSame('gluten', $dto->code);
        self::assertSame('Gluten', $dto->name);
        self::assertSame('グルテン', $dto->japanese_name);
        self::assertSame('fa-wheat', $dto->icon_class);
        self::assertSame('#ff0', $dto->icon_color);
        self::assertSame('high', $dto->severity);
        self::assertSame('Wheat-based allergen', $dto->description);
    }

    public function testAllergenMapperMapsMinimalRow(): void
    {
        $mapper = new AllergenMapper();
        $dto = $mapper->toDTO([
            'id'   => '1',
            'code' => 'lactose',
            'name' => 'Lactosa',
        ]);

        self::assertSame(1, $dto->id);
        self::assertSame('medium', $dto->severity);
        self::assertNull($dto->japanese_name);
        self::assertNull($dto->icon_class);
        self::assertNull($dto->description);
    }

    // -------------------------------------------------------------------------
    // AnimalMapper
    // -------------------------------------------------------------------------

    public function testAnimalMapperMapsFullRow(): void
    {
        $mapper = new AnimalMapper();
        $dto = $mapper->toDTO([
            'id'             => '5',
            'cafe_id'        => '2',
            'name'           => 'Mochi',
            'species_type'   => 'cat',
            'description'    => 'Un gato naranja',
            'image_url'      => '/img/mochi.jpg',
            'current_status' => 'active',
        ]);

        self::assertSame(5, $dto->id);
        self::assertSame(2, $dto->cafe_id);
        self::assertSame('Mochi', $dto->name);
        self::assertSame('cat', $dto->species);
        self::assertSame('Un gato naranja', $dto->description);
        self::assertSame('/img/mochi.jpg', $dto->image_url);
        self::assertTrue($dto->is_active);
    }

    public function testAnimalMapperMapsMinimalRow(): void
    {
        $mapper = new AnimalMapper();
        $dto = $mapper->toDTO([
            'id'      => '1',
            'cafe_id' => '1',
            'name'    => 'Kumo',
        ]);

        self::assertFalse($dto->is_active);
        self::assertNull($dto->description);
        self::assertNull($dto->image_url);
        self::assertSame('', $dto->species);
    }

    // -------------------------------------------------------------------------
    // AnimalHealthCheckMapper
    // -------------------------------------------------------------------------

    public function testAnimalHealthCheckMapperMapsFullRow(): void
    {
        $mapper = new AnimalHealthCheckMapper();
        $dto = $mapper->toDTO([
            'id'               => '10',
            'animal_id'        => '5',
            'checked_by'       => '3',
            'check_date'       => '2026-04-01',
            'created_at'       => '2026-04-01 10:00:00',
            'weight_kg'        => '4.5',
            'temperature_c'    => '38.5',
            'appetite'         => 'good',
            'energy_level'     => 'high',
            'coat_condition'   => 'shiny',
            'eyes_clear'       => '1',
            'breathing_normal' => '1',
            'mobility_normal'  => '1',
            'notes'            => 'Todo bien',
            'alerts'           => null,
            'animal_name'      => 'Mochi',
            'species_type'     => 'cat',
            'current_status'   => 'active',
            'keeper_name'      => 'Tanaka',
        ]);

        self::assertSame(10, $dto->id);
        self::assertSame(5, $dto->animal_id);
        self::assertSame(4.5, $dto->weight_kg);
        self::assertSame(38.5, $dto->temperature_c);
        self::assertSame('good', $dto->appetite);
        self::assertTrue($dto->eyes_clear);
        self::assertSame('Mochi', $dto->animal_name);
        self::assertNull($dto->alerts);
    }

    public function testAnimalHealthCheckMapperMapsMinimalRow(): void
    {
        $mapper = new AnimalHealthCheckMapper();
        $dto = $mapper->toDTO([
            'id'               => '1',
            'animal_id'        => '2',
            'checked_by'       => '1',
            'check_date'       => '2026-04-01',
            'created_at'       => '2026-04-01 09:00:00',
            'appetite'         => 'normal',
            'energy_level'     => 'normal',
            'coat_condition'   => 'normal',
            'eyes_clear'       => '0',
            'breathing_normal' => '0',
            'mobility_normal'  => '0',
        ]);

        self::assertNull($dto->weight_kg);
        self::assertNull($dto->temperature_c);
        self::assertNull($dto->notes);
        self::assertNull($dto->animal_name);
        self::assertFalse($dto->eyes_clear);
    }

    // -------------------------------------------------------------------------
    // AnimalIncidentMapper
    // -------------------------------------------------------------------------

    public function testAnimalIncidentMapperMapsFullRow(): void
    {
        $mapper = new AnimalIncidentMapper();
        $dto = $mapper->toDTO([
            'id'            => '7',
            'animal_id'     => '3',
            'incident_type' => 'injury',
            'description'   => 'Pata lastimada',
            'severity'      => 'medium',
            'reported_by'   => '2',
            'resolved_at'   => '2026-04-02 11:00:00',
            'resolved_by'   => '4',
            'created_at'    => '2026-04-01 08:00:00',
            'status'        => 'resolved',
            'animal_name'   => 'Kumo',
            'species'       => 'dog',
        ]);

        self::assertSame(7, $dto->id);
        self::assertSame('injury', $dto->incident_type);
        self::assertSame('medium', $dto->severity);
        self::assertSame(2, $dto->reported_by);
        self::assertSame('resolved', $dto->status);
        self::assertSame('Kumo', $dto->animal_name);
        self::assertSame('dog', $dto->species);
    }

    public function testAnimalIncidentMapperMapsMinimalRow(): void
    {
        $mapper = new AnimalIncidentMapper();
        $dto = $mapper->toDTO([
            'id'            => '1',
            'animal_id'     => '1',
            'incident_type' => 'behavior',
            'description'   => 'Agresivo con visitante',
            'severity'      => 'low',
            'created_at'    => '2026-04-01 07:00:00',
        ]);

        self::assertSame('open', $dto->status);
        self::assertNull($dto->reported_by);
        self::assertNull($dto->resolved_at);
        self::assertNull($dto->animal_name);
    }

    // -------------------------------------------------------------------------
    // AuditLogMapper
    // -------------------------------------------------------------------------

    public function testAuditLogMapperMapsFullRow(): void
    {
        $mapper = new AuditLogMapper();
        $dto = $mapper->toDTO([
            'id'            => '100',
            'user_id'       => '5',
            'action'        => 'update_role',
            'resource_type' => 'User',
            'resource_id'   => '10',
            'old_values'    => '{"role":"user"}',
            'new_values'    => '{"role":"admin"}',
            'ip_address'    => '192.168.1.1',
            'user_agent'    => 'Mozilla/5.0',
            'created_at'    => '2026-04-01 12:00:00',
        ]);

        self::assertSame(100, $dto->id);
        self::assertSame(5, $dto->user_id);
        self::assertSame('update_role', $dto->action);
        self::assertSame('User', $dto->resource_type);
        self::assertSame(10, $dto->resource_id);
        self::assertSame('192.168.1.1', $dto->ip_address);
    }

    public function testAuditLogMapperMapsMinimalRow(): void
    {
        $mapper = new AuditLogMapper();
        $dto = $mapper->toDTO([
            'id'         => '1',
            'action'     => 'login',
            'created_at' => '2026-04-01 12:00:00',
        ]);

        self::assertNull($dto->user_id);
        self::assertNull($dto->resource_type);
        self::assertNull($dto->resource_id);
        self::assertNull($dto->old_values);
        self::assertNull($dto->new_values);
    }

    // -------------------------------------------------------------------------
    // AuthAuditLogMapper
    // -------------------------------------------------------------------------

    public function testAuthAuditLogMapperMapsFullRow(): void
    {
        $mapper = new AuthAuditLogMapper();
        $dto = $mapper->toDTO([
            'id'          => '20',
            'user_id'     => '7',
            'event_type'  => 'login_failed',
            'success'     => '0',
            'reason'      => 'bad_password',
            'ip_address'  => '10.0.0.1',
            'user_agent'  => 'Chrome/120',
            'device_name' => 'Desktop',
            'created_at'  => '2026-04-01 09:00:00',
        ]);

        self::assertSame(20, $dto->id);
        self::assertSame(7, $dto->user_id);
        self::assertSame('login_failed', $dto->event_type);
        self::assertFalse($dto->success);
        self::assertSame('bad_password', $dto->reason);
        self::assertSame('Desktop', $dto->device_name);
    }

    public function testAuthAuditLogMapperMapsMinimalRow(): void
    {
        $mapper = new AuthAuditLogMapper();
        $dto = $mapper->toDTO([
            'id'         => '1',
            'event_type' => 'login',
            'created_at' => '2026-04-01 09:00:00',
        ]);

        self::assertNull($dto->user_id);
        self::assertTrue($dto->success);
        self::assertNull($dto->reason);
        self::assertNull($dto->device_name);
    }

    // -------------------------------------------------------------------------
    // CafeMapper
    // -------------------------------------------------------------------------

    public function testCafeMapperMapsFullRow(): void
    {
        $mapper = new CafeMapper();
        $dto = $mapper->toDTO([
            'id'               => '1',
            'slug'             => 'neko-cafe',
            'name'             => 'Neko Café',
            'japanese_name'    => 'ねこカフェ',
            'description'      => 'Un café de gatos',
            'location'         => 'Madrid',
            'category'         => 'cat',
            'animal_type'      => 'cat',
            'price_per_hour'   => '12.50',
            'capacity_max'     => '20',
            'rating_avg'       => '4.8',
            'opening_time'     => '10:00',
            'closing_time'     => '22:00',
            'timezone'         => 'Europe/Madrid',
            'is_active'        => '1',
            'has_reservations' => '1',
            'image_url'        => '/img/neko.jpg',
        ]);

        self::assertSame(1, $dto->id);
        self::assertSame('neko-cafe', $dto->slug);
        self::assertSame('Neko Café', $dto->name);
        self::assertSame('ねこカフェ', $dto->japanese_name);
        self::assertSame(12.5, $dto->price_per_hour);
        self::assertSame(20, $dto->capacity_max);
        self::assertSame(4.8, $dto->rating_avg);
        self::assertTrue($dto->is_active);
        self::assertTrue($dto->has_reservations);
    }

    public function testCafeMapperMapsMinimalRow(): void
    {
        $mapper = new CafeMapper();
        $dto = $mapper->toDTO([
            'id'   => '2',
            'slug' => 'dog-cafe',
            'name' => 'Dog Café',
        ]);

        self::assertNull($dto->japanese_name);
        self::assertNull($dto->image_url);
        self::assertSame('UTC', $dto->timezone);
        self::assertSame(0.0, $dto->price_per_hour);
        self::assertTrue($dto->is_active);
        self::assertFalse($dto->has_reservations);
    }

    // -------------------------------------------------------------------------
    // FavoriteMapper
    // -------------------------------------------------------------------------

    public function testFavoriteMapperMapsRow(): void
    {
        $mapper = new FavoriteMapper();
        $dto = $mapper->toDTO([
            'user_id'    => '3',
            'cafe_id'    => '5',
            'created_at' => '2026-03-15 11:00:00',
        ]);

        self::assertSame(3, $dto->user_id);
        self::assertSame(5, $dto->cafe_id);
        self::assertSame('2026-03-15 11:00:00', $dto->created_at);
    }

    // -------------------------------------------------------------------------
    // LoyaltyCardMapper
    // -------------------------------------------------------------------------

    public function testLoyaltyCardMapperMapsFullRow(): void
    {
        $mapper = new LoyaltyCardMapper();
        $dto = $mapper->toDTO([
            'id'                      => '8',
            'user_id'                 => '4',
            'stamps'                  => '7',
            'current_tier'            => 'silver',
            'visits_count'            => '15',
            'total_rewards_redeemed'  => '2',
            'last_stamp_at'           => '2026-04-10 14:00:00',
            'created_at'              => '2025-01-01 00:00:00',
            'updated_at'              => '2026-04-10 14:00:00',
        ]);

        self::assertSame(8, $dto->id);
        self::assertSame(4, $dto->user_id);
        self::assertSame(7, $dto->stamps);
        self::assertSame('silver', $dto->current_tier);
        self::assertSame(15, $dto->visits_count);
        self::assertSame(2, $dto->total_rewards_redeemed);
        self::assertSame('2026-04-10 14:00:00', $dto->last_stamp_at);
    }

    public function testLoyaltyCardMapperMapsMinimalRow(): void
    {
        $mapper = new LoyaltyCardMapper();
        $dto = $mapper->toDTO([
            'id'         => '1',
            'user_id'    => '2',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ]);

        self::assertSame(0, $dto->stamps);
        self::assertSame('bronze', $dto->current_tier);
        self::assertNull($dto->last_stamp_at);
    }

    // -------------------------------------------------------------------------
    // LoyaltyRewardMapper
    // -------------------------------------------------------------------------

    public function testLoyaltyRewardMapperMapsFullRow(): void
    {
        $mapper = new LoyaltyRewardMapper();
        $dto = $mapper->toDTO([
            'id'               => '5',
            'user_id'          => '3',
            'loyalty_card_id'  => '2',
            'reward_type'      => 'free_drink',
            'stamps_cost'      => '8',
            'status'           => 'active',
            'redemption_code'  => 'REWARD-ABC123',
            'redeemed_at'      => '2026-04-01 10:00:00',
            'used_at'          => '2026-04-05 12:00:00',
            'expires_at'       => '2026-05-01 00:00:00',
            'notes'            => 'VIP reward',
            'created_at'       => '2026-04-01 09:00:00',
        ]);

        self::assertSame(5, $dto->id);
        self::assertSame('free_drink', $dto->reward_type);
        self::assertSame(8, $dto->stamps_cost);
        self::assertSame('REWARD-ABC123', $dto->redemption_code);
        self::assertSame('2026-04-05 12:00:00', $dto->used_at);
        self::assertSame('VIP reward', $dto->notes);
    }

    public function testLoyaltyRewardMapperMapsMinimalRow(): void
    {
        $mapper = new LoyaltyRewardMapper();
        $dto = $mapper->toDTO([
            'id'              => '1',
            'user_id'         => '1',
            'loyalty_card_id' => '1',
            'reward_type'     => 'discount',
            'redemption_code' => 'CODE-XYZ',
            'redeemed_at'     => '2026-04-01 00:00:00',
            'created_at'      => '2026-04-01 00:00:00',
        ]);

        self::assertSame('pending', $dto->status);
        self::assertSame(0, $dto->stamps_cost);
        self::assertNull($dto->used_at);
        self::assertNull($dto->expires_at);
        self::assertNull($dto->notes);
    }

    // -------------------------------------------------------------------------
    // LoyaltyRewardCatalogMapper
    // -------------------------------------------------------------------------

    public function testLoyaltyRewardCatalogMapperMapsFullRow(): void
    {
        $mapper = new LoyaltyRewardCatalogMapper();
        $dto = $mapper->toDTO([
            'id'               => '2',
            'reward_type'      => 'free_entry',
            'name_es'          => 'Entrada gratuita',
            'name_en'          => 'Free entry',
            'stamps_required'  => '10',
            'tier_required'    => 'gold',
            'validity_days'    => '60',
            'is_active'        => '1',
            'display_order'    => '3',
            'icon'             => 'fa-ticket',
        ]);

        self::assertSame(2, $dto->id);
        self::assertSame('free_entry', $dto->reward_type);
        self::assertSame('Entrada gratuita', $dto->name_es);
        self::assertSame('Free entry', $dto->name_en);
        self::assertSame(10, $dto->stamps_required);
        self::assertSame('gold', $dto->tier_required);
        self::assertSame(60, $dto->validity_days);
        self::assertTrue($dto->is_active);
        self::assertSame('fa-ticket', $dto->icon);
    }

    public function testLoyaltyRewardCatalogMapperMapsMinimalRow(): void
    {
        $mapper = new LoyaltyRewardCatalogMapper();
        $dto = $mapper->toDTO([
            'id'        => '1',
            'reward_type' => 'free_drink',
            'name_es'   => 'Bebida gratis',
            'name_en'   => 'Free drink',
        ]);

        self::assertSame('bronze', $dto->tier_required);
        self::assertSame(30, $dto->validity_days);
        self::assertTrue($dto->is_active);
        self::assertNull($dto->icon);
    }

    // -------------------------------------------------------------------------
    // MenuCategoryMapper
    // -------------------------------------------------------------------------

    public function testMenuCategoryMapperMapsFullRow(): void
    {
        $mapper = new MenuCategoryMapper();
        $dto = $mapper->toDTO([
            'id'            => '3',
            'name'          => 'Bebidas',
            'slug'          => 'bebidas',
            'display_order' => '2',
        ]);

        self::assertSame(3, $dto->id);
        self::assertSame('Bebidas', $dto->name);
        self::assertSame('bebidas', $dto->slug);
        self::assertSame(2, $dto->display_order);
    }

    public function testMenuCategoryMapperDefaultDisplayOrder(): void
    {
        $mapper = new MenuCategoryMapper();
        $dto = $mapper->toDTO([
            'id'   => '1',
            'name' => 'Snacks',
            'slug' => 'snacks',
        ]);

        self::assertSame(0, $dto->display_order);
    }

    // -------------------------------------------------------------------------
    // PermissionMapper
    // -------------------------------------------------------------------------

    public function testPermissionMapperMapsFullRow(): void
    {
        $mapper = new PermissionMapper();
        $dto = $mapper->toDTO([
            'id'          => '12',
            'code'        => 'admin.users.edit',
            'name'        => 'Edit users',
            'description' => 'Can edit any user',
            'resource'    => 'User',
            'action'      => 'edit',
        ]);

        self::assertSame(12, $dto->id);
        self::assertSame('admin.users.edit', $dto->code);
        self::assertSame('Edit users', $dto->name);
        self::assertSame('User', $dto->resource);
        self::assertSame('edit', $dto->action);
    }

    public function testPermissionMapperMapsMinimalRow(): void
    {
        $mapper = new PermissionMapper();
        $dto = $mapper->toDTO([
            'id'   => '1',
            'code' => 'view',
            'name' => 'View',
        ]);

        self::assertNull($dto->description);
        self::assertNull($dto->resource);
        self::assertNull($dto->action);
    }

    // -------------------------------------------------------------------------
    // ProductMapper
    // -------------------------------------------------------------------------

    public function testProductMapperMapsFullRow(): void
    {
        $mapper = new ProductMapper();
        $dto = $mapper->toDTO([
            'id'                  => '9',
            'name'                => 'Matcha Latte',
            'slug'                => 'matcha-latte',
            'description'         => 'Té matcha con leche',
            'price'               => '4.50',
            'category_id'         => '2',
            'category_name'       => 'Bebidas',
            'allergens'           => '["lactose"]',
            'is_active'           => '1',
            'image_url'           => '/img/matcha.jpg',
            'product_type'        => 'drink',
            'min_pax'             => '1',
            'max_pax'             => '4',
            'duration_minutes'    => '30',
            'attributes'          => '{}',
            'target_cafe_types'   => 'cat,dog',
            'target_animal_types' => 'all',
            'stock_quantity'      => '50',
        ]);

        self::assertSame(9, $dto->id);
        self::assertSame('Matcha Latte', $dto->name);
        self::assertSame(4.5, $dto->price);
        self::assertSame(['lactose'], $dto->allergens);
        self::assertSame('drink', $dto->product_type);
        self::assertSame(1, $dto->min_pax);
        self::assertSame(50, $dto->stock_quantity);
    }

    public function testProductMapperMapsMinimalRow(): void
    {
        $mapper = new ProductMapper();
        $dto = $mapper->toDTO([
            'id'          => '1',
            'name'        => 'Cookie',
            'slug'        => 'cookie',
            'category_id' => '1',
        ]);

        self::assertSame([], $dto->allergens);
        self::assertSame('item', $dto->product_type);
        self::assertNull($dto->description);
        self::assertNull($dto->image_url);
        self::assertNull($dto->stock_quantity);
    }

    public function testProductMapperHandlesArrayAllergens(): void
    {
        $mapper = new ProductMapper();
        $dto = $mapper->toDTO([
            'id'          => '1',
            'name'        => 'Croissant',
            'slug'        => 'croissant',
            'category_id' => '1',
            'allergens'   => ['gluten', 'lactose'],
        ]);

        self::assertSame(['gluten', 'lactose'], $dto->allergens);
    }

    // -------------------------------------------------------------------------
    // ReservationMapper
    // -------------------------------------------------------------------------

    public function testReservationMapperMapsFullRow(): void
    {
        $mapper = new ReservationMapper();
        $dto = $mapper->toDTO([
            'id'               => '42',
            'uuid'             => 'abc-123',
            'cafe_id'          => '1',
            'user_id'          => '7',
            'reservation_date' => '2026-05-01',
            'reservation_time' => '11:00',
            'guest_count'      => '4',
            'status'           => 'confirmed',
            'time_slot_id'     => '15',
            'pass_name'        => 'Ana García',
            'check_in_at'      => '2026-05-01 11:05:00',
            'check_out_at'     => '2026-05-01 13:00:00',
            'final_amount'     => '50.00',
            'payment_status'   => 'paid',
            'payment_method'   => 'card',
            'notes'            => 'Alergia a gatos',
        ]);

        self::assertSame(42, $dto->id);
        self::assertSame('abc-123', $dto->uuid);
        self::assertSame(4, $dto->guest_count);
        self::assertSame('confirmed', $dto->status);
        self::assertSame(15, $dto->time_slot_id);
        self::assertSame(50.0, $dto->final_amount);
        self::assertSame('Alergia a gatos', $dto->notes);
    }

    public function testReservationMapperMapsMinimalRow(): void
    {
        $mapper = new ReservationMapper();
        $dto = $mapper->toDTO([
            'id'               => '1',
            'uuid'             => 'uuid-001',
            'cafe_id'          => '1',
            'user_id'          => '1',
            'reservation_date' => '2026-05-01',
            'reservation_time' => '10:00',
        ]);

        self::assertSame(1, $dto->guest_count);
        self::assertSame('pending', $dto->status);
        self::assertNull($dto->time_slot_id);
        self::assertNull($dto->final_amount);
        self::assertNull($dto->notes);
    }

    // -------------------------------------------------------------------------
    // ReservationItemMapper
    // -------------------------------------------------------------------------

    public function testReservationItemMapperMapsFullRow(): void
    {
        $mapper = new ReservationItemMapper();
        $dto = $mapper->toDTO([
            'id'             => '3',
            'reservation_id' => '42',
            'product_id'     => '9',
            'quantity'       => '2',
            'unit_price'     => '4.50',
            'status'         => 'served',
            'created_at'     => '2026-05-01 11:00:00',
        ]);

        self::assertSame(3, $dto->id);
        self::assertSame(42, $dto->reservation_id);
        self::assertSame(2, $dto->quantity);
        self::assertSame(4.5, $dto->unit_price);
        self::assertSame('served', $dto->status);
    }

    public function testReservationItemMapperMapsMinimalRow(): void
    {
        $mapper = new ReservationItemMapper();
        $dto = $mapper->toDTO([
            'id'             => '1',
            'reservation_id' => '1',
            'product_id'     => '1',
            'created_at'     => '2026-05-01 00:00:00',
        ]);

        self::assertSame(1, $dto->quantity);
        self::assertSame(0.0, $dto->unit_price);
        self::assertSame('pending', $dto->status);
    }

    // -------------------------------------------------------------------------
    // ReviewMapper
    // -------------------------------------------------------------------------

    public function testReviewMapperMapsFullRow(): void
    {
        $mapper = new ReviewMapper();
        $dto = $mapper->toDTO([
            'id'         => '55',
            'user_id'    => '3',
            'cafe_id'    => '1',
            'cafe_name'  => 'Neko Café',
            'user_name'  => 'María',
            'rating'     => '5',
            'title'      => '¡Increíble!',
            'body'       => 'Me encantó la experiencia',
            'status'     => 'approved',
            'created_at' => '2026-03-20 16:00:00',
        ]);

        self::assertSame(55, $dto->id);
        self::assertSame(3, $dto->user_id);
        self::assertSame('Neko Café', $dto->cafe_name);
        self::assertSame(5, $dto->rating);
        self::assertSame('approved', $dto->status);
    }

    public function testReviewMapperMapsMinimalRow(): void
    {
        $mapper = new ReviewMapper();
        $dto = $mapper->toDTO([
            'id'      => '1',
            'cafe_id' => '1',
        ]);

        self::assertSame(0, $dto->user_id);
        self::assertSame('', $dto->cafe_name);
        self::assertSame(0, $dto->rating);
        self::assertSame('pending', $dto->status);
    }

    // -------------------------------------------------------------------------
    // RoleMapper
    // -------------------------------------------------------------------------

    public function testRoleMapperMapsFullRow(): void
    {
        $mapper = new RoleMapper();
        $dto = $mapper->toDTO([
            'id'          => '2',
            'code'        => 'admin',
            'name'        => 'Administrador',
            'description' => 'Acceso total',
        ]);

        self::assertSame(2, $dto->id);
        self::assertSame('admin', $dto->code);
        self::assertSame('Administrador', $dto->name);
        self::assertSame('Acceso total', $dto->description);
    }

    public function testRoleMapperMapsMinimalRow(): void
    {
        $mapper = new RoleMapper();
        $dto = $mapper->toDTO([
            'id'   => '1',
            'code' => 'user',
            'name' => 'Usuario',
        ]);

        self::assertNull($dto->description);
    }

    // -------------------------------------------------------------------------
    // SettingMapper
    // -------------------------------------------------------------------------

    public function testSettingMapperMapsFullRow(): void
    {
        $mapper = new SettingMapper();
        $dto = $mapper->toDTO([
            'key'          => 'max_capacity',
            'value'        => '50',
            'type'         => 'integer',
            'group_name'   => 'reservations',
            'description'  => 'Capacidad máxima',
            'is_public'    => '1',
        ]);

        self::assertSame('max_capacity', $dto->key);
        self::assertSame('50', $dto->value);
        self::assertSame('integer', $dto->type);
        self::assertSame('reservations', $dto->group_name);
        self::assertTrue($dto->is_public);
    }

    public function testSettingMapperMapsMinimalRow(): void
    {
        $mapper = new SettingMapper();
        $dto = $mapper->toDTO([
            'key'   => 'site_name',
            'value' => 'Komorebi',
        ]);

        self::assertSame('string', $dto->type);
        self::assertSame('general', $dto->group_name);
        self::assertNull($dto->description);
        self::assertFalse($dto->is_public);
    }

    // -------------------------------------------------------------------------
    // StaffShiftMapper
    // -------------------------------------------------------------------------

    public function testStaffShiftMapperMapsFullRow(): void
    {
        $mapper = new StaffShiftMapper();
        $dto = $mapper->toDTO([
            'id'          => '11',
            'user_id'     => '5',
            'cafe_id'     => '1',
            'shift_date'  => '2026-05-01',
            'shift_start' => '09:00:00',
            'shift_end'   => '17:00:00',
            'notes'       => 'Turno extra',
            'created_by'  => '1',
            'created_at'  => '2026-04-28 10:00:00',
            'updated_at'  => '2026-04-28 10:00:00',
            'deleted_at'  => null,
            'staff_name'  => 'Yamamoto',
        ]);

        self::assertSame(11, $dto->id);
        self::assertSame(5, $dto->user_id);
        self::assertSame('09:00:00', $dto->shift_start);
        self::assertSame('Turno extra', $dto->notes);
        self::assertSame(1, $dto->created_by);
        self::assertSame('Yamamoto', $dto->staff_name);
        self::assertNull($dto->deleted_at);
    }

    public function testStaffShiftMapperMapsMinimalRow(): void
    {
        $mapper = new StaffShiftMapper();
        $dto = $mapper->toDTO([
            'id'          => '1',
            'user_id'     => '2',
            'cafe_id'     => '1',
            'shift_date'  => '2026-05-02',
            'shift_start' => '08:00:00',
            'shift_end'   => '16:00:00',
            'created_at'  => '2026-04-28 00:00:00',
            'updated_at'  => '2026-04-28 00:00:00',
        ]);

        self::assertNull($dto->notes);
        self::assertNull($dto->created_by);
        self::assertNull($dto->staff_name);
    }

    // -------------------------------------------------------------------------
    // SupervisorAssignmentMapper
    // -------------------------------------------------------------------------

    public function testSupervisorAssignmentMapperMapsRow(): void
    {
        $mapper = new SupervisorAssignmentMapper();
        $dto = $mapper->toDTO([
            'id'             => '4',
            'supervisor_id'  => '6',
            'reservation_id' => '42',
            'table_code'     => 'T-03',
            'cafe_id'        => '1',
            'is_active'      => '1',
            'assigned_at'    => '2026-05-01 11:00:00',
            'created_at'     => '2026-05-01 10:55:00',
        ]);

        self::assertSame(4, $dto->id);
        self::assertSame(6, $dto->supervisor_id);
        self::assertSame(42, $dto->reservation_id);
        self::assertSame('T-03', $dto->table_code);
        self::assertTrue($dto->is_active);
        self::assertSame('2026-05-01 11:00:00', $dto->assigned_at);
    }

    // -------------------------------------------------------------------------
    // TimeSlotMapper
    // -------------------------------------------------------------------------

    public function testTimeSlotMapperMapsFullRow(): void
    {
        $mapper = new TimeSlotMapper();
        $dto = $mapper->toDTO([
            'id'               => '30',
            'cafe_id'          => '1',
            'slot_date'        => '2026-05-01',
            'slot_time'        => '11:00:00',
            'total_capacity'   => '25',
            'available_spots'  => '10',
            'reserved_spots'   => '15',
            'is_blocked'       => '0',
            'blocked_reason'   => null,
            'duration_minutes' => '90',
            'created_at'       => '2026-04-01 00:00:00',
            'updated_at'       => '2026-04-28 00:00:00',
        ]);

        self::assertSame(30, $dto->id);
        self::assertSame(25, $dto->total_capacity);
        self::assertSame(10, $dto->available_spots);
        self::assertSame(15, $dto->reserved_spots);
        self::assertFalse($dto->is_blocked);
        self::assertSame(90, $dto->duration_minutes);
        self::assertNull($dto->blocked_reason);
    }

    public function testTimeSlotMapperMapsMinimalRow(): void
    {
        $mapper = new TimeSlotMapper();
        $dto = $mapper->toDTO([
            'id'         => '1',
            'cafe_id'    => '1',
            'slot_date'  => '2026-05-01',
            'slot_time'  => '10:00:00',
            'created_at' => '2026-04-01 00:00:00',
            'updated_at' => '2026-04-01 00:00:00',
        ]);

        self::assertSame(20, $dto->total_capacity);
        self::assertSame(0, $dto->available_spots);
        self::assertFalse($dto->is_blocked);
        self::assertSame(60, $dto->duration_minutes);
    }

    // -------------------------------------------------------------------------
    // TrackerMapper
    // -------------------------------------------------------------------------

    public function testTrackerMapperMapsFullRow(): void
    {
        $mapper = new TrackerMapper();
        $dto = $mapper->toDTO([
            'id'               => '7',
            'cafe_id'          => '1',
            'code'             => 'TRK-007',
            'type'             => 'table',
            'status'           => 'available',
            'last_assigned_at' => '2026-04-20 15:00:00',
            'cafe_name'        => 'Neko Café',
        ]);

        self::assertSame(7, $dto->id);
        self::assertSame('TRK-007', $dto->code);
        self::assertSame('table', $dto->type);
        self::assertSame('available', $dto->status);
        self::assertSame('2026-04-20 15:00:00', $dto->last_assigned_at);
        self::assertSame('Neko Café', $dto->cafe_name);
    }

    public function testTrackerMapperMapsMinimalRow(): void
    {
        $mapper = new TrackerMapper();
        $dto = $mapper->toDTO([
            'id'      => '1',
            'cafe_id' => '1',
            'code'    => 'TRK-001',
            'type'    => 'wristband',
            'status'  => 'in_use',
        ]);

        self::assertNull($dto->last_assigned_at);
        self::assertNull($dto->cafe_name);
    }

    // -------------------------------------------------------------------------
    // UserMapper
    // -------------------------------------------------------------------------

    public function testUserMapperMapsFullRow(): void
    {
        $mapper = new UserMapper();
        $dto = $mapper->toDTO([
            'id'          => '10',
            'uuid'        => 'uuid-user-010',
            'name'        => 'Irene',
            'email'       => 'irene@komorebi.cafe',
            'avatar'      => '/img/irene.jpg',
            'roles'       => '["admin"]',
            'is_active'   => '1',
            'cafe_id'     => '1',
            'created_at'  => '2025-01-01 00:00:00',
            'preferences' => '{"lang":"es"}',
        ]);

        self::assertSame(10, $dto->id);
        self::assertSame('uuid-user-010', $dto->uuid);
        self::assertSame('irene@komorebi.cafe', $dto->email);
        self::assertSame(['admin'], $dto->roles);
        self::assertTrue($dto->is_active);
        self::assertSame(1, $dto->cafe_id);
        self::assertSame('{"lang":"es"}', $dto->preferences);
    }

    public function testUserMapperMapsMinimalRow(): void
    {
        $mapper = new UserMapper();
        $dto = $mapper->toDTO([
            'id'    => '1',
            'uuid'  => 'uuid-001',
            'name'  => 'Guest',
            'email' => 'guest@example.com',
        ]);

        self::assertNull($dto->avatar);
        self::assertSame([], $dto->roles);
        self::assertTrue($dto->is_active);
        self::assertNull($dto->cafe_id);
        self::assertNull($dto->preferences);
    }

    public function testUserMapperHandlesArrayRoles(): void
    {
        $mapper = new UserMapper();
        $dto = $mapper->toDTO([
            'id'    => '1',
            'uuid'  => 'uuid-001',
            'name'  => 'Staff',
            'email' => 'staff@example.com',
            'roles' => ['manager', 'reception'],
        ]);

        self::assertSame(['manager', 'reception'], $dto->roles);
    }

    // -------------------------------------------------------------------------
    // WaitlistMapper
    // -------------------------------------------------------------------------

    public function testWaitlistMapperMapsFullRow(): void
    {
        $mapper = new WaitlistMapper();
        $dto = $mapper->toDTO([
            'id'               => '8',
            'token'            => 'WAIT-TOKEN-8',
            'status'           => 'notified',
            'position'         => '3',
            'time_slot_id'     => '15',
            'user_id'          => '5',
            'slot_date'        => '2026-05-03',
            'slot_time'        => '14:00',
            'cafe_name'        => 'Neko Café',
            'guest_count'      => '2',
            'contact_email'    => 'user@example.com',
            'expires_at'       => '2026-05-04 14:00:00',
            'special_requests' => 'Silla alta para bebé',
        ]);

        self::assertSame(8, $dto->id);
        self::assertSame('WAIT-TOKEN-8', $dto->token);
        self::assertSame('notified', $dto->status);
        self::assertSame(3, $dto->position);
        self::assertSame(2, $dto->guest_count);
        self::assertSame('2026-05-04 14:00:00', $dto->expires_at);
        self::assertSame('Silla alta para bebé', $dto->special_requests);
    }

    public function testWaitlistMapperMapsMinimalRow(): void
    {
        $mapper = new WaitlistMapper();
        $dto = $mapper->toDTO([
            'id'    => '1',
            'token' => 'WAIT-001',
        ]);

        self::assertSame('waiting', $dto->status);
        self::assertNull($dto->position);
        self::assertSame(1, $dto->guest_count);
        self::assertNull($dto->expires_at);
        self::assertNull($dto->special_requests);
    }
}
