(function () {
  'use strict';

  function createReviewsMod(config = {}) {
    return {
      csrfToken:      config.csrfToken || '',
      processing:     [],
      selectedReview: null,
      rejectReason:   '',
      rejectModal:    null,

      init() {
        const modalEl = document.getElementById('rejectModal');
        if (modalEl) {
          this.rejectModal = new bootstrap.Modal(modalEl);
          modalEl.addEventListener('hidden.bs.modal', () => {
            this.selectedReview = null;
            this.rejectReason   = '';
          });
        }
      },

      async approve(reviewId) {
        if (this.processing.includes(reviewId)) return;
        this.processing.push(reviewId);
        try {
          const res  = await fetch(`/api/v1/admin/reviews/${reviewId}/approve`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Reseña aprobada correctamente');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al aprobar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.processing = this.processing.filter(id => id !== reviewId); }
      },

      openRejectModal(review) {
        this.selectedReview = review;
        this.rejectReason   = '';
        this.rejectModal?.show();
      },

      async reject() {
        if (!this.selectedReview) return;
        const reviewId = this.selectedReview.id;

        if (this.rejectReason.trim().length < 5) {
          KomorebiToast.error('El motivo debe tener al menos 5 caracteres');
          return;
        }
        if (this.rejectReason.length > 500) {
          KomorebiToast.error('El motivo no puede exceder 500 caracteres');
          return;
        }
        if (this.processing.includes(reviewId)) return;
        this.processing.push(reviewId);

        try {
          const res  = await fetch(`/api/v1/admin/reviews/${reviewId}/reject`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken, reason: this.rejectReason }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            this.rejectModal?.hide();
            KomorebiToast.success(data.data?.message || 'Reseña rechazada correctamente');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al rechazar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.processing = this.processing.filter(id => id !== reviewId); }
      },

      async deleteReview(reviewId) {
        if (!await KomorebiConfirm.delete('esta reseña')) return;
        if (this.processing.includes(reviewId)) return;
        this.processing.push(reviewId);
        try {
          const res = await fetch(`/api/v1/admin/reviews/${reviewId}`, {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken }),
          });
          if (res.ok && res.status === 204) {
            KomorebiToast.success('Reseña eliminada');
            globalThis.location.reload();
          } else {
            const data = await res.json().catch(() => ({}));
            KomorebiToast.error(data.detail || 'Error al eliminar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.processing = this.processing.filter(id => id !== reviewId); }
      },

      getReasonLength() { return this.rejectReason.length; },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('reviewsMod', createReviewsMod);
  });

})();
