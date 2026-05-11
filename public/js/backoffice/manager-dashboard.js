/**
 * Manager Dashboard - Alpine.js Component
 */

(function () {
  'use strict';

  /**
   * Alpine.js component para el dashboard del Manager
   * @param {Object} config - Configuración con chartData
   * @returns {Object} Alpine component instance
   */
  window.managerDashboard = function (config) {
    return {
      loading: false,
      chartData: config.chartData,
      revenueChart: null,
      statusChart: null,

      init() {
        this.$nextTick(() => {
          this.initCharts();
        });
      },

      initCharts() {
        // Chart.js Revenue Chart
        if (this.chartData.weekly_revenue && window.Chart) {
          const ctx = document.getElementById('revenueChart');
          if (ctx) {
            this.revenueChart = new Chart(ctx, {
              type: 'line',
              data: {
                labels: this.chartData.weekly_revenue.labels || [],
                datasets: [{
                  label: 'Ingresos (€)',
                  data: this.chartData.weekly_revenue.data || [],
                  borderColor: 'rgb(75, 192, 192)',
                  backgroundColor: 'rgba(75, 192, 192, 0.2)',
                  tension: 0.4
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    display: false
                  }
                }
              }
            });
          }
        }

        // Donut Chart for Reservation Status
        if (this.chartData.reservation_status && window.Chart) {
          const ctx = document.getElementById('statusChart');
          if (ctx) {
            const statuses = this.chartData.reservation_status;
            const labels = statuses.map(s => s.status || 'Sin estado');
            const data = statuses.map(s => parseInt(s.count) || 0);

            this.statusChart = new Chart(ctx, {
              type: 'doughnut',
              data: {
                labels: labels,
                datasets: [{
                  data: data,
                  backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)'
                  ]
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    position: 'bottom'
                  }
                }
              }
            });
          }
        }
      },

      /**
       * Destruir charts al desmontar el componente
       */
      destroy() {
        if (this.revenueChart) {
          this.revenueChart.destroy();
        }
        if (this.statusChart) {
          this.statusChart.destroy();
        }
      }
    };
  };

  console.log('[Manager Dashboard] Script loaded');
})();
