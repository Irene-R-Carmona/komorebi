<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Seeders\AnimalAdoptionRequestSeeder;
use App\Core\Seeders\AnimalHealthCheckSeeder;
use App\Core\Seeders\AnimalIncidentSeeder;
use App\Core\Seeders\AnimalRelationshipSeeder;
use App\Core\Seeders\AnimalSeeder;
use App\Core\Seeders\AuditLogSeeder;
use App\Core\Seeders\AuthAuditLogSeeder;
use App\Core\Seeders\CafeSeeder;
use App\Core\Seeders\InteractionSessionSeeder;
use App\Core\Seeders\LoyaltySeeder;
use App\Core\Seeders\AllergenSeeder;
use App\Core\Seeders\MenuSeeder;
use App\Core\Seeders\NewsletterSeeder;
use App\Core\Seeders\PassInclusionsSeeder;
use App\Core\Seeders\RbacSeeder;
use App\Core\Seeders\ReservationSeeder;
use App\Core\Seeders\ReviewSeeder;
use App\Core\Seeders\StaffSeeder;
use App\Core\Seeders\StaffShiftSeeder;
use App\Core\Seeders\SupervisorAssignmentSeeder;
use App\Core\Seeders\SystemSettingsSeeder;
use App\Core\Seeders\TimeSlotSeeder;
use App\Core\Seeders\UserSeeder;
use App\Core\Seeders\WaitlistSeeder;
use Throwable;

/**
 * Orquestador de seeders.
 */
final class DatabaseSeeder
{
    /**
     * Tablas a limpiar (en orden para respetar FKs).
     */
    private const array TABLES = [
        'api_tokens',
        'active_sessions',
        'email_verification_tokens',
        'password_reset_tokens',
        'telegram_message_logs',
        'telegram_users',
        'api_audit_logs',
        'audit_logs',
        'auth_audit_logs',
        'favorites',
        'waitlist',
        'time_slots',
        'user_animal_visits',
        'loyalty_rewards',
        'loyalty_cards',
        // loyalty_reward_catalog se omite: lo gestiona la migración 012 con ON DUPLICATE KEY UPDATE
        'supervisor_assignments',
        'reservation_items',
        'reservations',
        'reviews',
        'animal_health_checks',
        'animal_incidents',
        'animal_adoption_requests',
        'animal_relationships',
        'animal_status_log',
        'interaction_sessions',
        'animals',
        'species_rules',
        'staff_shifts',
        'trackers',
        'cafe_zones',
        'user_roles',
        'users',
        'pass_inclusions',
        'product_allergens',
        'products',
        'allergens',
        'menu_categories',
        'cafes',
        'role_permissions',
        'permissions',
        'roles',
        'settings',
        'newsletter_subscriptions',
    ];

    private const array SEEDERS = [
        RbacSeeder::class,           // 1. Crear roles y permisos
        CafeSeeder::class,           // 2. Cafés
        AnimalSeeder::class,         // 3. Animales
        SystemSettingsSeeder::class, // 4. Configuración del sistema
        StaffSeeder::class,          // 5. Staff con roles asignados
        UserSeeder::class,           // 6. Usuarios de prueba normales
        MenuSeeder::class,             // 7. Menú y productos
        AllergenSeeder::class,        // 8. Alérgenos UE 1169/2011 (14 obligatorios)
        PassInclusionsSeeder::class,  // 9. Inclusiones de pases por categoría
        TimeSlotSeeder::class,        // 10. Ajustar time_slots a horarios reales
        ReservationSeeder::class,     // 11. Reservas sincronizadas con time_slots
        WaitlistSeeder::class,        // 12. Lista de espera
        AnimalIncidentSeeder::class,  // 13. Incidentes de animales (necesita staff)
        ReviewSeeder::class,          // 14. Reseñas de demo
        NewsletterSeeder::class,      // 15. Suscripciones newsletter
        AuditLogSeeder::class,            // 16. Logs de auditoría de acciones
        AuthAuditLogSeeder::class,        // 17. Logs de autenticación
        LoyaltySeeder::class,             // 18. Loyalty cards, rewards y visitas a animales
        StaffShiftSeeder::class,          // 19. Turnos de staff (5 semanas)
        AnimalHealthCheckSeeder::class,   // 20. Chequeos de salud (14 días)
        SupervisorAssignmentSeeder::class, // 21. Asignaciones de supervisores
        AnimalRelationshipSeeder::class,  // 22. Relaciones entre animales del mismo café
        InteractionSessionSeeder::class,  // 23. Sesiones de interacción (retroactivas)
        AnimalAdoptionRequestSeeder::class, // 24. Solicitudes de adopción
    ];

    private bool $isCli;

    public function __construct()
    {
        $this->isCli = PHP_SAPI === 'cli';
    }

    public function run(): void
    {
        $this->output('Iniciando siembra', 'header');
        $this->output(\str_repeat('─', 50));

        try {
            $this->truncateTables();

            foreach (self::SEEDERS as $seederClass) {
                try {
                    $seeder = new $seederClass();
                    $seeder->run();
                    $this->output("Seeder ejecutado: $seederClass", 'info');
                } catch (Throwable $e) {
                    $this->output("ERROR en $seederClass: " . $e->getMessage(), 'error');
                }
            }

            $this->output(\str_repeat('─', 50));
            $this->output('Siembra completada', 'success');
        } catch (Throwable $e) {
            $this->output('ERROR CRÍTICO: ' . $e->getMessage(), 'error');

            Logger::error('Error crítico en DatabaseSeeder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function truncateTables(): void
    {
        $pdo = Database::getConnection();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->output('Tablas limpiadas correctamente.');
    }

    /**
     * @param string $type Tipo visual: header|success|error|info
     */
    private function output(string $message, string $type = 'info'): void
    {
        if ($this->isCli) {
            echo $message . PHP_EOL;
        } else {
            $colors = [
                'header' => '#6366f1',
                'success' => '#22c55e',
                'error' => '#ef4444',
                'info' => '#64748b',
            ];
            $color = $colors[$type] ?? $colors['info'];
            echo "<div style=\"color:$color;font-family:monospace;\">$message</div>";
        }
    }
}
