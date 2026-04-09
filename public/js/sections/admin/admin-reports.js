/**
 * Admin Reports Management
 * Alpine.js component para gestión de reportes y estadísticas
 */

(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {

    Alpine.data('reportsManagement', (config = {}) => ({
      loading: false,
      reportType: 'monthly',
      dateFrom: '',
      dateTo: '',
      charts: {
        reservations: null,
        popularCafes: null
      },

      init() {
        this.setDefaultDates();
        this.loadCharts();
      },

      setDefaultDates() {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        this.dateFrom = firstDay.toISOString().split('T')[0];
        this.dateTo = now.toISOString().split('T')[0];
      },

      async loadCharts() {
        this.loading = true;

        try {
          // Cargar Chart.js si no está disponible
          if (typeof Chart === 'undefined') {
            await this.loadChartJs();
          }

          this.initReservationsChart();
          this.initPopularCafesChart();
        } catch (error) {
          console.error('Error loading charts:', error);
        } finally {
          this.loading = false;
        }
      },

      loadChartJs() {
        return new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
          script.onload = resolve;
          script.onerror = reject;
          document.head.appendChild(script);
        });
      },

      initReservationsChart() {
        const ctx = document.getElementById('reservationsChart');
        if (!ctx) return;

        // Datos de ejemplo (reemplazar con datos reales del backend)
        const data = {
          labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
          datasets: [{
            label: 'Reservas',
            data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.4
          }]
        };

        this.charts.reservations = new Chart(ctx, {
          type: 'line',
          data: data,
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: true,
                position: 'top'
              },
              title: {
                display: false
              }
            },
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        });
      },

      initPopularCafesChart() {
        const ctx = document.getElementById('popularCafesChart');
        if (!ctx) return;

        // Datos de ejemplo (reemplazar con datos reales del backend)
        const data = {
          labels: ['Café Central', 'Café Bosque', 'Café Mar', 'Café Montaña', 'Café Ciudad'],
          datasets: [{
            label: 'Visitas',
            data: [120, 95, 80, 65, 50],
            backgroundColor: [
              'rgba(255, 99, 132, 0.7)',
              'rgba(54, 162, 235, 0.7)',
              'rgba(255, 206, 86, 0.7)',
              'rgba(75, 192, 192, 0.7)',
              'rgba(153, 102, 255, 0.7)'
            ],
            borderWidth: 1
          }]
        };

        this.charts.popularCafes = new Chart(ctx, {
          type: 'doughnut',
          data: data,
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                position: 'bottom'
              }
            }
          }
        });
      },

      async exportPDF() {
        this.loading = true;
        try {
          const response = await fetch('/api/admin/reports/export/pdf', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
              type: this.reportType,
              dateFrom: this.dateFrom,
              dateTo: this.dateTo
            })
          });

          if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte-${this.reportType}-${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showNotification('Reporte PDF exportado correctamente', 'success');
          } else {
            throw new Error('Error al exportar PDF');
          }
        } catch (error) {
          console.error('Export PDF error:', error);
          this.showNotification('Error al exportar el reporte PDF', 'error');
        } finally {
          this.loading = false;
        }
      },

      async exportExcel() {
        this.loading = true;
        try {
          const response = await fetch('/api/admin/reports/export/excel', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
              type: this.reportType,
              dateFrom: this.dateFrom,
              dateTo: this.dateTo
            })
          });

          if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte-${this.reportType}-${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showNotification('Reporte Excel exportado correctamente', 'success');
          } else {
            throw new Error('Error al exportar Excel');
          }
        } catch (error) {
          console.error('Export Excel error:', error);
          this.showNotification('Error al exportar el reporte Excel', 'error');
        } finally {
          this.loading = false;
        }
      },

      async exportCSV() {
        this.loading = true;
        try {
          const response = await fetch('/api/admin/reports/export/csv', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
              type: this.reportType,
              dateFrom: this.dateFrom,
              dateTo: this.dateTo
            })
          });

          if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte-${this.reportType}-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showNotification('Reporte CSV exportado correctamente', 'success');
          } else {
            throw new Error('Error al exportar CSV');
          }
        } catch (error) {
          console.error('Export CSV error:', error);
          this.showNotification('Error al exportar el reporte CSV', 'error');
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
