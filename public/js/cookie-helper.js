/**
 * COOKIE HELPERS
 * Utilidades JavaScript para trabajar con cookies del lado del cliente
 */

let CookieHelper = {
  /**
   * Obtener el valor de una cookie
   * @param {string} name - Nombre de la cookie
   * @returns {string|null} - Valor de la cookie o null si no existe
   */
  get(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
      return decodeURIComponent(parts.pop().split(';').shift());
    }
    return null;
  },

  /**
   * Verificar si existe una cookie
   * @param {string} name - Nombre de la cookie
   * @returns {boolean}
   */
  has(name) {
    return this.get(name) !== null;
  },

  /**
   * Verificar si el usuario ha dado consentimiento para una categoría
   * @param {string} category - 'essential', 'functional', 'analytics'
   * @returns {boolean}
   */
  hasConsent(category) {
    // Essential siempre true
    if (category === 'essential') return true;

    const consent = this.get('cookie_consent');
    if (!consent) return false;

    try {
      const preferences = JSON.parse(consent);
      return preferences[category] === true;
    } catch (e) {
      console.error('Error parsing cookie consent:', e);
      return false;
    }
  },

  /**
   * Guardar filtros del catálogo (requiere consentimiento funcional)
   * @param {object} filters - {origen: [], tostado: [], precio: {min, max}}
   */
  async saveFilters(filters) {
    if (!this.hasConsent('functional')) {
      console.warn('Cannot save filters: functional cookies not allowed');
      return false;
    }

    try {
      const response = await fetch('/api/cookies/save-filters', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(filters)
      });

      const data = await response.json();
      return data.success;
    } catch (error) {
      console.error('Error saving filters:', error);
      return false;
    }
  },

  /**
   * Obtener filtros guardados del catálogo
   * @returns {object|null} - Filtros guardados o null
   */
  async getFilters() {
    try {
      const response = await fetch('/api/cookies/get-filters', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await response.json();
      return data.success ? data.filters : null;
    } catch (error) {
      console.error('Error getting filters:', error);
      return null;
    }
  },

  /**
   * Añadir producto a vistos recientemente (cliente)
   * Nota: También debe hacerse en servidor para persistencia
   * @param {number} productId - ID del producto
   */
  addRecentlyViewed(productId) {
    if (!this.hasConsent('functional')) return;

    const existingJson = this.get('recently_viewed');
    let items = [];

    try {
      if (existingJson) {
        items = JSON.parse(existingJson);
      }
    } catch (e) {
      console.error('Error parsing recently viewed:', e);
      items = [];
    }

    // Eliminar duplicado si existe
    items = items.filter(id => id !== productId);

    // Añadir al inicio
    items.unshift(productId);

    // Mantener solo los últimos 10
    items = items.slice(0, 10);

    // Guardar
    // La cookie ya se guarda en servidor, esto es solo para sync inmediato
    console.log('Recently viewed:', items);
  },

  /**
   * Obtener productos vistos recientemente
   * @returns {array} - Array de IDs de productos
   */
  getRecentlyViewed() {
    if (!this.hasConsent('functional')) return [];

    const json = this.get('recently_viewed');
    if (!json) return [];

    try {
      return JSON.parse(json);
    } catch (e) {
      console.error('Error parsing recently viewed:', e);
      return [];
    }
  },

  /**
   * Verificar si ya se mostró el popup de newsletter
   * @returns {boolean}
   */
  wasNewsletterPrompted() {
    if (!this.hasConsent('functional')) return false;
    return this.get('newsletter_prompted') === '1';
  }
};

// Exportar globalmente
globalThis.CookieHelper = CookieHelper;
