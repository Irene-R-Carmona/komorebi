/**
 * Formatters Utility
 * Funciones puras para transformación de datos
 */
const Formatters = {
  /**
   * Formatea fecha ISO a formato local español
   */
  dateTime: (isoString) => {
    if (!isoString) return 'N/A';
    return new Date(isoString).toLocaleString('es-ES', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  },

  /**
   * Pretty print JSON
   */
  json: (data) => {
    if (!data) return 'N/A';
    try {
      const obj = typeof data === 'string' ? JSON.parse(data) : data;
      return JSON.stringify(obj, null, 2);
    } catch {
      return String(data);
    }
  },

  /**
   * Mapeo de acciones a nombres legibles
   */
  actionName: (() => {
    const map = {
      'create_user': 'Crear Usuario',
      'update_user': 'Actualizar Usuario',
      'delete_user': 'Eliminar Usuario',
      'toggle_user_active': 'Activar/Desactivar Usuario',
      'create_cafe': 'Crear Café',
      'update_cafe': 'Actualizar Café',
      'delete_cafe': 'Eliminar Café',
      'toggle_cafe_status': 'Activar/Desactivar Café',
      'create_product': 'Crear Producto',
      'update_product': 'Actualizar Producto',
      'delete_product': 'Eliminar Producto',
      'toggle_product_availability': 'Cambiar Disponibilidad'
    };

    return (action) => map[action] || action;
  })(),

  /**
   * Clases CSS para badges según tipo de acción
   */
  actionBadgeClass: (action) => {
    if (!action) return 'bg-secondary';
    if (action.startsWith('create')) return 'bg-success';
    if (action.startsWith('update')) return 'bg-info';
    if (action.startsWith('delete')) return 'bg-danger';
    if (action.startsWith('toggle')) return 'bg-warning';
    return 'bg-secondary';
  },

  /**
   * Paginación visible (lógica de ventana deslizante)
   */
  visiblePages: (currentPage, totalPages, maxVisible = 5) => {
    if (totalPages <= 1) return [];

    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
      start = Math.max(1, end - maxVisible + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
  },

  /**
   * Formatea tipos de eventos de autenticación
   */
  authEventType: (() => {
    const map = {
      'login': 'Login',
      'logout': 'Logout',
      'failed_login': 'Login Fallido',
      'password_reset': 'Reset Contraseña',
      'email_verified': 'Email Verificado',
      'session_revoked': 'Sesión Revocada',
      'lockout': 'Bloqueo de Cuenta',
      'password_change': 'Cambio de Contraseña',
      'mfa_enabled': '2FA Activado',
      'mfa_disabled': '2FA Desactivado'
    };
    return (eventType) => map[eventType] || eventType;
  })(),

  /**
   * Clases CSS para eventos de auth
   */
  authEventClass: (eventType) => {
    const map = {
      'login': 'bg-success',
      'logout': 'bg-secondary',
      'failed_login': 'bg-danger',
      'password_reset': 'bg-warning',
      'email_verified': 'bg-info',
      'session_revoked': 'bg-dark',
      'lockout': 'bg-danger',
      'password_change': 'bg-primary',
      'mfa_enabled': 'bg-success',
      'mfa_disabled': 'bg-warning'
    };
    return map[eventType] || 'bg-secondary';
  },

  /**
   * Formatea dispositivo con icono HTML
   * Devuelve string HTML seguro para x-html
   */
  deviceWithIcon: (deviceName) => {
    if (!deviceName) return '<span class="text-muted">Desconocido</span>';

    const icons = {
      'iPhone': 'bi-phone',
      'iPad': 'bi-tablet',
      'Android': 'bi-phone-fill',
      'Windows': 'bi-windows',
      'Mac': 'bi-apple',
      'Linux': 'bi-ubuntu',
      'Chrome': 'bi-google',
      'Firefox': 'bi-firefox',
      'Safari': 'bi-safari',
      'Edge': 'bi-edge',
      'Opera': 'bi-opera',
      'Samsung': 'bi-phone'
    };

    let iconClass = 'bi-device-unknown';

    // Buscar coincidencias parciales
    for (const [key, icon] of Object.entries(icons)) {
      if (deviceName.includes(key)) {
        iconClass = icon;
        break;
      }
    }

    return `<i class="bi ${iconClass} me-1"></i><span>${deviceName}</span>`;
  },

  /**
   * Calcula tasa de fallo
   */
  failureRate: (successful, failed) => {
    const total = (successful || 0) + (failed || 0);
    if (total === 0) return '0.0';
    return ((failed || 0) / total * 100).toFixed(1);
  },

  /**
   * Formatea fecha con segundos (para logs de seguridad)
   */
  dateTimeSeconds: (isoString) => {
    if (!isoString) return 'N/A';
    return new Date(isoString).toLocaleString('es-ES', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }
};

globalThis.Formatters = Formatters;
