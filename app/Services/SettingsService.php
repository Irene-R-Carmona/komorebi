<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Exceptions\ConfigurationException;
use App\Exceptions\DatabaseException;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Contracts\SettingsServiceInterface;
use Exception;
use Override;

/**
 * Servicio de gestión de configuración del sistema
 *
 * Encapsula toda la lógica de negocio relacionada con settings,
 * incluyendo validación, actualización y recuperación de configuraciones.
 *
 * @package Komorebi\Services
 */
final class SettingsService implements SettingsServiceInterface
{
    private SettingRepositoryInterface $settingRepo;

    /** @var array<string, mixed>|null Cache local construida desde el repositorio */
    private ?array $localCache = null;

    public function __construct(?SettingRepositoryInterface $settingRepo = null)
    {
        $this->settingRepo = $settingRepo ?? Container::make(SettingRepositoryInterface::class);
    }

    /**
     * Obtiene un valor de configuración desde el repositorio (sin DB estática)
     */
    private function getSetting(string $key, mixed $default = null): mixed
    {
        if ($this->localCache === null) {
            $this->localCache = [];
            foreach ($this->settingRepo->findAll() as $row) {
                $this->localCache[$row->key] = $row->value;
            }
        }

        return $this->localCache[$key] ?? $default;
    }

    /**
     * Obtiene todas las configuraciones del sistema
     *
     * @return array Configuraciones como array asociativo [key => value]
     */
    #[Override]
    public function getAll(): array
    {
        $allSettings = $this->settingRepo->findAll();

        $settings = [];
        foreach ($allSettings as $setting) {
            $settings[$setting->key] = Setting::get($setting->key);
        }

        return $settings;
    }

    /**
     * Obtiene una configuración específica por clave
     *
     * @param string     $key     Clave de la configuración
     * @param mixed|null $default Valor por defecto si no existe
     * @return mixed Valor de la configuración
     */
    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    /**
     * Actualiza una configuración individual
     *
     * @param string       $key    Clave de la configuración
     * @param mixed        $value  Nuevo valor
     * @param integer|null $userId ID del usuario que hace el cambio
     * @return boolean True si se actualizó correctamente
     * @throws DatabaseException Si falla la actualización
     */
    #[Override]
    public function update(string $key, mixed $value, ?int $userId = null): bool
    {
        // Obtener valor antiguo para auditoría
        $oldValue = Setting::get($key);

        // Actualizar valor
        $success = Setting::set($key, $value);

        if (!$success) {
            throw DatabaseException::transactionFailed("No se pudo actualizar la configuración '$key'");
        }

        // Log de auditoría
        AuditLog::log(
            'update_setting',
            'setting',
            null,
            ['key' => $key, 'old_value' => $oldValue],
            ['key' => $key, 'new_value' => $value],
            $userId
        );

        return true;
    }

    /**
     * Actualiza múltiples configuraciones de un grupo
     *
     * @param array        $settings Array asociativo [key => value]
     * @param string|null  $group    Nombre del grupo (para auditoría)
     * @param integer|null $userId   ID del usuario que hace el cambio
     * @return integer Número de configuraciones actualizadas
     * @throws DatabaseException Si falla la actualización masiva
     */
    #[Override]
    public function updateBulk(array $settings, ?string $group = null, ?int $userId = null): int
    {
        $updated = 0;
        $errors = [];

        foreach ($settings as $key => $value) {
            try {
                Setting::set($key, $value);
                $updated++;
            } catch (Exception) {
                $errors[] = $key;
            }
        }

        // Log de auditoría
        if ($updated > 0) {
            AuditLog::log(
                'bulk_update_settings',
                'setting',
                null,
                null,
                [
                    'group' => $group,
                    'count' => $updated,
                    'keys' => \array_keys($settings),
                ],
                $userId
            );
        }

        if (!empty($errors)) {
            throw DatabaseException::transactionFailed(
                "Se actualizaron $updated configuraciones con errores en: " . \implode(', ', $errors)
            );
        }

        return $updated;
    }

    /**
     * Obtiene configuraciones por grupo
     *
     * @param string $group Prefijo del grupo (ej: 'smtp_', 'app_')
     * @return array Configuraciones del grupo
     */
    #[Override]
    public function getByGroup(string $group): array
    {
        $allSettings = $this->getAll();

        return \array_filter($allSettings, static function ($key) use ($group) {
            return \str_starts_with($key, $group);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Verifica si el sistema de email SMTP está configurado y habilitado
     *
     * @return boolean True si SMTP está habilitado
     */
    #[Override]
    public function isSmtpEnabled(): bool
    {
        return (bool) $this->getSetting('smtp_enabled', false);
    }

    /**
     * Obtiene la configuración SMTP completa
     *
     * @return array Configuración SMTP
     */
    #[Override]
    public function getSmtpConfig(): array
    {
        return [
            'enabled' => $this->isSmtpEnabled(),
            'host' => $this->getSetting('smtp_host', ''),
            'port' => (int) $this->getSetting('smtp_port', 587),
            'username' => $this->getSetting('smtp_username', ''),
            'password' => $this->getSetting('smtp_password', ''),
            'from_email' => $this->getSetting('smtp_from_email', ''),
            'from_name' => $this->getSetting('smtp_from_name', 'Komorebi Café'),
            'encryption' => $this->getSetting('smtp_encryption', 'tls'),
        ];
    }

    /**
     * Verifica configuraciones del sistema
     *
     * @return array Resultados de validación
     */
    #[Override]
    public function validate(): array
    {
        $issues = [];

        // Verificar configuración de app
        if (empty($this->getSetting('app_name'))) {
            $issues[] = 'Nombre de la aplicación no configurado';
        }

        // Verificar configuración de email si está habilitado
        if ($this->isSmtpEnabled()) {
            $smtp = $this->getSmtpConfig();

            if (empty($smtp['host'])) {
                $issues[] = 'Servidor SMTP no configurado';
            }

            if (empty($smtp['username'])) {
                $issues[] = 'Usuario SMTP no configurado';
            }

            if (empty($smtp['from_email'])) {
                $issues[] = 'Email remitente no configurado';
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Obtiene estadísticas de configuración
     *
     * @return array Estadísticas
     */
    #[Override]
    public function getStats(): array
    {
        $allSettings = $this->settingRepo->findAll();

        $groups = [];
        foreach ($allSettings as $setting) {
            $prefix = \explode('_', $setting->key)[0];
            $groups[$prefix] = ($groups[$prefix] ?? 0) + 1;
        }

        return [
            'total' => \count($allSettings),
            'groups' => $groups,
            'smtp_enabled' => $this->isSmtpEnabled(),
        ];
    }

    /**
     * Restablece una configuración a su valor por defecto
     *
     * @param string $key
     * @return boolean
     * @throws ConfigurationException Si no existe valor por defecto
     */
    #[Override]
    public function resetToDefault(string $key): bool
    {
        $defaultValue = $this->getDefaults()[$key] ?? null;

        if ($defaultValue === null) {
            throw ConfigurationException::missingKey($key);
        }

        return Setting::set($key, $defaultValue);
    }

    /**
     * Obtiene los valores por defecto de configuración
     *
     * @return array Valores por defecto
     */
    private function getDefaults(): array
    {
        return [
            // App
            'app_name' => 'Komorebi Café',
            'app_url' => 'http://localhost',
            'app_timezone' => 'Europe/Madrid',
            'app_locale' => 'es_ES',

            // Email
            'smtp_enabled' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => 'Komorebi Café',

            // Sistema
            'maintenance_mode' => false,
            'debug_mode' => false,
        ];
    }
}
