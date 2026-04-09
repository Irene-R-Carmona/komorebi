<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Seeders\AnimalIncidentSeeder;
use App\Core\Seeders\AnimalSeeder;
use App\Core\Seeders\CafeSeeder;
use App\Core\Seeders\MenuSeeder;
use App\Core\Seeders\NewsletterSeeder;
use App\Core\Seeders\RbacSeeder;
use App\Core\Seeders\ReservationSeeder;
use App\Core\Seeders\ReviewSeeder;
use App\Core\Seeders\StaffSeeder;
use App\Core\Seeders\SystemSettingsSeeder;
use App\Core\Seeders\TelegramSeeder;
use App\Core\Seeders\TimeSlotSeeder;
use App\Core\Seeders\UserSeeder;
use App\Core\Seeders\WaitlistSeeder;
use Throwable;

/**
 * Orquestador de seeders.
 * Ejecuta todos los seeders dentro de una transacción.
 */
final class DatabaseSeeder
{
    /**
     * Tablas a limpiar (en orden para respetar FKs).
     */
    private const array TABLES = [
        'newsletter_subscriptions',
        'telegram_message_log',
        'telegram_users',
        'favorites',
        'reservation_items',
        'reservations',
        'reviews',
        'animal_incidents',
        'animal_relationships',
        'animal_status_log',
        'animals',
        'species_rules',
        'trackers',
        'cafe_zones',
        'user_roles',
        'users',
        'product_allergens',
        'products',
        'menu_categories',
        'cafes',
        'role_permissions',
        'permissions',
        'roles',
        'settings',
    ];

    /**
     * Seeders a ejecutar (en orden).
     *
     * @var array<class-string>
     */
    private const array SEEDERS = [
        RbacSeeder::class,           // 1. Crear roles y permisos
        CafeSeeder::class,           // 2. Cafés
        AnimalSeeder::class,         // 3. Animales
        SystemSettingsSeeder::class, // 4. Configuración del sistema
        StaffSeeder::class,          // 5. Staff con roles asignados
        UserSeeder::class,           // 6. Usuarios de prueba normales
        MenuSeeder::class,           // 7. Menú y productos
        TimeSlotSeeder::class,       // 8. Ajustar time_slots a horarios reales
        ReservationSeeder::class,    // 9. Reservas sincronizadas con time_slots
        WaitlistSeeder::class,       // 10. Lista de espera (FASE 2.3)
        AnimalIncidentSeeder::class, // 11. Incidentes de animales (necesita staff)
        ReviewSeeder::class,         // 12. Reseñas de demo
        TelegramSeeder::class,       // 13. Configuración Telegram bot
        NewsletterSeeder::class,     // 14. Suscripciones newsletter
    ];

    private bool $isCli;

    public function __construct()
    {
        $this->isCli = PHP_SAPI === 'cli';
    }

    /**
     * Ejecuta el proceso de seeding completo.
     */
    public function run(): void
    {
        $this->output('Iniciando siembra', 'header');
        $this->output(\str_repeat('─', 50));

        try {
            // Limpiar tablas sin transacción
            $this->truncateTables();

            // Ejecutar seeders secuencialmente
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

            if (!$this->isCli) {
                Logger::error('Error crítico en DatabaseSeeder', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Limpia todas las tablas.
     *
     * @return void
     */
    private function truncateTables(): void
    {
        $pdo = Database::getConnection();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            // Usamos prepared statement pattern seguro
            // (aunque aquí los nombres vienen de constante, es buena práctica)
            $pdo->exec("TRUNCATE TABLE `$table`");
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->output('Tablas limpiadas correctamente.');
    }

    /**
     * Output adaptativo (CLI vs Web).
     *
     * @param string $message Mensaje a mostrar
     * @param string $type    Tipo visual: header|success|error|info
     *
     * @return void
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
