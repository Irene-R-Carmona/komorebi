<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Exception;
use PDO;

/**
 * Modelo Setting
 *
 * Sistema centralizado de configuración con caché en memoria.
 * Soporta tipos: string, integer, boolean, json
 */
final class Setting
{
    private PDO $db;
    private static array $cache = [];
    private static bool $cacheLoaded = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene un valor de configuración
     *
     * @param string $key     Clave de configuración
     * @param mixed  $default Valor por defecto si no existe
     * @return mixed Valor parseado según su tipo
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadCache();

        if (!isset(self::$cache[$key])) {
            return $default;
        }

        $setting = self::$cache[$key];

        return self::parseValue($setting['value'], $setting['type']);
    }

    /**
     * Establece un valor de configuración
     *
     * @param string      $key   Clave de configuración
     * @param mixed       $value Valor a guardar
     * @param string|null $type  Tipo de dato (se autodetecta si es null)
     * @return boolean
     */
    public static function set(string $key, mixed $value, ?string $type = null): bool
    {
        $instance = new self();

        // Autodetectar tipo si no se especifica
        if ($type === null) {
            $type = self::detectType($value);
        }

        // Convertir valor a string para almacenar
        $stringValue = self::valueToString($value, $type);

        // Verificar si existe
        $stmt = $instance->db->prepare('SELECT `key` FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $exists = $stmt->fetch() !== false;

        if ($exists) {
            // Actualizar
            $stmt = $instance->db->prepare('UPDATE settings SET `value` = :value, `type` = :type WHERE `key` = :key');
            $result = $stmt->execute([
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
            ]);
        } else {
            // Insertar
            $stmt = $instance->db->prepare(
                'INSERT INTO settings (`key`, `value`, `type`, `group_name`) VALUES (:key, :value, :type, :group_name)'
            );
            $result = $stmt->execute([
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
                'group_name' => 'general',
            ]);
        }

        // Limpiar caché
        self::clearCache();

        return $result;
    }

    /**
     * Obtiene todas las configuraciones de un grupo
     *
     * @param string $group Nombre del grupo
     * @return array Configuraciones del grupo
     */
    public static function getGroup(string $group): array
    {
        self::loadCache();

        $result = [];
        foreach (self::$cache as $key => $setting) {
            if ($setting['group_name'] === $group) {
                $result[$key] = self::parseValue($setting['value'], $setting['type']);
            }
        }

        return $result;
    }

    /**
     * Obtiene todas las configuraciones con metadatos
     *
     * @return array
     */
    public function findAll(): array
    {
        return $this->db->query('SELECT * FROM settings ORDER BY group_name, `key`')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene configuraciones por grupo con metadatos
     *
     * @param string $group Nombre del grupo
     * @return array
     */
    public function findByGroup(string $group): array
    {
        $stmt = $this->db->prepare('SELECT * FROM settings WHERE group_name = :group ORDER BY `key`');
        $stmt->execute(['group' => $group]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza múltiples configuraciones en lote
     *
     * @param array $settings Array de [key => value]
     * @return boolean
     */
    public function updateBatch(array $settings): bool
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE settings SET `value` = :value WHERE `key` = :key');

            foreach ($settings as $key => $value) {
                // Obtener tipo actual
                $typeStmt = $this->db->prepare('SELECT `type` FROM settings WHERE `key` = :key');
                $typeStmt->execute(['key' => $key]);
                $type = $typeStmt->fetchColumn();

                if (!$type) {
                    continue; // Skip si no existe
                }

                $stringValue = self::valueToString($value, $type);

                $stmt->execute([
                    'key' => $key,
                    'value' => $stringValue,
                ]);
            }

            $this->db->commit();
            self::clearCache();

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error('Error updating settings batch: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Elimina una configuración
     *
     * @param string $key
     * @return boolean
     */
    public function delete(string $key): bool
    {
        $stmt = $this->db->prepare('DELETE FROM settings WHERE `key` = :key');
        $result = $stmt->execute(['key' => $key]);

        if ($result) {
            self::clearCache();
        }

        return $result;
    }

    /**
     * Obtiene configuraciones públicas (para frontend)
     *
     * @return array
     */
    public static function getPublic(): array
    {
        $instance = new self();
        $stmt = $instance->db->query('SELECT `key`, `value`, `type` FROM settings WHERE is_public = 1');
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['key']] = self::parseValue($setting['value'], $setting['type']);
        }

        return $result;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Carga todas las configuraciones en caché
     */
    private static function loadCache(): void
    {
        if (self::$cacheLoaded) {
            return;
        }

        $instance = new self();
        $stmt = $instance->db->query('SELECT `key`, `value`, `type`, `group_name` FROM settings');
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($settings as $setting) {
            self::$cache[$setting['key']] = $setting;
        }

        self::$cacheLoaded = true;
    }

    /**
     * Limpia la caché
     */
    private static function clearCache(): void
    {
        self::$cache = [];
        self::$cacheLoaded = false;
    }

    /**
     * Parsea un valor según su tipo
     *
     * @param string $value Valor en string
     * @param string $type  Tipo de dato
     * @return mixed
     */
    private static function parseValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => (bool) (int) $value,
            'json' => \json_decode($value, true),
            default => $value
        };
    }

    /**
     * Convierte un valor a string para almacenar
     *
     * @param mixed  $value Valor a convertir
     * @param string $type  Tipo de dato
     *
     * @return false|string
     */
    private static function valueToString(mixed $value, string $type): string|false
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => \json_encode($value),
            default => (string) $value
        };
    }

    /**
     * Detecta el tipo de un valor
     *
     * @param mixed $value
     * @return string
     */
    private static function detectType(mixed $value): string
    {
        if (\is_bool($value)) {
            return 'boolean';
        }

        if (\is_int($value)) {
            return 'integer';
        }

        if (\is_array($value)) {
            return 'json';
        }

        return 'string';
    }
}
