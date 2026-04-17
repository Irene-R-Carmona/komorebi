<?php

declare(strict_types=1);

/**
 * Script de aplicación de base de datos
 *
 * Aplica migraciones SQL y ejecuta seeders en orden correcto.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
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

const SEPARATOR = "\n===============================================================\n";

// Parse argumentos
$options = getopt('', ['force', 'seeders-only', 'help']);
$force = isset($options['force']);
$seedersOnly = isset($options['seeders-only']);

$logDir = __DIR__ . '/../storage/logs';
$logFile = $logDir . '/init-migrations.log';
$seedLock = __DIR__ . '/../storage/.seeded';

if (!is_dir($logDir) && !mkdir($logDir, 0o755, true) && !is_dir($logDir)) {
    // Intento de creación fallido; usar stdout como fallback
    $logFile = 'php://stdout';
}

// Si no se puede escribir en storage, usar stdout como fallback
$canWriteLog = is_writable($logDir) || (!file_exists($logFile) && is_writable(dirname($logFile)));
if (!$canWriteLog) {
    $logFile = 'php://stdout';
}

function logMsg(string $msg, string $level = 'info'): void
{
    // Mostrar en consola para visibilidad en entrypoint
    $dt = new \DateTime();
    $time = $dt->format('Y-m-d H:i:s');
    $line = "[$time] " . $msg . PHP_EOL;
    echo $line;

    // Enviar a Monolog (wrapper)
    try {
        switch (strtolower($level)) {
            case 'error':
                Logger::error($msg, ['level' => $level]);
                break;
            case 'warning':
                Logger::warning($msg);
                break;
            case 'debug':
                Logger::debug($msg);
                break;
            default:
                Logger::info($msg);
                break;
        }
    } catch (Throwable $e) {
        // No bloquear la ejecución si el logger falla
        echo '[logger-fallback] ' . $e->getMessage() . PHP_EOL;
    }
}

if (isset($options['help'])) {
    echo <<<HELP

        APLICADOR DE REDISEÑO BD KOMOREBI v2.0

        Uso: php scripts/apply-db.php [opciones]

        Opciones:
          --force         Aplicar sin confirmación (modo CI/CD)
          --seeders-only  Solo ejecutar seeders (skip migraciones)
          --help          Mostrar esta ayuda

        Orden de ejecución:
          1. Migraciones SQL (001-012, secuencialmente)
          2. Seeders: RBAC → Menu → Animal → Cafe → Settings → Staff → User

        ADVERTENCIA: Este script modifica la estructura de la base de datos.
                    Asegúrate de tener un backup antes de continuar.

        HELP;
    exit(0);
}

logMsg(SEPARATOR);
logMsg('  APLICADOR DE REDISEÑO BD - KOMOREBI v2.0');
logMsg(SEPARATOR);

try {
    $db = Database::getConnection();
    logMsg('OK: Conexión a BD establecida');
} catch (Throwable $e) {
    logMsg('ERROR: Error conectando a BD: ' . $e->getMessage());
    exit(1);
}

// Confirmación (solo si no --force y no --seeders-only)
if (!$force && !$seedersOnly) {
    echo "\nWARNING: ADVERTENCIA: Este script aplicará cambios estructurales a la BD.\n";
    echo "   - Eliminará campo 'rating' de tabla 'cafes'\n";
    echo "   - Añadirá campos RGPD (deleted_at, anonymized_at, retention_until)\n";
    echo "   - Creará eventos MySQL de purga automática\n";
    echo "   - Aplicará 9 migraciones SQL\n\n";
    echo '¿Continuar? (yes/no): ';
    $handle = fopen('php://stdin', 'rb');
    $line = trim(fgets($handle));
    fclose($handle);

    if ($line !== 'yes') {
        logMsg('Operación cancelada por el usuario.', 'warning');
        exit(0);
    }
}

// ════════════════════════════════════════════════════════════════
// PASO 1: APLICAR MIGRACIONES SQL
// ════════════════════════════════════════════════════════════════

if (!$seedersOnly) {
    logMsg(SEPARATOR);
    logMsg('  PASO 1: APLICANDO MIGRACIONES SQL');
    logMsg(SEPARATOR);

    $migrationsPath = __DIR__ . '/../migrations';
    $migrations = [
        '001_infrastructure.sql',
        '002_users_rbac.sql',
        '003_reviews.sql',
        '004_reservations.sql',
        '005_email_auth.sql',
        '006_telegram_bot.sql',
        '007_external_cache.sql',
        '008_animals.sql',
        '009_system_settings.sql',
        '010_newsletter.sql',
        '011_time_slots_waitlist.sql',
        '012_waitlist.sql',
        '012b_reservation_triggers.sql',
        '013_loyalty_system.sql',
        '014_staff_shifts.sql',
        '015_animal_health_checks.sql',
        '016_supervisor_assignments.sql',
        '017_product_stock.sql',
        '018_api_tokens.sql',
    ];

    foreach ($migrations as $migration) {
        $path = $migrationsPath . '/' . $migration;

        if (!file_exists($path)) {
            logMsg("SKIP: $migration (archivo no encontrado)");
            continue;
        }

        logMsg("Aplicando: $migration ...");

        try {
            $sql = file_get_contents($path);

            // Ejecutar migration completa
            $db->exec($sql);

            logMsg('OK');
        } catch (PDOException $e) {
            // Si falla por tabla existente, es OK (migraciones idempotentes)
            if (str_contains($e->getMessage(), 'already exists')) {
                logMsg('(tabla ya existe, skip)');
            } else {
                logMsg('ERROR: ' . $e->getMessage());

                if (!$force) {
                    logMsg('ERROR: Migración fallida. Detener ejecución.', 'error');
                    exit(1);
                }
            }
        }
    }

    logMsg('OK: Migraciones SQL aplicadas');
}

// ════════════════════════════════════════════════════════════════
// PASO 2: EJECUTAR SEEDERS (mejorado, con prereqs y múltiples pasadas)
// ════════════════════════════════════════════════════════════════

logMsg(SEPARATOR);
logMsg('  PASO 2: EJECUTANDO SEEDERS');
logMsg(SEPARATOR);

// Definir seeders en el orden de ejecución y prerequisitos SQL por seeder
$seeders = [
    'RBAC' => ['class' => RbacSeeder::class, 'prereq' => null],
    'Cafes' => ['class' => CafeSeeder::class, 'prereq' => null],
    'Animals' => ['class' => AnimalSeeder::class, 'prereq' => null],
    'AnimalIncidents' => ['class' => AnimalIncidentSeeder::class, 'prereq' => static function (PDO $db) {
        $cnt = (int) $db->query('SELECT COUNT(*) FROM animals')->fetchColumn();

        return $cnt > 0;
    }],
    'Settings' => ['class' => SystemSettingsSeeder::class, 'prereq' => null],
    'Menu' => ['class' => MenuSeeder::class, 'prereq' => null],
    'Staff' => ['class' => StaffSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere cafés creados
        $cnt = (int) $db->query('SELECT COUNT(*) FROM cafes')->fetchColumn();

        return $cnt > 0;
    }],
    'Users' => ['class' => UserSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere rol 'user' existente
        $stmt = $db->prepare('SELECT COUNT(*) FROM roles WHERE code = :code');
        $stmt->execute(['code' => 'user']);

        return ((int) $stmt->fetchColumn()) > 0;
    }],
    'Reservations' => ['class' => ReservationSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere usuarios, cafés, product_type 'pass' y time_slots
        $u = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $c = (int) $db->query('SELECT COUNT(*) FROM cafes')->fetchColumn();
        $p = (int) $db->query("SELECT COUNT(*) FROM products WHERE product_type = 'pass'")->fetchColumn();
        $t = (int) $db->query('SELECT COUNT(*) FROM time_slots')->fetchColumn();

        return $u > 0 && $c > 0 && $p > 0 && $t > 0;
    }],
    'Reviews' => ['class' => ReviewSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere reservas completadas
        $cnt = (int) $db->query("SELECT COUNT(*) FROM reservations WHERE status = 'completed'")->fetchColumn();

        return $cnt > 0;
    }],
    'Telegram' => ['class' => TelegramSeeder::class, 'prereq' => null],
    'Newsletter' => ['class' => NewsletterSeeder::class, 'prereq' => null],
    'TimeSlots' => ['class' => TimeSlotSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere cafés creados
        $cnt = (int) $db->query('SELECT COUNT(*) FROM cafes')->fetchColumn();

        return $cnt > 0;
    }],
    'Waitlist' => ['class' => WaitlistSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere usuarios y time_slots
        $u = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $t = (int) $db->query('SELECT COUNT(*) FROM time_slots')->fetchColumn();

        return $u > 0 && $t > 0;
    }],
];

// Verificar si necesitamos ejecutar seeders
$shouldSeed = true;
$forceSeedEnv = getenv('FORCE_SEED');

if (file_exists($seedLock) && !$force && $forceSeedEnv !== '1') {
    // Verificar si las tablas críticas tienen datos
    try {
        $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $cafeCount = (int) $db->query('SELECT COUNT(*) FROM cafes')->fetchColumn();
        $roleCount = (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn();

        if ($userCount > 0 && $cafeCount > 0 && $roleCount > 0) {
            logMsg("SKIP: Lockfile encontrado ($seedLock) y BD contiene datos. Saltando seeders.");
            logMsg("      (usuarios: $userCount, cafés: $cafeCount, roles: $roleCount)");
            logMsg('      Para forzar re-seeding: --force o FORCE_SEED=1');
            $shouldSeed = false;
        } else {
            logMsg('WARNING: Lockfile existe pero tablas están vacías. Ejecutando seeders...');
            logMsg("      (usuarios: $userCount, cafés: $cafeCount, roles: $roleCount)");
        }
    } catch (Throwable $e) {
        logMsg('WARNING: Error verificando datos existentes: ' . $e->getMessage());
        logMsg('      Ejecutando seeders por precaución...');
    }
}

if ($shouldSeed) {
    // IMPORTANTE: Limpiar tablas antes de ejecutar seeders para evitar duplicados
    logMsg('Limpiando tablas antes de insertar datos...');

    $tablesToClean = [
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

    try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tablesToClean as $table) {
            try {
                $db->exec("TRUNCATE TABLE `$table`");
                logMsg("  OK Tabla '$table' limpiada");
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), "doesn't exist")) {
                    logMsg("  WARNING: Error limpiando tabla '$table': " . $e->getMessage());
                }
            }
        }

        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        logMsg("OK Tablas limpiadas correctamente\n");
    } catch (Throwable $e) {
        logMsg('WARNING: Error durante limpieza de tablas: ' . $e->getMessage(), 'warning');
    }

    $pending = $seeders;
    $results = [];
    $max_passes = 3;

    for ($pass = 1; $pass <= $max_passes && !empty($pending); $pass++) {
        logMsg("[Pass $pass] Seeders pendientes: " . implode(', ', array_keys($pending)));

        foreach ($pending as $name => $meta) {
            $cls = $meta['class'];
            $prereq = $meta['prereq'];

            // Comprobar prerequisitos
            $canRun = true;
            if (is_callable($prereq)) {
                try {
                    $canRun = (bool) $prereq($db);
                } catch (Throwable $e) {
                    logMsg("[$name] Error comprobando prerequisitos: " . $e->getMessage());
                    $canRun = false;
                }
            }

            if (!$canRun) {
                logMsg("[$name] Prerequisitos no cumplidos. Se pospone al siguiente pass.");
                continue;
            }

            // Clase debe existir
            if (!class_exists($cls)) {
                logMsg("SKIP: Seeder $name (clase $cls no encontrada)");
                $results[$name] = 'missing_class';
                unset($pending[$name]);
                continue;
            }

            logMsg("Ejecutando: {$name}Seeder ...");

            try {
                $seeder = new $cls();
                $seeder->run();
                logMsg("OK {$name}Seeder completado");
                $results[$name] = 'ok';
                unset($pending[$name]);
            } catch (Throwable $e) {
                // Log detallado: mensaje + trace
                logMsg("ERROR: {$name}Seeder falló: " . $e->getMessage());
                logMsg("[$name] Exception: " . $e->__toString());
                $results[$name] = 'failed';

                if (!$force) {
                    logMsg('ERROR: Seeder fallido. Detener ejecución.', 'error');
                    exit(1);
                }

                // En modo force, no eliminar de pending para permitir reintentos en siguientes pasadas
                // Se mantiene en $pending para que pueda volver a intentarse si fue por prereqs
            }
        }

        if (!empty($pending) && $pass < $max_passes) {
            logMsg('Seeders aún pendientes: ' . implode(', ', array_keys($pending)) . '. Esperando 2s antes del siguiente pass...');
            sleep(2);
        }
    }

    // Informe final
    if (!empty($pending)) {
        logMsg('WARNING: Algunos seeders no se ejecutaron tras ' . $max_passes . ' pasadas: ' . implode(', ', array_keys($pending)), 'warning');
        foreach ($pending as $n => $m) {
            $results[$n] ??= 'pending';
        }
    }

    // Crear lockfile si OK (ok o missing_class)
    $allOk = array_reduce($results, static function ($carry, $item) {
        return $carry && ($item === 'ok' || $item === 'missing_class');
    }, true);

    if ($allOk) {
        if ($logFile !== 'php://stdout') {
            $dt = new \DateTime();
            @file_put_contents($seedLock, $dt->format(DATE_ATOM));
            logMsg("Lockfile creado: $seedLock");
        } else {
            logMsg('Lockfile no creado porque el log está en stdout (entorno no escribible)');
        }
    } else {
        logMsg('No se creó lockfile porque algunos seeders fallaron o quedaron pendientes');
    }

    // Resumen
    logMsg('Resumen de seeders:');
    foreach ($results as $n => $status) {
        logMsg(" - $n : $status");
    }

    if (!$allOk) {
        logMsg('WARNING: Atención: revisar logs y corregir errores antes de considerar el proceso completo.', 'warning');
    }
}

logMsg('OK: Seeders (intento) finalizado');

// ════════════════════════════════════════════════════════════════
// PASO 3: VERIFICAR EVENTOS RGPD
// ════════════════════════════════════════════════════════════════

logMsg(SEPARATOR);
logMsg('  PASO 3: VERIFICANDO EVENTOS RGPD');
logMsg(SEPARATOR);

try {
    $stmt = $db->query('SHOW EVENTS');
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $expectedEvents = [
        'evt_cleanup_deleted_cafes',
        'evt_gdpr_purge_users',
        'evt_purge_audit_logs',
        'evt_purge_auth_logs',
        'evt_cleanup_old_reservations',
        'evt_cleanup_old_products',
        'evt_purge_telegram_logs',
        'evt_cleanup_old_animals',
    ];

    $foundEvents = array_map(static fn ($e) => $e['Name'], $events);

    logMsg('Eventos encontrados:');
    $totalFound = 0;
    foreach ($expectedEvents as $expected) {
        $found = in_array($expected, $foundEvents, true);
        $status = $found ? '[OK]' : '[MISSING]';
        logMsg("  $status $expected");
        if ($found) {
            $totalFound++;
        }
    }

    logMsg('Total: ' . $totalFound . ' / ' . count($expectedEvents) . ' eventos RGPD activos');

    if ($totalFound < count($expectedEvents)) {
        logMsg('WARNING: ADVERTENCIA: Algunos eventos RGPD no están activos.', 'warning');
        logMsg('   Verifica que MySQL tenga el event_scheduler habilitado: SET GLOBAL event_scheduler = ON;');
    }
} catch (PDOException $e) {
    logMsg('WARNING: No se pudo verificar eventos: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════
// RESUMEN FINAL
// ════════════════════════════════════════════════════════════════

logMsg(SEPARATOR);
logMsg('  REDISEÑO DE BD APLICADO (resumen)');
logMsg(SEPARATOR);

logMsg('Próximos pasos recomendados:');
logMsg('1. Verificar eventos MySQL: SHOW EVENTS;');
logMsg("2. Verificar scheduler: SHOW VARIABLES LIKE 'event_scheduler';");
logMsg('3. Habilitar scheduler: SET GLOBAL event_scheduler = ON;');
logMsg('4. Ejecutar tests: docker compose exec app php vendor/bin/phpunit');
logMsg('5. Revisar logs: docker compose logs -f app');

exit(0);
