/**
 * DIETARY PREFERENCES HELPER
 * Auto-rellena preferencias dietéticas en el formulario de reservas
 * Requiere: CookieHelper
 */

let DietaryPreferencesHelper = {
  /**
   * Cargar preferencias dietéticas guardadas
   * @returns {object|null} - Objeto con alergias, vegano, sinGluten, notas
   */
  async load() {
    if (!globalThis.CookieHelper || !CookieHelper.hasConsent('functional')) {
      return null;
    }

    const json = CookieHelper.get('dietary_preferences');
    if (!json) return null;

    try {
      return JSON.parse(json);
    } catch (e) {
      console.error('Error parsing dietary preferences:', e);
      return null;
    }
  },

  /**
   * Guardar preferencias dietéticas
   * @param {object} prefs - {alergias: string[], vegano: boolean, sinGluten: boolean, notas: string}
   */
  async save(prefs) {
    if (!globalThis.CookieHelper || !CookieHelper.hasConsent('functional')) {
      return false;
    }

    try {
      const response = await fetch('/api/v1/cookies/dietary', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(prefs)
      });

      const contentType = response.headers.get('content-type') || '';

      // Manejar respuestas no exitosas sin asumir cuerpo JSON
      if (!response.ok) {
        try {
          if (contentType.includes('application/json')) {
            const errorData = await response.json();
            console.error('Error response saving dietary preferences:', errorData);
          } else {
            const errorText = await response.text();
            console.error('Error response saving dietary preferences (non-JSON):', errorText);
          }
        } catch (parseError) {
          console.error('Error parsing error response for dietary preferences:', parseError);
        }
        return false;
      }

      // Solo intentar parsear JSON en respuestas exitosas con tipo adecuado
      if (!contentType.includes('application/json')) {
        console.error('Expected JSON response when saving dietary preferences, got:', contentType);
        return false;
      }

      try {
        const data = await response.json();
        return !!(data?.success);
      } catch (parseError) {
        console.error('Error parsing JSON response for dietary preferences:', parseError);
        return false;
      }
    } catch (error) {
      console.error('Error saving dietary preferences:', error);
      return false;
    }
  },

  /**
   * Auto-rellenar campo de notas en formulario de reservas
   * @param {string} fieldSelector - Selector del campo de notas (ej: 'textarea[name="comentarios"]')
   */
  async autoFillReservationNotes(fieldSelector) {
    const prefs = this.load();
    if (!prefs) return;

    const field = document.querySelector(fieldSelector);
    if (!field) return;

    // Solo auto-rellenar si el campo está vacío
    if (field.value.trim()) return;

    let notes = [];

    if (prefs.alergias && prefs.alergias.length > 0) {
      notes.push(`ALERGIAS: ${prefs.alergias.join(', ')}`);
    }

    if (prefs.vegano) {
      notes.push('Vegano');
    }

    if (prefs.sinGluten) {
      notes.push('Sin gluten');
    }

    if (prefs.notas) {
      const alergiaMatch = /alergia[s]?:?\s*([^\n]+)/i.exec(notes);
    }

    if (notes.length > 0) {
      field.value = notes.join('\n');

      // Trigger Alpine.js reactivity if field is bound with x-model
      field.dispatchEvent(new Event('input', { bubbles: true }));
    }
  },

  /**
   * Extraer preferencias del campo de notas del formulario
   * @param {string} notes - Contenido del campo de notas
   * @returns {object} - Preferencias extraídas
   */
  extractFromNotes(notes) {
    const prefs = {
      alergias: [],
      vegano: false,
      sinGluten: false,
      notas: notes
    };

    if (!notes) return prefs;

    const lines = notes.toLowerCase();

    // Detectar alergias
    const alergiaMatch = new RegExp(/alergia[s]?:?\s*([^\n]+)/i).exec(notes);
    if (alergiaMatch) {
      prefs.alergias = alergiaMatch[1].split(/,\s*/).map(a => a.trim()).filter(Boolean);
    }

    // Detectar vegano
    if (/vegano|vegan/i.test(lines)) {
      prefs.vegano = true;
    }

    // Detectar sin gluten
    if (/sin gluten|celíaco|celiaco|gluten-free/i.test(lines)) {
      prefs.sinGluten = true;
    }

    return prefs;
  }
};

// Exportar globalmente
globalThis.DietaryPreferencesHelper = DietaryPreferencesHelper;

// Auto-ejecutar en páginas de reservas
document.addEventListener('DOMContentLoaded', () => {
  // Detectar si estamos en página de reservas
  if (globalThis.location.pathname.includes('/reservas')) {
    // Esperar a que el formulario se renderice (Alpine.js)
    setTimeout(() => {
      DietaryPreferencesHelper.autoFillReservationNotes('textarea[name="comentarios"]');
    }, 500);
  }
});
