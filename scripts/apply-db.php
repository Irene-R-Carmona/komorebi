<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Core\Seeders\AllergenSeeder;
use App\Core\Seeders\AnimalIncidentSeeder;
use App\Core\Seeders\AnimalSeeder;
use App\Core\Seeders\AuditLogSeeder;
use App\Core\Seeders\AuthAuditLogSeeder;
use App\Core\Seeders\CafeSeeder;
use App\Core\Seeders\MenuSeeder;
use App\Core\Seeders\NewsletterSeeder;
use App\Core\Seeders\PassInclusionsSeeder;
use App\Core\Seeders\RbacSeeder;
use App\Core\Seeders\ReservationSeeder;
use App\Core\Seeders\ReviewSeeder;
use App\Core\Seeders\StaffSeeder;
use App\Core\Seeders\SystemSettingsSeeder;
use App\Core\Seeders\TimeSlotSeeder;
use App\Core\Seeders\UserSeeder;
use App\Core\Seeders\WaitlistSeeder;

const SEPARATOR = "\n===============================================================\n";

// Parse argumentos
$options = getopt('', ['force', 'seeders-only', 'help']);
$force = isset($options['force']);
$seedersOnly = isset($options['seeders-only']);

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0o755, true);
}
$seedLock = __DIR__ . '/../storage/.seeded';

function logMsg(string $msg, string $level = 'info'): void
{
    $dt = new DateTime();
    echo '[' . $dt->format('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;

    try {
        switch (strtolower($level)) {
            case 'error':
                Logger::error($msg, []);
                break;
            case 'warning':
                Logger::warning($msg);
                break;
            case 'debug':
                Logger::debug($msg);
                break;
            default:
                Logger::info($msg);
        }
    } catch (Throwable $e) {
        echo '[logger-fallback] ' . $e->getMessage() . PHP_EOL;
    }
}

if (isset($options['help'])) {
    echo <<<HELP

        APLICADOR DE MIGRACIONES Y SEEDERS - KOMOREBI

        Uso: php scripts/apply-db.php [opciones]

        Opciones:
          --force         Aplicar sin confirmación (modo CI/CD)
          --seeders-only  Solo ejecutar seeders (skip migraciones)
          --help          Mostrar esta ayuda

        Orden de ejecución:
          1. Migraciones SQL (todos los *.sql en /migrations/, orden alfabético, idempotente)
          2. Seeders: RBAC → Cafes → Animals → Menu → Staff → Users → Reservations → Reviews → Newsletter → TimeSlots → Waitlist
          3. Verificación de eventos RGPD

        ADVERTENCIA: Este script modifica la estructura de la base de datos.
                    Asegúrate de tener un backup antes de continuar.

        HELP;
    exit(0);
}

logMsg(SEPARATOR);
logMsg('  APLICADOR DE MIGRACIONES Y SEEDERS - KOMOREBI');
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
    echo "\nADVERTENCIA: Este script aplicará cambios a la base de datos.\n";
    echo "   - Aplicará todos los archivos de migración SQL en /migrations/ (idempotente)\n";
    echo "   - Ejecutará seeders si la BD está vacía\n";
    echo "   - Verificará eventos RGPD de purga automática\n\n";
    echo '¿Continuar? (yes/no): ';
    $handle = fopen('php://stdin', 'rb');
    $rawLine = $handle !== false ? fgets($handle) : false;
    $line = $rawLine !== false ? trim($rawLine) : '';
    if ($handle !== false) {
        fclose($handle);
    }

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
    $found = glob($migrationsPath . '/*.sql');
    if ($found === false) {
        $found = [];
    }
    sort($found);
    $migrations = array_map('basename', $found);

    foreach ($migrations as $migration) {
        $path = $migrationsPath . '/' . $migration;

        if (!file_exists($path)) {
            logMsg("SKIP: $migration (archivo no encontrado)");
            continue;
        }

        logMsg("Aplicando: $migration ...");

        try {
            $sql = file_get_contents($path);

            $db->exec($sql);

            logMsg('OK');
        } catch (PDOException $e) {
            // Si falla por objeto ya existente, es OK (migraciones idempotentes)
            // - MySQL CREATE TABLE: "already exists"
            // - MySQL ADD COLUMN:   "Duplicate column name"
            // - MySQL CREATE INDEX: "Duplicate key name"
            $msg = $e->getMessage();
            if (
                str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate column name')
                || str_contains($msg, 'Duplicate key name')
                || str_contains($msg, 'Duplicate foreign key constraint name')
                || str_contains($msg, 'Duplicate check constraint name')
            ) {
                logMsg('(objeto ya existe, skip)');
            } else {
                logMsg('ERROR: ' . $msg, 'error');
                logMsg('ERROR: Migración fallida. Detener ejecución.', 'error');
                exit(1);
            }
        }
    }

    logMsg('OK: Migraciones SQL aplicadas');
}

// ════════════════════════════════════════════════════════════════
// PASO 2: EJECUTAR SEEDERS
// ════════════════════════════════════════════════════════════════

logMsg(SEPARATOR);
logMsg('  PASO 2: EJECUTANDO SEEDERS');
logMsg(SEPARATOR);

// Definir seeders con prerequisitos de ejecución
$seeders = [
    'RBAC' => ['class' => RbacSeeder::class, 'prereq' => null],
    'Cafes' => ['class' => CafeSeeder::class, 'prereq' => null],
    'Animals' => ['class' => AnimalSeeder::class, 'prereq' => null],
    'AnimalIncidents' => ['class' => AnimalIncidentSeeder::class, 'prereq' => static function (PDO $db) {
        $cnt = (int) $db->query('SELECT COUNT(*) FROM animals')->fetchColumn();

        return $cnt > 0;
    }],
    'Settings' => ['class' => SystemSettingsSeeder::class, 'prereq' => null],
    'Allergens' => ['class' => AllergenSeeder::class, 'prereq' => null],
    'Menu' => ['class' => MenuSeeder::class, 'prereq' => null],
    'PassInclusions' => ['class' => PassInclusionsSeeder::class, 'prereq' => static function (PDO $db) {
        $p = (int) $db->query("SELECT COUNT(*) FROM products WHERE product_type = 'pass'")->fetchColumn();
        $c = (int) $db->query('SELECT COUNT(*) FROM menu_categories')->fetchColumn();

        return $p > 0 && $c > 0;
    }],
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
    'AuditLog' => ['class' => AuditLogSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere usuarios existentes
        $u = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

        return $u > 0;
    }],
    'AuthAuditLog' => ['class' => AuthAuditLogSeeder::class, 'prereq' => static function (PDO $db) {
        // Requiere usuarios existentes
        $u = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

        return $u > 0;
    }],
];

// Verificar si necesitamos ejecutar seeders
$shouldSeed = true;
$forceSeedEnv = getenv('FORCE_SEED');

if (!$force && $forceSeedEnv !== '1') {
    try {
        $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $cafeCount = (int) $db->query('SELECT COUNT(*) FROM cafes')->fetchColumn();
        $roleCount = (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn();

        if ($userCount > 0 && $cafeCount > 0 && $roleCount > 0) {
            $lockStatus = file_exists($seedLock)
                ? "Lockfile encontrado ($seedLock) y"
                : 'Lockfile ausente (filesystem efímero) pero';
            logMsg("SKIP: $lockStatus BD contiene datos. Saltando seeders.");
            logMsg("      (usuarios: $userCount, cafés: $cafeCount, roles: $roleCount)");
            logMsg('      Para forzar re-seeding usar variable de entorno FORCE_SEED=1');
            $shouldSeed = false;
        } else {
            $lockStatus = file_exists($seedLock)
                ? 'Lockfile existe pero tablas están vacías.'
                : 'Primera ejecución — tablas vacías.';
            logMsg("INFO: $lockStatus Ejecutando seeders...");
            logMsg("      (usuarios: $userCount, cafés: $cafeCount, roles: $roleCount)");
        }
    } catch (Throwable $e) {
        logMsg('WARNING: Error verificando datos existentes: ' . $e->getMessage());
        logMsg('      Ejecutando seeders por precaución...');
    }
}

if ($shouldSeed) {
    logMsg('Limpiando tablas antes de insertar datos...');

    $tablesToClean = [
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
        'loyalty_reward_catalog',
        'supervisor_assignments',
        'reservation_items',
        'reservations',
        'reviews',
        'animal_health_checks',
        'animal_incidents',
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
    $maxPasses = 3;

    for ($pass = 1; $pass <= $maxPasses && !empty($pending); $pass++) {
        logMsg("[Pass $pass] Seeders pendientes: " . implode(', ', array_keys($pending)));

        foreach ($pending as $name => $meta) {
            $cls = $meta['class'];
            $prereq = $meta['prereq'];

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
                logMsg("ERROR: {$name}Seeder falló: " . $e->getMessage(), 'error');
                logMsg("[$name] Exception: " . $e->__toString());
                $results[$name] = 'failed';
                logMsg('ERROR: Seeder fallido. Detener ejecución.', 'error');
                exit(1);
            }
        }

        if (!empty($pending) && $pass < $maxPasses) {
            logMsg('Seeders aún pendientes: ' . implode(', ', array_keys($pending)) . '. Esperando 2s antes del siguiente pass...');
            sleep(2);
        }
    }

    if (!empty($pending)) {
        logMsg('WARNING: Algunos seeders no se ejecutaron tras ' . $maxPasses . ' pasadas: ' . implode(', ', array_keys($pending)), 'warning');
        foreach (array_keys($pending) as $n) {
            $results[$n] ??= 'pending';
        }
    }

    $allOk = array_reduce($results, static function ($carry, $item) {
        return $carry && ($item === 'ok' || $item === 'missing_class');
    }, true);

    if ($allOk) {
        if (is_writable(dirname($seedLock))) {
            $dt = new DateTime();
            file_put_contents($seedLock, $dt->format(DATE_ATOM));
            logMsg("Lockfile creado: $seedLock");
        } else {
            logMsg('Lockfile no creado: directorio de storage no escribible');
        }
    } else {
        logMsg('No se creó lockfile porque algunos seeders fallaron o quedaron pendientes');
    }

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
        'evt_expire_waitlist',
        'evt_cleanup_old_time_slots',
        'evt_expire_loyalty_rewards',
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
        logMsg('ADVERTENCIA: Algunos eventos RGPD no están activos.', 'warning');
        logMsg('   Verifica que MySQL tenga el event_scheduler habilitado: SET GLOBAL event_scheduler = ON;');
    }
} catch (PDOException $e) {
    logMsg('WARNING: No se pudo verificar eventos: ' . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════
// RESUMEN FINAL
// ════════════════════════════════════════════════════════════════

logMsg(SEPARATOR);
logMsg('  PROCESO COMPLETADO (resumen)');
logMsg(SEPARATOR);

logMsg('Próximos pasos recomendados:');
logMsg('1. Verificar eventos MySQL: SHOW EVENTS;');
logMsg("2. Verificar scheduler: SHOW VARIABLES LIKE 'event_scheduler';");
logMsg('3. Habilitar scheduler: SET GLOBAL event_scheduler = ON;');
logMsg('4. Ejecutar tests: docker compose exec app php vendor/bin/phpunit');
logMsg('5. Revisar logs: docker compose logs -f app');

exit(0);
