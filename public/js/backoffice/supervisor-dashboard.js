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

      /**
       * Agrupa los ítems en estado 'ready' por tracker_code/reservation_id.
       * Devuelve [{tracker_code, total, ready, complete}].
       */
      get readyByTable() {
        var groups = {};
        var allOrders = this.pendingOrders.concat(this.kitchenOrders).concat(this.readyOrders);

        // Contar totales por tracker (todos los estados activos)
        allOrders.forEach(function (o) {
          var key = o.tracker_code || String(o.reservation_id);
          if (!groups[key]) { groups[key] = { tracker_code: key, total: 0, ready: 0 }; }
          groups[key].total++;
          if (o.status === 'ready') { groups[key].ready++; }
        });

        // Filtrar solo grupos que tengan al menos 1 listo
        return Object.values(groups).filter(function (g) { return g.ready > 0; });
      },

      /**
       * Reservas confirmadas que llegan en los próximos 30 min.
       * Usa r.unix_time (generado en PHP con strtotime).
       */
      get upcomingArrivals() {
        var nowTs = Math.floor(Date.now() / 1000);
        var horizon = nowTs + 1800; // 30 min
        return this.reservations.filter(function (r) {
          return r.status === 'confirmed' &&
            r.unix_time != null &&
            r.unix_time >= nowTs &&
            r.unix_time <= horizon;
        }).sort(function (a, b) { return a.unix_time - b.unix_time; });
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

      getOrderAge(order, field) {
        var f = field || 'created_ts';
        var ts = (order && order[f]) ? order[f] : (order && order.created_ts ? order.created_ts : null);
        if (!ts) return 0;
        return Math.floor((Date.now() / 1000 - ts) / 60);
      },

      getTimerClass(minutes) {
        if (minutes >= 12) return 'timer--critical';
        if (minutes >= 8) return 'timer--urgent';
        if (minutes >= 5) return 'timer--warning';
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
