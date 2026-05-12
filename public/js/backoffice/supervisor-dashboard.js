/**
 * Supervisor Dashboard - Alpine.js Component
 *
 * Recibe datos iniciales desde PHP (Raw::json) y se actualiza en tiempo real
 * via Mercure SSE. Cuando llega un evento se refresca desde /supervisor/dashboard/data.
 *
 * @param {Object} initialData - Datos iniciales serializados desde PHP
 */

(function () {
  'use strict';

  /**
   * @param {{
   *   reservations: Array,
   *   activeTables: Array,
   *   pendingOrders: Array,
   *   kitchenOrders: Array,
   *   readyOrders: Array,
   * }} initialData
   */
  window.supervisorDashboard = function (initialData) {
    return {
      reservations: (initialData && initialData.reservations) || [],
      activeTables: (initialData && initialData.activeTables) || [],
      pendingOrders: (initialData && initialData.pendingOrders) || [],
      kitchenOrders: (initialData && initialData.kitchenOrders) || [],
      readyOrders: (initialData && initialData.readyOrders) || [],
      reservationFilter: '',
      _tick: 0,
      lastUpdate: null,
      eventSource: null,

      get filteredReservations() {
        if (!this.reservationFilter) return this.reservations;
        return this.reservations.filter(r => r.status === this.reservationFilter);
      },

      init() {
        this.lastUpdate = new Date();
        this.connectMercure();
        setInterval(() => { this._tick++; }, 30000);
      },

      connectMercure() {
        if (!window.__MERCURE__ || !window.__MERCURE__.hub) return;

        const cafeId = window.__MERCURE__.cafeId;
        const url = new URL(window.__MERCURE__.hub, window.location.origin);
        url.searchParams.append('topic', 'reception/' + cafeId + '/reservations');
        url.searchParams.append('topic', 'kds/' + cafeId + '/orders');

        this.eventSource = new EventSource(url.toString());

        this.eventSource.onmessage = () => {
          this.refreshData();
        };

        this.eventSource.onerror = (e) => {
          console.warn('[Supervisor] SSE connection error, retrying in 5s', e);
          this.eventSource.close();
          setTimeout(() => this.connectMercure(), 5000);
        };
      },

      async refreshData() {
        try {
          const response = await fetch('/supervisor/dashboard/data', {
            headers: { 'Accept': 'application/json' },
          });

          if (!response.ok) {
            console.warn('[Supervisor] refreshData HTTP', response.status);
            return;
          }

          const data = await response.json();
          this.reservations = data.reservations || [];
          this.activeTables = data.activeTables || [];
          this.pendingOrders = data.pendingOrders || [];
          this.kitchenOrders = data.kitchenOrders || [];
          this.readyOrders = data.readyOrders || [];
          this.lastUpdate = new Date();
        } catch (error) {
          console.error('[Supervisor] Error refreshing data:', error);
        }
      },

      getOrderAge(order) {
        if (!order || !order.created_ts) return 0;
        return Math.floor((Date.now() / 1000 - order.created_ts) / 60);
      },

      getTimerClass(minutes) {
        if (minutes >= 15) return 'timer--critical';
        if (minutes >= 10) return 'timer--urgent';
        if (minutes >= 6) return 'timer--warning';
        return '';
      },

      destroy() {
        if (this.eventSource) {
          this.eventSource.close();
        }
      },
    };
  };

  console.log('[Supervisor Dashboard] Script loaded');
})();
