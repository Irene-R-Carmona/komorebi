/**
 * Gestión de Reseñas del Admin
 * ============================================================================
 * Sistema de moderación de reseñas con Alpine.js.
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {

    Alpine.data('reviewsModeration', (config = {}) => ({
      // ─────────────────────────────────────────────────────────────
      // ESTADO
      // ─────────────────────────────────────────────────────────────

      reviews: config.reviews || [],
      csrfToken: config.csrfToken || '',
      processing: [], // IDs de reseñas en proceso

      // Modal de rechazo
      selectedReview: null,
      rejectReason: '',
      rejectModal: null,

      // ─────────────────────────────────────────────────────────────
      // INICIALIZACIÓN
      // ─────────────────────────────────────────────────────────────

      init() {
        // Inicializar modal de rechazo
        const modalEl = document.getElementById('rejectModal');
        if (modalEl) {
          this.rejectModal = new bootstrap.Modal(modalEl);

          // Limpiar al cerrar
          modalEl.addEventListener('hidden.bs.modal', () => {
            this.selectedReview = null;
            this.rejectReason = '';
          });
        }

        console.log('[ReviewsModeration] Initialized with', this.reviews.length, 'reviews');
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED
      // ─────────────────────────────────────────────────────────────

      get pendingCount() {
        return this.reviews.filter(r => r.status === 'pending').length;
      },

      get filteredReviews() {
        // Por defecto mostrar solo pendientes, pero podría filtrar por estado
        return this.reviews.filter(r => r.status === 'pending');
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: ACCIONES
      // ─────────────────────────────────────────────────────────────

      async approve(reviewId) {
        if (this.processing.includes(reviewId)) return;

        this.processing.push(reviewId);

        try {
          const formData = new URLSearchParams({
            csrf_token: this.csrfToken,
            id: reviewId
          });

          const response = await fetch('/admin/reviews/approve', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (response.ok && data.success) {
            // Animar y remover
            this.animateAndRemove(reviewId, 'approved');
            KomorebiToast.success('Reseña aprobada correctamente');
          } else {
            KomorebiToast.error(data.message || 'Error al aprobar');
          }
        } catch (error) {
          console.error('[Reviews] Approve error:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.processing = this.processing.filter(id => id !== reviewId);
        }
      },

      openRejectModal(review) {
        this.selectedReview = review;
        this.rejectReason = '';
        this.rejectModal?.show();
      },

      async reject() {
        if (!this.selectedReview) return;

        const reviewId = this.selectedReview.id;

        // Validar motivo
        if (!this.rejectReason.trim() || this.rejectReason.length < 5) {
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
          const formData = new URLSearchParams({
            csrf_token: this.csrfToken,
            id: reviewId,
            reason: this.rejectReason
          });

          const response = await fetch('/admin/reviews/reject', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (response.ok && data.success) {
            this.rejectModal?.hide();
            this.animateAndRemove(reviewId, 'rejected');
            KomorebiToast.success('Reseña rechazada correctamente');
          } else {
            KomorebiToast.error(data.message || 'Error al rechazar');
          }
        } catch (error) {
          console.error('[Reviews] Reject error:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.processing = this.processing.filter(id => id !== reviewId);
        }
      },

      animateAndRemove(reviewId, newStatus) {
        // Actualizar estado para animación
        const review = this.reviews.find(r => r.id === reviewId);
        if (review) {
          review.status = newStatus;

          // Esperar animación y remover
          setTimeout(() => {
            const index = this.reviews.findIndex(r => r.id === reviewId);
            if (index > -1) {
              this.reviews.splice(index, 1);
            }
          }, 300);
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: UTILIDADES
      // ─────────────────────────────────────────────────────────────

      getInitials(name) {
        return (name || 'U').charAt(0).toUpperCase();
      },

      getStarArray(rating) {
        const stars = [];
        for (let i = 1; i <= 5; i++) {
          stars.push(i <= rating);
        }
        return stars;
      },

      formatDate(dateString) {
        return KomorebiUI.formatDate(dateString, {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      },

      getReasonLength() {
        return this.rejectReason.length;
      }
    }));
  });

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminReviews] Module loaded');
  });

})();
