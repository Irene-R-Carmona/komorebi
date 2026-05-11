document.addEventListener('alpine:init', () => {
  Alpine.data('menuApp', (catInicial) => ({
    // UI
    activeTab: catInicial,
    search: '',
    showAllergenFilter: false,

    // Simulador de precios (local, sin API)
    simulatorItems: {},
    simulatorTotal: 0,
    productDict: {},

    // Filtros
    excludedAllergens: [],
    selectedCafeType: null, // null = todos, 'lounge' | 'playroom' | 'farm' | 'zen'

    init() {
      // Cargar diccionario de productos desde el DOM
      const meta = document.getElementById('komorebi-page-meta');
      if (meta && meta.dataset.productDict) {
        try {
          this.productDict = JSON.parse(meta.dataset.productDict);
        } catch (e) {
          console.warn('Error al parsear productDict:', e);
        }
      }

      // Leer parámetros de alérgenos desde la URL
      try {
        const params = new URLSearchParams(globalThis.location.search);
        const list = params.getAll('exclude_allergens[]');
        if ((Array.isArray(this.excludedAllergens) && this.excludedAllergens.length === 0) && list.length > 0) {
          this.excludedAllergens = list;
        }
      } catch (e) {
        console.warn('No se pudieron leer los parámetros de la URL:', e);
      }

      if (Array.isArray(this.excludedAllergens) && this.excludedAllergens.length > 0) {
        this.applyAllergenFilter();
      }
    },

    // ----------------------------
    // Simulador de precios
    // ----------------------------

    updateQty(productId, change) {
      productId = Number.parseInt(productId, 10);
      change = Number.parseInt(change, 10);
      if (!Number.isFinite(productId) || !Number.isFinite(change)) return;

      const current = this.simulatorItems[productId] || 0;
      const next = current + change;
      if (next < 0 || next > 99) return;

      if (next === 0) {
        delete this.simulatorItems[productId];
      } else {
        this.simulatorItems[productId] = next;
      }

      // Recalcular total en céntimos
      let total = 0;
      for (const [id, qty] of Object.entries(this.simulatorItems)) {
        const prod = this.productDict[id];
        if (prod) total += prod.price * qty;
      }
      this.simulatorTotal = total;
    },

    getQty(productId) {
      return (this.simulatorItems && this.simulatorItems[productId]) || 0;
    },

    simulatorTotalQty() {
      return Object.values(this.simulatorItems).reduce((a, b) => a + b, 0);
    },

    // ----------------------------
    // UI helpers
    // ----------------------------

    setTab(id) {
      this.activeTab = id;
      this.search = '';
    },

    /**
     * Búsqueda y filtrado robusto leyendo data-* del artículo:
     * - data-name (búsqueda texto)
     * - data-desc (búsqueda texto)
     * - data-cafe-types (filtrado por tipo de café: lounge, playroom, farm, zen)
     */
    matchesNode(el) {
      // Filtro de búsqueda texto
      if (this.search) {
        const q = this.search.toLowerCase();
        const name = (el && el.dataset && el.dataset.name || '').toLowerCase();
        const desc = (el && el.dataset && el.dataset.desc || '').toLowerCase();

        if (!name.includes(q) && !desc.includes(q)) {
          return false;
        }
      }

      // Filtro de tipos de café
      if (this.selectedCafeType !== null && this.selectedCafeType !== '') {
        // Obtener atributo data-cafe-types
        const cafeTypesAttr = el && el.dataset && el.dataset.cafeTypes;

        // Si tiene el atributo y no está vacío, verificar si coincide
        if (cafeTypesAttr && cafeTypesAttr.trim() !== '') {
          const types = cafeTypesAttr.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
          const selectedType = this.selectedCafeType.toLowerCase();
          if (!types.includes(selectedType)) {
            return false; // NO mostrar si el tipo no coincide
          }
        }
        // Si no tiene atributo = disponible en TODOS los cafés → continuar filtros
      }

      // Filtro de alérgenos (integrado en matchesNode para que Alpine reactive lo detecte)
      if (this.excludedAllergens.length > 0) {
        const excludedSet = new Set(this.excludedAllergens.map(String));
        if (this.cardHasExcludedAllergen(el, excludedSet)) return false;
      }

      return true; // Mostrar si pasa todos los filtros
    },

    /**
     * Cuenta cuántos productos visibles hay dentro del grid actual,
     * para mostrar un mensaje “sin resultados” fiable.
     */
    visibleCount(containerEl) {
      if (!containerEl) return 0;
      const cards = containerEl.querySelectorAll('article.producto-card');
      let count = 0;
      cards.forEach((card) => {
        if (this.matchesNode(card)) count++;
      });
      return count;
    },

    // ----------------------------
    // Filtro de alérgenos
    // ----------------------------
    clearAllergenFilters() {
      this.excludedAllergens = [];
      this.applyAllergenFilter();
    },

    updateAllergenUrl() {
      const params = new URLSearchParams();
      for (const id of this.excludedAllergens) {
        params.append('exclude_allergens[]', id);
      }

      const url = params.toString() ? `/menu?${params.toString()}` : '/menu';
      globalThis.history.replaceState({}, '', url);
    },

    cardHasExcludedAllergen(card, excludedSet) {
      const dataAllergens = card.dataset.allergens || '';
      if (!dataAllergens) {
        return false;
      }

      const ids = dataAllergens.split(',').map(s => s.trim()).filter(Boolean);
      for (const id of ids) {
        if (excludedSet.has(String(id))) {
          return true;
        }
      }

      return false;
    },

    applyAllergenFilter() {
      try {
        // Actualizar la URL sin recargar
        this.updateAllergenUrl();
        // La visibilidad se gestiona reactivamente por Alpine x-show="matchesNode($el)"
        // que ahora incluye el check de alérgenos
      } catch (e) {
        console.error('Error applying allergen filter:', e);
      }
    }
  }));
});
