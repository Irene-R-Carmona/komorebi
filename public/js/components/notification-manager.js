/**
 * Notification Manager
 * Sistema de notificaciones toast reutilizable para el backoffice
 */

class NotificationManager {
  constructor() {
    this.container = null;
    this.init();
  }

  init() {
    // Crear contenedor de notificaciones si no existe
    if (!document.getElementById('notification-container')) {
      this.container = document.createElement('div');
      this.container.id = 'notification-container';
      this.container.className = 'position-fixed top-0 end-0 p-3';
      this.container.style.zIndex = '9999';
      document.body.appendChild(this.container);
    } else {
      this.container = document.getElementById('notification-container');
    }
  }

  /**
   * Mostrar notificación
   * @param {string} message - Mensaje a mostrar
   * @param {string} type - Tipo: success, error, warning, info
   * @param {number} duration - Duración en ms (0 = no auto-cerrar)
   */
  show(message, type = 'info', duration = 5000) {
    const toast = this.createToast(message, type);
    this.container.appendChild(toast);

    // Animar entrada
    setTimeout(() => {
      toast.classList.add('show');
    }, 10);

    // Auto-cerrar
    if (duration > 0) {
      setTimeout(() => {
        this.hide(toast);
      }, duration);
    }

    return toast;
  }

  createToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${this.getBootstrapVariant(type)} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${this.getIcon(type)} me-2"></i>
                    ${this.escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        `;

    // Cerrar al hacer clic en el botón
    toast.querySelector('.btn-close').addEventListener('click', () => {
      this.hide(toast);
    });

    return toast;
  }

  hide(toast) {
    toast.classList.remove('show');
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
  }

  getBootstrapVariant(type) {
    const variants = {
      'success': 'success',
      'error': 'danger',
      'warning': 'warning',
      'info': 'info'
    };
    return variants[type] || 'info';
  }

  getIcon(type) {
    const icons = {
      'success': 'check-circle-fill',
      'error': 'exclamation-circle-fill',
      'warning': 'exclamation-triangle-fill',
      'info': 'info-circle-fill'
    };
    return icons[type] || 'info-circle-fill';
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Métodos de conveniencia
  success(message, duration = 5000) {
    return this.show(message, 'success', duration);
  }

  error(message, duration = 7000) {
    return this.show(message, 'error', duration);
  }

  warning(message, duration = 6000) {
    return this.show(message, 'warning', duration);
  }

  info(message, duration = 5000) {
    return this.show(message, 'info', duration);
  }
}

// Crear instancia global
window.notificationManager = new NotificationManager();
