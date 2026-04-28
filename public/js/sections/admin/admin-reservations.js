(function () {
  'use strict';

  function createReservationActions(config = {}) {
    return {
      csrfToken:           config.csrfToken || '',
      selectedReservation: null,
      modalInstance:       null,

      init() {
        const modalEl = document.getElementById('reservationModal');
        if (modalEl) {
          this.modalInstance = new bootstrap.Modal(modalEl);
          modalEl.addEventListener('hidden.bs.modal', () => {
            this.selectedReservation = null;
          });
        }
      },

      openModal(data) {
        this.selectedReservation = data;
        this.modalInstance?.show();
      },

      getStatusLabel(status) {
        const labels = {
          confirmed: 'Confirmada',
          pending:   'Pendiente',
          cancelled: 'Cancelada',
          completed: 'Completada',
        };
        return labels[status] || status;
      },

      async confirmReservation(id) {
        if (!confirm('¿Confirmar esta reserva?')) return;
        try {
          const res  = await fetch(`/api/v1/admin/reservations/${id}/confirm`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body:    new URLSearchParams({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Reserva confirmada correctamente');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al confirmar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },

      async cancelReservation(id) {
        if (!await KomorebiConfirm.delete('esta reserva')) return;
        try {
          const res  = await fetch(`/api/v1/admin/reservations/${id}/cancel`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body:    new URLSearchParams({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Reserva cancelada correctamente');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al cancelar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('reservationActions', createReservationActions);
  });

})();
