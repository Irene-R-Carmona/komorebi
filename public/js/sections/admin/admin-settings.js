/**
 * Gestión de Configuración del Admin
 * ============================================================================
 * Sistema de manejo de configuración del sistema con Alpine.js.
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {

    Alpine.data('settingsManagement', (config = {}) => ({
      // ─────────────────────────────────────────────────────────────
      // ESTADO
      // ─────────────────────────────────────────────────────────────

      activeTab: config.activeTab || 'general',
      loading: true,
      saving: false,
      testingEmail: false,

      // Datos de configuración
      settings: {
        // General
        site_name: '',
        site_description: '',
        maintenance_mode: false,
        timezone: 'Europe/Madrid',
        default_language: 'es',
        items_per_page: 10,

        // Email
        smtp_enabled: false,
        smtp_host: '',
        smtp_port: 587,
        smtp_username: '',
        smtp_password: '',
        smtp_encryption: 'tls',
        from_email: '',
        from_name: '',
        support_email: '',

        // Reservations
        reservations_enabled: true,
        max_advance_days: 30,
        min_advance_hours: 2,
        cancellation_hours: 24,
        max_guests_per_reservation: 10,
        require_deposit: false,
        deposit_percentage: 20,
        reservation_duration: 60,

        // Security
        session_lifetime: 120,
        max_login_attempts: 5,
        lockout_duration: 15,
        require_email_verification: true,
        password_min_length: 8,
        password_require_special: true,
        enable_2fa: false
      },

      // UI State
      showSmtpPassword: false,
      originalSettings: {},

      // ─────────────────────────────────────────────────────────────
      // INICIALIZACIÓN
      // ─────────────────────────────────────────────────────────────

      async init() {
        await this.loadSettings();
        this.loading = false;
      },

      async loadSettings() {
        try {
          const response = await fetch('/admin/settings/data', {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (data.success && data.settings) {
            // Merge settings con defaults
            this.settings = {
              ...this.settings,
              ...data.settings
            };

            // Guardar copia para detectar cambios
            this.originalSettings = JSON.parse(JSON.stringify(this.settings));
          }
        } catch (error) {
          console.error('[Settings] Error loading:', error);
          KomorebiToast.error('Error al cargar configuración');
        }
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED
      // ─────────────────────────────────────────────────────────────

      get hasChanges() {
        return JSON.stringify(this.settings) !== JSON.stringify(this.originalSettings);
      },

      get sessionHours() {
        return (this.settings.session_lifetime / 60).toFixed(1);
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: GUARDAR
      // ─────────────────────────────────────────────────────────────

      async saveGroup(group) {
        this.saving = true;

        try {
          const groupSettings = this.getSettingsForGroup(group);

          const response = await fetch(`/admin/settings/group/${group}/update`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': KomorebiForm.getCsrfToken(),
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ settings: groupSettings })
          });

          const data = await response.json();

          if (response.ok && data.success) {
            // Actualizar copia original
            this.originalSettings = {
              ...this.originalSettings,
              ...groupSettings
            };

            KomorebiToast.success(data.message || 'Configuración guardada');
          } else {
            KomorebiToast.error(data.message || 'Error al guardar');
          }
        } catch (error) {
          console.error('[Settings] Save error:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.saving = false;
        }
      },

      getSettingsForGroup(group) {
        const mappings = {
          general: [
            'site_name', 'site_description', 'maintenance_mode',
            'timezone', 'default_language', 'items_per_page'
          ],
          email: [
            'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_username',
            'smtp_password', 'smtp_encryption', 'from_email', 'from_name', 'support_email'
          ],
          reservations: [
            'reservations_enabled', 'max_advance_days', 'min_advance_hours',
            'cancellation_hours', 'max_guests_per_reservation',
            'require_deposit', 'deposit_percentage', 'reservation_duration'
          ],
          security: [
            'session_lifetime', 'max_login_attempts', 'lockout_duration',
            'require_email_verification', 'password_min_length',
            'password_require_special', 'enable_2fa'
          ]
        };

        const keys = mappings[group] || [];
        const result = {};

        keys.forEach(key => {
          result[key] = this.settings[key];
        });

        return result;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: EMAIL TEST
      // ─────────────────────────────────────────────────────────────

      async testEmail() {
        if (!this.settings.smtp_enabled) {
          KomorebiToast.warning('Activa el envío de emails primero');
          return;
        }

        this.testingEmail = true;

        try {
          const response = await fetch('/admin/settings/test-email', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': KomorebiForm.getCsrfToken(),
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (response.ok && data.success) {
            KomorebiToast.success('Email de prueba enviado correctamente');
          } else {
            KomorebiToast.error(data.message || 'Error al enviar email de prueba');
          }
        } catch (error) {
          console.error('[Settings] Email test error:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.testingEmail = false;
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: VALIDACIONES ESPECIALES
      // ─────────────────────────────────────────────────────────────

      confirmMaintenanceMode(event) {
        if (this.settings.maintenance_mode) {
          const confirmed = confirm(
            '⚠️ ADVERTENCIA\n\n' +
            'Activar el modo mantenimiento deshabilitará el acceso público al sitio.\n' +
            'Solo los administradores podrán acceder.\n\n' +
            '¿Deseas continuar?'
          );

          if (!confirmed) {
            this.settings.maintenance_mode = false;
            // Forzar actualización del DOM
            this.$nextTick(() => {
              event.target.checked = false;
            });
          }
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: UTILIDADES
      // ─────────────────────────────────────────────────────────────

      formatNumber(num) {
        return KomorebiUI.formatNumber(num);
      },

      resetGroup(group) {
        // Restaurar valores originales del grupo
        const mappings = {
          general: ['site_name', 'site_description', 'maintenance_mode', 'timezone', 'default_language', 'items_per_page'],
          email: ['smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'from_email', 'from_name', 'support_email'],
          reservations: ['reservations_enabled', 'max_advance_days', 'min_advance_hours', 'cancellation_hours', 'max_guests_per_reservation', 'require_deposit', 'deposit_percentage', 'reservation_duration'],
          security: ['session_lifetime', 'max_login_attempts', 'lockout_duration', 'require_email_verification', 'password_min_length', 'password_require_special', 'enable_2fa']
        };

        const keys = mappings[group] || [];
        keys.forEach(key => {
          this.settings[key] = this.originalSettings[key];
        });
      }
    }));
  });

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminSettings] Module loaded');
  });

})();
