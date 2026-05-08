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

final class DatabaseServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(PDO::class, static fn() => \App\Providers\DatabaseServiceProvider::createConnection());

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

    private static function createConnection(): PDO
    {
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        [$host, $port, $database, $username, $password] = self::resolveConnectionParams();

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

    /**
     * Resuelve los parámetros de conexión.
     * Prioridad: MYSQL_URL → DATABASE_URL → variables DB_* individuales.
     *
     * @return array{0: string, 1: int, 2: string, 3: string, 4: string}
     */
    private static function resolveConnectionParams(): array
    {
        $url = Env::get('MYSQL_URL') ?: Env::get('DATABASE_URL');

        if ($url !== '') {
            return self::parseConnectionUrl($url);
        }

        $driver = Env::get('DB_CONNECTION', 'mysql');
        if ($driver !== 'mysql') {
            throw new RuntimeException("Database driver no soportado: $driver");
        }

        return [
            Env::get('DB_HOST', 'localhost'),
            (int) Env::get('DB_PORT', '3306'),
            Env::get('DB_DATABASE', 'komorebi_db'),
            Env::get('DB_USERNAME', 'root'),
            Env::get('DB_PASSWORD', ''),
        ];
    }

    /**
     * Parsea una URL de conexión tipo mysql://user:pass@host:port/dbname.
     *
     * @return array{0: string, 1: int, 2: string, 3: string, 4: string}
     */
    private static function parseConnectionUrl(string $url): array
    {
        $parsed = \parse_url($url);

        if ($parsed === false || !isset($parsed['host'])) {
            throw new RuntimeException('MYSQL_URL/DATABASE_URL no es una URL válida.');
        }

        $scheme = $parsed['scheme'] ?? 'mysql';
        if (!\in_array($scheme, ['mysql', 'mysqli'], true)) {
            throw new RuntimeException("Database driver no soportado en la URL: $scheme");
        }

        return [
            $parsed['host'],
            (int) ($parsed['port'] ?? 3306),
            \ltrim($parsed['path'] ?? '', '/'),
            \urldecode($parsed['user'] ?? ''),
            \urldecode($parsed['pass'] ?? ''),
        ];
    }
}
