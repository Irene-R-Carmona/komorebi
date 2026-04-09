/**
 * Utilidades Comunes del Admin
 * ============================================================================
 * Funcionalidades compartidas para todas las vistas de administración.
 * Se carga automáticamente en el layout backoffice.
 *
 * Módulos:
 * - KomorebiToast: Sistema de notificaciones
 * - KomorebiConfirm: Diálogos de confirmación
 * - KomorebiForm: Utilidades de formularios
 * - KomorebiUI: Ayudas de interfaz
 * - Integraciones con Alpine.js
 *
 * @version 1.0.0
 * @requires Alpine.js
 */

(function () {
  'use strict';

  // ========================================================================
  // SISTEMA DE TOASTS
  // ========================================================================

  window.KomorebiToast = {
    container: null,
    toasts: [],
    counter: 0,

    /**
     * Inicializa el contenedor de toasts
     */
    init() {
      if (this.container) return this.container;

      this.container = document.createElement('div');
      this.container.className = 'toast-container-admin';
      this.container.setAttribute('aria-live', 'polite');
      this.container.setAttribute('aria-label', 'Notificaciones');
      document.body.appendChild(this.container);

      return this.container;
    },

    /**
     * Muestra un toast
     * @param {string} type - success|error|warning|info
     * @param {string} message - Mensaje a mostrar
     * @param {Object} options - Opciones adicionales
     * @returns {HTMLElement} Elemento del toast
     */
    show(type, message, options = {}) {
      this.init();

      const {
        duration = 5000,
        dismissible = true,
        icon = null
      } = options;

      const id = ++this.counter;

      const icons = {
        success: 'check-circle-fill',
        error: 'exclamation-circle-fill',
        warning: 'exclamation-triangle-fill',
        info: 'info-circle-fill'
      };

      const bgClasses = {
        success: 'text-bg-success',
        error: 'text-bg-danger',
        warning: 'text-bg-warning',
        info: 'text-bg-info'
      };

      const toastEl = document.createElement('div');
      toastEl.id = `toast-${id}`;
      toastEl.className = `toast align-items-center border-0 ${bgClasses[type] || bgClasses.info} toast-enter`;
      toastEl.setAttribute('role', 'alert');
      toastEl.setAttribute('aria-atomic', 'true');

      toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${icon || icons[type] || icons.info} me-2"></i>
                        <span>${this.escapeHtml(message)}</span>
                    </div>
                    ${dismissible ? `
                        <button type="button"
                                class="btn-close btn-close-white me-2 m-auto"
                                aria-label="Cerrar"
                                data-toast-dismiss="${id}">
                        </button>
                    ` : ''}
                </div>
            `;

      this.container.appendChild(toastEl);
      this.toasts.push({ id, element: toastEl });

      // Event listener para cerrar
      if (dismissible) {
        toastEl.querySelector(`[data-toast-dismiss="${id}"]`)
          ?.addEventListener('click', () => this.dismiss(id));
      }

      // Auto-dismiss
      if (duration > 0) {
        setTimeout(() => this.dismiss(id), duration);
      }

      return toastEl;
    },

    /**
     * Cierra un toast
     * @param {number} id - ID del toast
     */
    dismiss(id) {
      const index = this.toasts.findIndex(t => t.id === id);
      if (index === -1) return;

      const { element } = this.toasts[index];
      element.classList.remove('toast-enter');
      element.classList.add('toast-exit');

      setTimeout(() => {
        element.remove();
        this.toasts.splice(index, 1);
      }, 300);
    },

    /**
     * Cierra todos los toasts
     */
    dismissAll() {
      [...this.toasts].forEach(t => this.dismiss(t.id));
    },

    // Métodos de conveniencia
    success(message, options = {}) {
      return this.show('success', message, options);
    },

    error(message, options = {}) {
      return this.show('error', message, { duration: 8000, ...options });
    },

    warning(message, options = {}) {
      return this.show('warning', message, options);
    },

    info(message, options = {}) {
      return this.show('info', message, options);
    },

    /**
     * Escapa HTML para prevenir XSS
     */
    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  };

  // ========================================================================
  // CONFIRMATION DIALOGS
  // ========================================================================

  window.KomorebiConfirm = {
    /**
     * Muestra un diálogo de confirmación
     * @param {Object} options
     * @returns {Promise<boolean>}
     */
    async show(options = {}) {
      const {
        title = '¿Estás seguro?',
        message = 'Esta acción no se puede deshacer.',
        confirmText = 'Confirmar',
        cancelText = 'Cancelar',
        type = 'warning' // warning|danger|info
      } = options;

      // TODO: Implementar modal personalizado en futuras fases
      // Por ahora usamos confirm nativo con formato mejorado
      return confirm(`${title}\n\n${message}`);
    },

    /**
     * Confirmación de eliminación
     * @param {string} itemName - Nombre del elemento
     * @returns {Promise<boolean>}
     */
    async delete(itemName = 'este elemento') {
      return this.show({
        title: '¿Eliminar?',
        message: `¿Estás seguro de eliminar ${itemName}?\n\nEsta acción no se puede deshacer.`,
        confirmText: 'Sí, eliminar',
        type: 'danger'
      });
    },

    /**
     * Confirmación de cambio de estado
     * @param {string} itemName - Nombre del elemento
     * @param {boolean} currentState - Estado actual (true = activo)
     * @returns {Promise<boolean>}
     */
    async toggle(itemName = 'este elemento', currentState = true) {
      const action = currentState ? 'desactivar' : 'activar';
      return this.show({
        title: `¿${this.capitalize(action)}?`,
        message: `¿Estás seguro de ${action} ${itemName}?`,
        confirmText: `Sí, ${action}`,
        type: 'warning'
      });
    },

    capitalize(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    }
  };

  // ========================================================================
  // FORM UTILITIES
  // ========================================================================

  window.KomorebiForm = {
    /**
     * Obtiene el token CSRF del meta tag
     * @returns {string}
     */
    getCsrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    /**
     * Genera un slug a partir de texto
     * @param {string} text
     * @returns {string}
     */
    generateSlug(text) {
      if (!text) return '';

      return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Quitar acentos
        .replace(/ñ/g, 'n')
        .replace(/[^a-z0-9\s-]/g, '') // Solo alfanuméricos
        .trim()
        .replace(/\s+/g, '-') // Espacios a guiones
        .replace(/-+/g, '-') // Múltiples guiones a uno
        .replace(/^-|-$/g, ''); // Quitar guiones al inicio/final
    },

    /**
     * Valida un email
     * @param {string} email
     * @returns {boolean}
     */
    isValidEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    /**
     * Envía un formulario via fetch
     * @param {string} url
     * @param {FormData|Object} data
     * @param {Object} options
     * @returns {Promise<Object>}
     */
    async submit(url, data, options = {}) {
      const {
        method = 'POST',
        headers = {}
      } = options;

      // Convertir objeto a FormData si es necesario
      let body;
      if (data instanceof FormData) {
        body = data;
      } else if (data instanceof URLSearchParams) {
        body = data;
        headers['Content-Type'] = 'application/x-www-form-urlencoded';
      } else {
        body = JSON.stringify(data);
        headers['Content-Type'] = 'application/json';
      }

      // Añadir header AJAX
      headers['X-Requested-With'] = 'XMLHttpRequest';

      try {
        const response = await fetch(url, { method, body, headers });
        const result = await response.json();

        return {
          ok: response.ok,
          status: response.status,
          data: result,
          success: response.ok && result.success !== false
        };
      } catch (error) {
        console.error('Form submit error:', error);
        return {
          ok: false,
          status: 0,
          data: null,
          success: false,
          error
        };
      }
    },

    /**
     * Serializa un formulario a objeto
     * @param {HTMLFormElement} form
     * @returns {Object}
     */
    serialize(form) {
      const formData = new FormData(form);
      const data = {};

      for (const [key, value] of formData.entries()) {
        // Manejar arrays (name="items[]")
        if (key.endsWith('[]')) {
          const arrayKey = key.slice(0, -2);
          if (!data[arrayKey]) data[arrayKey] = [];
          data[arrayKey].push(value);
        } else {
          data[key] = value;
        }
      }

      return data;
    }
  };

  // ========================================================================
  // UI UTILITIES
  // ========================================================================

  window.KomorebiUI = {
    /**
     * Formatea número con separadores
     * @param {number} num
     * @param {string} locale
     * @returns {string}
     */
    formatNumber(num, locale = 'es-ES') {
      if (num === null || num === undefined) return '0';
      return new Intl.NumberFormat(locale).format(num);
    },

    /**
     * Formatea precio en yenes
     * @param {number} amount
     * @returns {string}
     */
    formatPrice(amount) {
      return `¥${this.formatNumber(amount)}`;
    },

    /**
     * Formatea fecha
     * @param {string|Date} date
     * @param {Object} options
     * @returns {string}
     */
    formatDate(date, options = {}) {
      if (!date) return '-';

      const defaults = {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      };

      try {
        return new Date(date).toLocaleDateString('es-ES', { ...defaults, ...options });
      } catch {
        return '-';
      }
    },

    /**
     * Formatea fecha y hora
     * @param {string|Date} date
     * @returns {string}
     */
    formatDateTime(date) {
      if (!date) return '-';

      try {
        return new Date(date).toLocaleString('es-ES', {
          day: 'numeric',
          month: 'short',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      } catch {
        return '-';
      }
    },

    /**
     * Trunca texto
     * @param {string} text
     * @param {number} length
     * @returns {string}
     */
    truncate(text, length = 100) {
      if (!text || text.length <= length) return text || '';
      return text.substring(0, length).trim() + '...';
    },

    /**
     * Debounce function
     * @param {Function} func
     * @param {number} wait
     * @returns {Function}
     */
    debounce(func, wait = 300) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },

    /**
     * Throttle function
     * @param {Function} func
     * @param {number} limit
     * @returns {Function}
     */
    throttle(func, limit = 300) {
      let inThrottle;
      return function executedFunction(...args) {
        if (!inThrottle) {
          func.apply(this, args);
          inThrottle = true;
          setTimeout(() => inThrottle = false, limit);
        }
      };
    },

    /**
     * Copia texto al portapapeles
     * @param {string} text
     * @returns {Promise<boolean>}
     */
    async copyToClipboard(text) {
      try {
        await navigator.clipboard.writeText(text);
        KomorebiToast.success('Copiado al portapapeles');
        return true;
      } catch {
        KomorebiToast.error('No se pudo copiar');
        return false;
      }
    },

    /**
     * Obtiene parámetros de URL
     * @returns {Object}
     */
    getUrlParams() {
      return Object.fromEntries(new URLSearchParams(window.location.search));
    },

    /**
     * Actualiza parámetros de URL sin recargar
     * @param {Object} params
     */
    updateUrlParams(params) {
      const url = new URL(window.location);
      Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
          url.searchParams.delete(key);
        } else {
          url.searchParams.set(key, value);
        }
      });
      window.history.replaceState({}, '', url);
    }
  };

  // ========================================================================
  // ALPINE.JS INTEGRATIONS
  // ========================================================================

  document.addEventListener('alpine:init', () => {

    // Store global para toasts (acceso desde cualquier componente Alpine)
    Alpine.store('toast', {
      show(type, message, options) {
        KomorebiToast.show(type, message, options);
      },
      success(message) {
        KomorebiToast.success(message);
      },
      error(message) {
        KomorebiToast.error(message);
      },
      warning(message) {
        KomorebiToast.warning(message);
      },
      info(message) {
        KomorebiToast.info(message);
      }
    });

    // Magic helper para formateo
    Alpine.magic('format', () => ({
      number: KomorebiUI.formatNumber,
      price: KomorebiUI.formatPrice,
      date: KomorebiUI.formatDate,
      datetime: KomorebiUI.formatDateTime,
      truncate: KomorebiUI.truncate
    }));

    // Magic helper para CSRF
    Alpine.magic('csrf', () => KomorebiForm.getCsrfToken());

    /**
     * Componente base para management views
     * Extiende este para crear componentes específicos
     */
    Alpine.data('baseManagement', (config = {}) => ({
      // Estado común
      loading: false,
      saving: false,
      items: config.items || [],

      // Búsqueda y filtros
      searchQuery: '',

      // Paginación
      currentPage: 1,
      perPage: config.perPage || 10,

      // UI
      toasts: [],

      // Métodos comunes
      showToast(type, message) {
        KomorebiToast.show(type, message);
      },

      async handleSubmit(url, data, options = {}) {
        const {
          successMessage = 'Guardado correctamente',
          errorMessage = 'Error al guardar',
          reload = true
        } = options;

        this.saving = true;

        try {
          const result = await KomorebiForm.submit(url, data);

          if (result.success) {
            this.showToast('success', result.data?.message || successMessage);

            if (reload) {
              setTimeout(() => window.location.reload(), 1000);
            }

            return { success: true, data: result.data };
          } else {
            this.showToast('error', result.data?.message || errorMessage);
            return { success: false, errors: result.data?.errors };
          }
        } catch (error) {
          console.error('Submit error:', error);
          this.showToast('error', 'Error de conexión');
          return { success: false, error };
        } finally {
          this.saving = false;
        }
      },

      async confirmAndDelete(url, itemName) {
        if (!await KomorebiConfirm.delete(itemName)) return false;

        const formData = new FormData();
        formData.append('csrf_token', KomorebiForm.getCsrfToken());

        return this.handleSubmit(url, formData, {
          successMessage: 'Eliminado correctamente'
        });
      },

      // Paginación helpers
      get totalPages() {
        return Math.ceil(this.filteredItems.length / this.perPage);
      },

      get paginatedItems() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredItems.slice(start, start + this.perPage);
      },

      get filteredItems() {
        // Override en componentes específicos
        return this.items;
      },

      goToPage(page) {
        this.currentPage = Math.max(1, Math.min(page, this.totalPages));
      }
    }));
  });

  // ========================================================================
  // INITIALIZATION
  // ========================================================================

  document.addEventListener('DOMContentLoaded', () => {
    // Inicializar sistema de toasts
    KomorebiToast.init();

    // Inicializar tooltips de Bootstrap
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (tooltips.length && typeof bootstrap !== 'undefined') {
      tooltips.forEach(el => new bootstrap.Tooltip(el));
    }

    // Log de inicialización
    console.log('[KomorebiAdmin] Common utilities loaded');
  });

})();
