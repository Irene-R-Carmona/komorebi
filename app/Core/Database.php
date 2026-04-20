<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

// LoggingPDO is autoloaded from App\Core\LoggingPDO

/**
 * Singleton para conexión PDO.
 *
 * Configuración segura:
 * - ERRMODE_EXCEPTION: Errores como excepciones
 * - FETCH_ASSOC: Arrays asociativos por defecto
 * - EMULATE_PREPARES=false: Prepared statements nativos (seguridad)
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $connection;

    /**
     * Constructor privado (patrón Singleton).
     *
     * @throws RuntimeException Si falta configuración o falla conexión.
     */
    private function __construct()
    {
        // Railway inyecta MYSQL_URL / DATABASE_URL como cadena de conexión completa.
        // Si está presente, tiene prioridad sobre las variables DB_* individuales.
        $databaseUrl = Env::get('MYSQL_URL') !== '' ? Env::get('MYSQL_URL') : Env::get('DATABASE_URL');

        if ($databaseUrl !== '') {
            [$host, $port, $db, $user, $pass, $charset] = self::parseDatabaseUrl($databaseUrl);
        } else {
            $host    = Env::require('DB_HOST');
            $port    = Env::get('DB_PORT', '3306');
            $db      = Env::require('DB_DATABASE');
            $user    = Env::require('DB_USERNAME');
            $pass    = Env::get('DB_PASSWORD');
            $charset = Env::get('DB_CHARSET', 'utf8mb4');
        }

        if (\str_contains($charset, '_')) {
            throw new RuntimeException(
                "DB_CHARSET inválido ($charset). Usa un charset, no una collation."
            );
        }

        $dsn = \sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $db,
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (($sslCa = Env::get('DB_SSL_CA')) !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        try {
            $this->connection = new LoggingPDO($dsn, $user, $pass, $options);

            // Collation correcta (post-conexión)
            $collation = Env::get('DB_COLLATION', 'utf8mb4_unicode_ci');
            self::validateCharset($charset, $collation);
            $this->connection->exec(
                "SET NAMES $charset COLLATE $collation"
            );
        } catch (PDOException $e) {
            Logger::error('Error de conexión a la base de datos', [
                'error' => $e->getMessage(),
                'host' => $host,
                'database' => $db,
            ]);

            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }
    }

    /**
     * Obtiene la conexión PDO (lazy initialization).
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance->connection;
    }

    /**
     * Ejecuta callback dentro de una transacción.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @throws Throwable
     *
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();

        // Si ya hay una transacción activa, ejecutar callback sin nested transaction
        if ($pdo->inTransaction()) {
            return $callback();
        }

        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /**
     * Valida charset y collation contra whitelist para prevenir inyección en SET NAMES.
     *
     * @throws \RuntimeException si el charset o collation no son válidos
     */
    public static function validateCharset(string $charset, string $collation): void
    {
        $allowedCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii', 'binary'];

        if (!\in_array($charset, $allowedCharsets, true)) {
            throw new \RuntimeException("Charset inválido: '$charset'. Permitidos: " . \implode(', ', $allowedCharsets));
        }

        if (!\preg_match('/^[a-z0-9_]+$/i', $collation)) {
            throw new \RuntimeException("Collation inválida: '$collation'. Solo caracteres alfanuméricos y guiones bajos.");
        }
    }

    /**
     * Parsea una URL de conexión a base de datos (MySQL/MariaDB).
     *
     * Soporta los formatos que Railway inyecta en MYSQL_URL y DATABASE_URL:
     *   mysql://user:password@host:port/dbname
     *   mysql://user:password@host/dbname
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string|null, 5: string}
     * @throws RuntimeException si la URL no se puede parsear
     */
    private static function parseDatabaseUrl(string $url): array
    {
        $parsed = \parse_url($url);

        if ($parsed === false || !isset($parsed['host'])) {
            throw new RuntimeException(
                'MYSQL_URL/DATABASE_URL tiene formato inválido. Esperado: mysql://user:pass@host:port/dbname'
            );
        }

        $host    = $parsed['host'];
        $port    = isset($parsed['port']) ? (string) $parsed['port'] : '3306';
        $db      = \ltrim($parsed['path'] ?? '', '/');
        $user    = $parsed['user'] ?? '';
        $pass    = isset($parsed['pass']) ? \urldecode($parsed['pass']) : null;
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        if ($db === '') {
            throw new RuntimeException('MYSQL_URL/DATABASE_URL no contiene nombre de base de datos en la ruta.');
        }

        return [$host, $port, $db, $user, $pass, $charset];
    }

    /**
     * Prevenir clonación (Singleton).
     */
    private function __clone(): void {}

    /**
     * Prevenir deserialización (Singleton).
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('No es posible deserializar el singleton.');
    }
}
