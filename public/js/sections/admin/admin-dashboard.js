/**
 * Admin Dashboard - Refactorización 2026
 * ============================================================================
 * Lógica del dashboard con gráficos Chart.js y gestión de errores
 *
 * @version 2.0.0 - Elimina colores hardcodeados y mejora accesibilidad
 * @requires Alpine.js
 * @requires Chart.js
 */

(function () {
  'use strict';

  // Paleta de colores desde CSS variables
  const CHART_COLORS = {
    primary: getComputedStyle(document.documentElement).getPropertyValue('--coffee-dark').trim() || '#8B6F47',
    primaryLight: 'rgba(184, 147, 92, 0.1)',
    grid: 'rgba(0, 0, 0, 0.05)',
    text: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#78716C'
  };

  document.addEventListener('alpine:init', () => {
    Alpine.data('dashboard', (config = {}) => ({
      // Configuración inicial pasada desde el servidor
      chartData: config.chartData || {
        labels: [],
        values: []
      },

      chartInstance: null,
      chartError: false,
      loading: true,

      init() {
        // Validar Chart.js
        if (typeof Chart === 'undefined') {
          console.error('[Dashboard] Chart.js no está cargado');
          this.chartError = true;
          this.loading = false;
          return;
        }

        // Validar datos
        if (!this.chartData.labels || !this.chartData.values) {
          console.warn('[Dashboard] Datos del gráfico incompletos');
          this.chartError = true;
          this.loading = false;
          return;
        }

        this.initChart();
      },

      initChart() {
        const ctx = document.getElementById('dashboardChart');
        if (!ctx) {
          console.error('[Dashboard] Canvas #dashboardChart no encontrado');
          this.chartError = true;
          this.loading = false;
          return;
        }

        try {
          const canvasGradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
          canvasGradient.addColorStop(0, 'rgba(184, 147, 92, 0.2)');
          canvasGradient.addColorStop(1, 'rgba(184, 147, 92, 0.01)');

          this.chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
              labels: this.chartData.labels,
              datasets: [{
                label: 'Reservas',
                data: this.chartData.values,
                borderColor: CHART_COLORS.primary,
                backgroundColor: canvasGradient,
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#FFFFFF',
                pointBorderColor: CHART_COLORS.primary,
                pointBorderWidth: 2,
                pointHoverBackgroundColor: CHART_COLORS.primary,
                pointHoverBorderColor: '#FFFFFF',
                pointHoverBorderWidth: 2
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              interaction: {
                mode: 'index',
                intersect: false
              },
              plugins: {
                legend: {
                  display: false
                },
                tooltip: {
                  backgroundColor: 'rgba(0, 0, 0, 0.85)',
                  padding: 12,
                  cornerRadius: 6,
                  titleFont: {
                    size: 13,
                    weight: '600'
                  },
                  bodyFont: {
                    size: 13
                  },
                  displayColors: false,
                  callbacks: {
                    label: function (context) {
                      return `Reservas: ${context.parsed.y}`;
                    }
                  }
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  grid: {
                    color: CHART_COLORS.grid,
                    drawBorder: false
                  },
                  ticks: {
                    font: {
                      size: 11
                    },
                    color: CHART_COLORS.text,
                    padding: 8
                  }
                },
                x: {
                  grid: {
                    display: false,
                    drawBorder: false
                  },
                  ticks: {
                    font: {
                      size: 11,
                      weight: '500'
                    },
                    color: CHART_COLORS.text,
                    padding: 8
                  }
                }
              },
              animation: {
                duration: 800,
                easing: 'easeInOutCubic'
              }
            }
          });

          this.loading = false;
          console.log('[Dashboard] Gráfico inicializado correctamente');

        } catch (error) {
          console.error('[Dashboard] Error al crear gráfico:', error);
          this.chartError = true;
          this.loading = false;
        }
      },

      destroy() {
        if (this.chartInstance) {
          this.chartInstance.destroy();
          this.chartInstance = null;
        }
      }
    }));
  });

  console.log('[Dashboard] Módulo cargado');
})();
