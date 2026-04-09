/**
 * Delete Confirmation Modal Component
 * Modal reutilizable para confirmaciones de eliminación en el backoffice
 */

function deleteConfirmationModal() {
  return {
    isOpen: false,
    title: '¿Confirmar eliminación?',
    message: 'Esta acción no se puede deshacer.',
    itemName: '',
    itemType: '',
    deleteUrl: '',
    csrfToken: '',
    isDeleting: false,
    onSuccess: null,
    onCancel: null,

    /**
     * Abrir modal de confirmación
     * @param {Object} options - Opciones del modal
     * @param {string} options.title - Título del modal
     * @param {string} options.message - Mensaje de confirmación
     * @param {string} options.itemName - Nombre del elemento a eliminar
     * @param {string} options.itemType - Tipo de elemento (usuario, café, producto, etc.)
     * @param {string} options.deleteUrl - URL del endpoint de eliminación
     * @param {string} options.csrfToken - Token CSRF
     * @param {Function} options.onSuccess - Callback al eliminar correctamente
     * @param {Function} options.onCancel - Callback al cancelar
     */
    open(options = {}) {
      this.title = options.title || '¿Confirmar eliminación?';
      this.message = options.message || 'Esta acción no se puede deshacer.';
      this.itemName = options.itemName || '';
      this.itemType = options.itemType || 'elemento';
      this.deleteUrl = options.deleteUrl || '';
      this.csrfToken = options.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.onSuccess = options.onSuccess || null;
      this.onCancel = options.onCancel || null;
      this.isOpen = true;
    },

    close() {
      if (this.isDeleting) return; // No cerrar si está eliminando

      this.isOpen = false;

      if (typeof this.onCancel === 'function') {
        this.onCancel();
      }

      // Reset después de cerrar
      setTimeout(() => this.reset(), 300);
    },

    reset() {
      this.title = '¿Confirmar eliminación?';
      this.message = 'Esta acción no se puede deshacer.';
      this.itemName = '';
      this.itemType = '';
      this.deleteUrl = '';
      this.isDeleting = false;
      this.onSuccess = null;
      this.onCancel = null;
    },

    async confirmDelete() {
      if (!this.deleteUrl) {
        console.error('Delete URL not provided');
        return;
      }

      this.isDeleting = true;

      try {
        const response = await fetch(this.deleteUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': this.csrfToken
          },
          body: JSON.stringify({
            _method: 'DELETE'
          })
        });

        const data = await response.json();

        if (response.ok) {
          this.showNotification(
            data.message || `${this.itemType} eliminado correctamente`,
            'success'
          );

          this.isOpen = false;

          if (typeof this.onSuccess === 'function') {
            this.onSuccess(data);
          }

          setTimeout(() => this.reset(), 300);
        } else {
          throw new Error(data.message || 'Error al eliminar');
        }
      } catch (error) {
        console.error('Delete error:', error);
        this.showNotification(
          error.message || 'Error al eliminar el elemento',
          'error'
        );
      } finally {
        this.isDeleting = false;
      }
    },

    showNotification(message, type = 'info') {
      if (window.notificationManager) {
        window.notificationManager.show(message, type);
      } else {
        alert(message);
      }
    }
  };
}

// Instancia global del modal de confirmación de eliminación
window.deleteModal = deleteConfirmationModal();
