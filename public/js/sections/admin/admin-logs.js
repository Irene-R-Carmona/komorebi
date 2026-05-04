/**
 * Gestión de Logs del Admin
 * ============================================================================
 * Componentes Alpine.js para logs de auditoría y autenticación
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {

    Alpine.data('auditLogsManagement', () => ({
      loading: false,
      logs: [],
      filters: {
        dateFrom: '',
        dateTo: '',
        action: '',
        user: ''
      },
      currentPage: 1,
      totalPages: 1,
      perPage: 25,

      init() {
        this.setDefaultDates();
        this.loadLogs();
      },

      setDefaultDates() {
        const now = new Date();
        const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        this.filters.dateFrom = weekAgo.toISOString().split('T')[0];
        this.filters.dateTo = now.toISOString().split('T')[0];
      },

      async loadLogs() {
        this.loading = true;

        try {
          const params = new URLSearchParams({
            page: this.currentPage.toString(),
            perPage: this.perPage.toString(),
            ...this.filters
          });

          const response = await fetch(`/admin/logs/audit?${params}`, {
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
          });

          if (response.ok) {
            const data = await response.json();
            this.logs = data.data || [];
            this.totalPages = Math.ceil((data.total || 0) / this.perPage) || 1;
          } else {
            const rawBody = await response.text().catch(() => '');
            const cuerpoResumido = rawBody.length > 500 ? rawBody.slice(0, 500) + '…' : rawBody;
            throw new Error(
              `Error al cargar logs de auditoría: estado ${response.status} ${response.statusText || ''}` +
              (cuerpoResumido ? ` | cuerpo: ${cuerpoResumido}` : '')
            );
          }
        } catch (error) {
          console.error('Load audit logs error:', error);
          this.logs = [];
          this.showNotification('Error al cargar los logs de auditoría', 'error');
        } finally {
          this.loading = false;
        }
      },

      applyFilters() {
        this.currentPage = 1;
        this.loadLogs();
      },

      clearFilters() {
        this.filters = {
          dateFrom: '',
          dateTo: '',
          action: '',
          user: ''
        };
        this.setDefaultDates();
        this.applyFilters();
      },

      async exportLogs() {
        this.loading = true;

        try {
          const params = new URLSearchParams(this.filters);
          const response = await fetch(`/admin/logs/audit/export?${params}`, {
            headers: {
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
          });

          if (!response.ok) {
            // Try to extract server message
            let serverMessage = '';
            try {
              const text = await response.text();
              serverMessage = text;
              try {
                const parsed = JSON.parse(text);
                serverMessage = parsed?.message || parsed?.error || serverMessage;
              } catch (_) {
                // keep text
              }
            } catch (_) {
              serverMessage = '';
            }
            const baseMessage = `Error al exportar los logs (HTTP ${response.status} ${response.statusText || ''})`.trim();
            const detailedMessage = serverMessage ? `${baseMessage}: ${serverMessage}` : baseMessage;
            this.showNotification(detailedMessage, 'error');
            return;
          }

          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `audit-logs-${new Date().toISOString().split('T')[0]}.csv`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);

          this.showNotification('Logs exportados correctamente', 'success');
        } catch (error) {
          console.error('Error al exportar los logs:', error);
          const userMessage = error && error.message
            ? `Error al exportar los logs: ${error.message}`
            : 'Error al exportar los logs. Por favor, inténtalo de nuevo más tarde.';
          this.showNotification(userMessage, 'error');
        } finally {
          this.loading = false;
        }
      },

      viewDetails(log) {
        // Abrir modal con detalles completos del log
        if (window.modalManager) {
          window.modalManager.showLogDetails(log);
        } else {
          alert(`Log ID: ${log.id}\nUsuario: ${log.user_name}\nAcción: ${log.action}\nRecurso: ${log.resource}\nDetalles: ${JSON.stringify(log.details, null, 2)}`);
        }
      },

      getActionBadge(action) {
        const badges = {
          'create': 'bg-success',
          'update': 'bg-info',
          'delete': 'bg-danger',
          'login': 'bg-primary',
          'logout': 'bg-secondary'
        };
        return badges[action] || 'bg-secondary';
      },

      showNotification(message, type = 'info') {
        if (window.notificationManager) {
          window.notificationManager.show(message, type);
        } else {
          alert(message);
        }
      }
    }));

    // ========================================================================
    // ALPINE COMPONENT: Auth Logs Management
    // ========================================================================

    Alpine.data('authLogsManagement', (config = {}) => ({
      loading: false,
      logs: [],
      suspiciousCount: 0,
      filters: {
        dateFrom: '',
        dateTo: '',
        status: '',
        event: '',
        search: ''
      },
      currentPage: 1,
      totalPages: 1,
      perPage: 25,

      async init() {
        this.setDefaultDates();
        await this.loadLogs();
        this.loadSuspiciousCount();
      },

      setDefaultDates() {
        const now = new Date();
        const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        this.filters.dateFrom = weekAgo.toISOString().split('T')[0];
        this.filters.dateTo = now.toISOString().split('T')[0];
      },

      async loadLogs() {
        this.loading = true;

        try {
          const params = new URLSearchParams({
            page: this.currentPage.toString(),
            perPage: this.perPage.toString(),
            ...this.filters
          });

          const response = await fetch(`/admin/logs/auth?${params}`, {
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
          });

          if (response.ok) {
            const data = await response.json();
            this.logs = data.data || [];
            this.totalPages = Math.ceil((data.total || 0) / this.perPage) || 1;
          } else {
            const raw = await response.text().catch(() => '');
            const summary = raw.length > 500 ? raw.slice(0, 500) + '…' : raw;
            throw new Error(`Error al cargar los logs de autenticación: ${response.status} ${response.statusText || ''}${summary ? ` | ${summary}` : ''}`);
          }
        } catch (error) {
          console.error('Load auth logs error:', error);
          this.logs = [];
          this.showNotification('Error al cargar los logs de autenticación', 'error');
        } finally {
          this.loading = false;
        }
      },

      async loadSuspiciousCount() {
        try {
          const response = await fetch('/admin/logs/auth/suspicious-count', {
            headers: {
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
          });

          if (response.ok) {
            const data = await response.json();
            this.suspiciousCount = data.count || 0;
          }
        } catch (error) {
          console.error('Load suspicious count error:', error);
        }
      },

      applyFilters() {
        this.currentPage = 1;
        this.loadLogs();
      },

      clearFilters() {
        this.filters = {
          dateFrom: '',
          dateTo: '',
          status: '',
          event: '',
          search: ''
        };
        this.setDefaultDates();
        this.applyFilters();
      },

      viewSuspicious() {
        this.filters.status = 'suspicious';
        this.applyFilters();
      },

      async exportLogs() {
        this.loading = true;

        try {
          const params = new URLSearchParams(this.filters);
          const response = await fetch(`/admin/logs/auth/export?${params}`, {
            headers: {
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
          });

          if (!response.ok) {
            let serverMessage = '';
            try {
              const text = await response.text();
              serverMessage = text;
              try {
                const parsed = JSON.parse(text);
                serverMessage = parsed?.message || parsed?.error || serverMessage;
              } catch (_) { }
            } catch (_) {
              serverMessage = '';
            }
            const baseMessage = `Error al exportar los logs (HTTP ${response.status} ${response.statusText || ''})`.trim();
            const detailedMessage = serverMessage ? `${baseMessage}: ${serverMessage}` : baseMessage;
            this.showNotification(detailedMessage, 'error');
            return;
          }

          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `auth-logs-${new Date().toISOString().split('T')[0]}.csv`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);

          this.showNotification('Logs exportados correctamente', 'success');
        } catch (error) {
          console.error('Export logs error:', error);
          this.showNotification('Error al exportar los logs', 'error');
        } finally {
          this.loading = false;
        }
      },

      viewDetails(log) {
        // Abrir modal con detalles completos del log
        if (window.modalManager) {
          window.modalManager.showLogDetails(log);
        } else {
          alert(`Log ID: ${log.id}\nUsuario: ${log.user_email}\nEvento: ${log.event_type}\nEstado: ${log.status}\nIP: ${log.ip_address}\nUser Agent: ${log.user_agent}`);
        }
      },

      async blockIP(ipAddress) {
        if (!confirm(`¿Está seguro de que desea bloquear la IP ${ipAddress}?`)) {
          return;
        }

        this.loading = true;

        try {
          const response = await fetch('/admin/security/block-ip', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ ip: ipAddress })
          });

          if (response.ok) {
            this.showNotification(`IP ${ipAddress} bloqueada correctamente`, 'success');
            this.loadLogs();
          } else {
            const raw = await response.text().catch(() => '');
            const msg = raw || `Error blocking IP (status ${response.status})`;
            this.showNotification(msg, 'error');
          }
        } catch (error) {
          console.error('Block IP error:', error);
          this.showNotification('Error al bloquear la IP', 'error');
        } finally {
          this.loading = false;
        }
      },

      showNotification(message, type = 'info') {
        if (window.notificationManager) {
          window.notificationManager.show(message, type);
        } else {
          alert(message);
        }
      }
    }));
  });
})();
