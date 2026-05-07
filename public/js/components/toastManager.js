/**
 * Komorebi Toast Manager
 *
 * Alpine.js component: toastManager()
 *
 * Uso:
 *   Escucha eventos 'toast' desde cualquier componente Alpine:
 *     $dispatch('toast', { message: 'Texto', type: 'success' })
 *     $dispatch('toast', { title: 'Título', message: 'Texto', type: 'error', duration: 6000 })
 *
 *   O desde JS puro:
 *     window.dispatchEvent(new CustomEvent('toast', { detail: { message: '...', type: 'info' } }))
 *
 * Tipos: 'success' | 'error' | 'info' | 'warning'
 */
function toastManager() {
  return {
    toasts: [],
    _nextId: 1,

    /** @param {{ message: string, title?: string, type?: string, duration?: number }} detail */
    show(detail) {
      const id = this._nextId++;
      const duration = detail.duration ?? 4000;

      const icons = {
        success: 'bi bi-check-circle-fill',
        error: 'bi bi-x-circle-fill',
        info: 'bi bi-info-circle-fill',
        warning: 'bi bi-exclamation-triangle-fill',
      };

      const toast = {
        id,
        message: detail.message ?? '',
        title: detail.title ?? null,
        type: detail.type ?? 'info',
        icon: icons[detail.type] ?? icons.info,
        duration,
        visible: true,
      };

      this.toasts.push(toast);

      // Auto-dismiss
      if (duration > 0) {
        setTimeout(() => this.dismiss(id), duration);
      }
    },

    dismiss(id) {
      const idx = this.toasts.findIndex(t => t.id === id);
      if (idx !== -1) {
        this.toasts.splice(idx, 1);
      }
    },

    /** Expone escucha de evento global 'toast' */
    init() {
      window.addEventListener('toast', (e) => {
        this.show(e.detail ?? {});
      });
    },
  };
}

// Registro como Alpine.data (si Alpine ya cargó, registra directamente)
if (typeof Alpine !== 'undefined') {
  Alpine.data('toastManager', toastManager);
} else {
  // Registro diferido: se registra cuando Alpine se inicializa
  document.addEventListener('alpine:init', () => {
    Alpine.data('toastManager', toastManager);
  });
}
