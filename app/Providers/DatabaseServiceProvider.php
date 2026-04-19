<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Config;
use App\Core\Container;
use App\Core\Env;
use App\Core\Logger;
use App\Core\ServiceProvider;
use Override;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Database Service Provider.
 *
 * Registra la conexión PDO en el Container como singleton.
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(PDO::class, function () {
            return $this->createConnection();
        });

        // Alias corto
        Container::alias('db', PDO::class);
    }

    #[Override]
    public function boot(): void
    {
        // Verificar conexión en modo debug
        if (Config::getBool('app.debug', false)) {
            try {
                $pdo = Container::make(PDO::class);
                $pdo->query('SELECT 1');
            } catch (Throwable $e) {
                Logger::error('Verificación de conexión a base de datos fallida', [
                    'exception' => \get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Crear conexión PDO.
     */
    private function createConnection(): PDO
    {
        $driver = Env::get('DB_CONNECTION', 'mysql');
        $host = Env::get('DB_HOST', 'localhost');
        $port = (int) Env::get('DB_PORT', '3306');
        $database = Env::get('DB_DATABASE', 'komorebi_db');
        $username = Env::get('DB_USERNAME', 'root');
        $password = Env::get('DB_PASSWORD', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        if ($driver !== 'mysql') {
            throw new RuntimeException("Database driver no soportado: $driver");
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=$charset";

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Configurar charset
            $collation = Env::get('DB_COLLATION', 'utf8mb4_unicode_ci');
            $pdo->exec("SET NAMES $charset COLLATE $collation");

            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos: ' . $e->getMessage());
        }
    }
}
