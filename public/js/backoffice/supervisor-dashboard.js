/**
 * Supervisor Dashboard - Alpine.js Component
 */

(function () {
  'use strict';

  /**
   * Alpine.js component para el dashboard del Supervisor
   * @returns {Object} Alpine component instance
   */
  window.supervisorDashboard = function () {
    return {
      refreshInterval: null,
      lastUpdate: null,

      init() {
        console.log('Supervisor Dashboard loaded');
        this.lastUpdate = new Date();

        // TODO: Implementar polling para actualizar mesas/órdenes en tiempo real
        // this.startPolling();
      },

      /**
       * Iniciar polling para datos en tiempo real (futuro)
       */
      startPolling() {
        // Actualizar cada 30 segundos
        this.refreshInterval = setInterval(() => {
          this.refreshData();
        }, 30000);
      },

      /**
       * Refrescar datos desde el servidor
       */
      async refreshData() {
        try {
          const response = await fetch('/api/supervisor/dashboard-data');
          if (!response.ok) throw new Error('Network error');

          const data = await response.json();
          // TODO: Actualizar reactive data

          this.lastUpdate = new Date();
          console.log('[Supervisor] Data refreshed', data);
        } catch (error) {
          console.error('[Supervisor] Error refreshing data:', error);
        }
      },

      /**
       * Limpiar interval al desmontar
       */
      destroy() {
        if (this.refreshInterval) {
          clearInterval(this.refreshInterval);
        }
      }
    };
  };

  console.log('[Supervisor Dashboard] Script loaded');
})();
