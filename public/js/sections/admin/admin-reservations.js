/**
 * Gestión de Reservas del Admin
 * ============================================================================
 * Sistema de manejo de reservas con Alpine.js.
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {

    Alpine.data('reservationManagement', (config = {}) => ({
      // Estado
      reservations: config.reservations || [],
      csrfToken: config.csrfToken || '',

      // Filtros
      searchQuery: '',
      filterStatus: '',
      filterCafe: '',
      filterDateFrom: '',
      filterDateTo: '',

      // Modal
      selectedReservation: null,
      modalInstance: null,

      init() {
        // Inicializar modal
        const modalEl = document.getElementById('reservationModal');
        if (modalEl) {
          this.modalInstance = new bootstrap.Modal(modalEl);
        }
      },

      get filteredReservations() {
        let filtered = [...this.reservations];

        // Filtro por estado
        if (this.filterStatus) {
          filtered = filtered.filter(r => r.status === this.filterStatus);
        }

        // Filtro por café
        if (this.filterCafe) {
          filtered = filtered.filter(r => r.cafe_name === this.filterCafe);
        }

        // Filtro por fecha
        if (this.filterDateFrom) {
          filtered = filtered.filter(r => r.reservation_date >= this.filterDateFrom);
        }
        if (this.filterDateTo) {
          filtered = filtered.filter(r => r.reservation_date <= this.filterDateTo);
        }

        // Búsqueda
        if (this.searchQuery) {
          const query = this.searchQuery.toLowerCase();
          filtered = filtered.filter(r =>
            r.customer_name?.toLowerCase().includes(query) ||
            r.customer_email?.toLowerCase().includes(query) ||
            r.cafe_name?.toLowerCase().includes(query) ||
            r.id?.toString().includes(query)
          );
        }

        return filtered;
      },

      get uniqueCafes() {
        const cafes = [...new Set(this.reservations.map(r => r.cafe_name).filter(Boolean))];
        return cafes.sort();
      },

      getStatusBadgeClass(status) {
        const classes = {
          'confirmed': 'badge-reservation--confirmed',
          'pending': 'badge-reservation--pending',
          'cancelled': 'badge-reservation--cancelled',
          'completed': 'badge-reservation--completed'
        };
        return classes[status] || 'bg-secondary';
      },

      getStatusLabel(status) {
        const labels = {
          'confirmed': 'Confirmada',
          'pending': 'Pendiente',
          'cancelled': 'Cancelada',
          'completed': 'Completada'
        };
        return labels[status] || status;
      },

      openModal(reservation) {
        this.selectedReservation = reservation;
        this.modalInstance?.show();
      },

      async cancelReservation(id) {
        if (!await KomorebiConfirm.delete('esta reserva')) {
          return;
        }

        try {
          const formData = new URLSearchParams({
            csrf_token: this.csrfToken
          });

          const response = await fetch(`/api/v1/admin/reservations/${id}/cancel`, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (response.ok && data.ok) {
            // Actualizar estado local
            const reservation = this.reservations.find(r => r.id === id);
            if (reservation) {
              reservation.status = 'cancelled';
            }
            KomorebiToast.success('Reserva cancelada correctamente');
          } else {
            KomorebiToast.error(data.message || 'Error al cancelar');
          }
        } catch (error) {
          KomorebiToast.error('Error de conexión');
        }
      },

      async confirmReservation(id) {
        if (!confirm('¿Confirmar esta reserva?')) {
          return;
        }

        try {
          const formData = new URLSearchParams({
            csrf_token: this.csrfToken
          });

          const response = await fetch(`/api/v1/admin/reservations/${id}/confirm`, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (response.ok && data.ok) {
            // Actualizar estado local
            const reservation = this.reservations.find(r => r.id === id);
            if (reservation) {
              reservation.status = 'confirmed';
            }
            KomorebiToast.success('Reserva confirmada correctamente');
          } else {
            KomorebiToast.error(data.message || 'Error al confirmar');
          }
        } catch (error) {
          KomorebiToast.error('Error de conexión');
        }
      },

      formatDate(date) {
        return KomorebiUI.formatDate(date);
      },

      formatDateTime(date, time) {
        if (!date) return '-';
        return `${this.formatDate(date)} ${time || ''}`;
      }
    }));
  });

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminReservations] Module loaded');
  });

})();
