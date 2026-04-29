document.addEventListener('alpine:init', () => {
  Alpine.data('menuApp', (catInicial) => ({
    // UI
    activeTab: catInicial,
    search: '',
    showComanda: false,
    showAllergenFilter: false,

    // Estado carrito
    cart: { items: {}, total_qty: 0, totalPrice: 0 },
    loading: true,

    // Filtros
    excludedAllergens: [],
    selectedCafeType: null, // null = todos, 'lounge' | 'playroom' | 'farm' | 'zen',

    async init() {
      await this.fetchCart();
      // Si no vino desde el servidor, leer parámetros de la URL
      try {
        const params = new URLSearchParams(globalThis.location.search);
        const list = params.getAll('exclude_allergens[]');
        if ((Array.isArray(this.excludedAllergens) && this.excludedAllergens.length === 0) && list.length > 0) {
          this.excludedAllergens = list;
        }
      } catch (e) {
        console.warn('No se pudieron leer los parámetros de la URL:', e);
      }

      // Aplicar filtros iniciales si vienen desde el servidor o la URL
      if (Array.isArray(this.excludedAllergens) && this.excludedAllergens.length > 0) {
        this.applyAllergenFilter();
      }

      this.loading = false;
    },

    // ----------------------------
    // API
    // ----------------------------
    async fetchCart() {
      try {
        // Si no hay cookies presentes probable usuario anónimo -> evitar solicitar /api/cart y causar 401
        if (!document.cookie || document.cookie.trim() === '') {
          const guestRes = await fetch('/api/v1/cart/guest');
          if (guestRes.ok) {
            const guestData = await guestRes.json();
            if (guestData.ok && guestData.data) {
              this.cart = guestData.data;
              return;
            }
          }
          this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
          return;
        }

        const res = await fetch('/api/v1/cart');
        if (!res.ok) {
          // Si el servidor responde 401 (no autenticado), informar al usuario
          if (res.status === 401) {
            // Mostrar mensaje amistoso para aclarar por qué no hay carrito
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'El carrito está disponible solo para usuarios registrados. Por favor, inicia sesión para acceder a tu carrito.', type: 'error' } }));
            // Intentar endpoint público de guest como fallback silencioso
            try {
              const guestRes = await fetch('/api/v1/cart/guest');
              if (guestRes.ok) {
                const guestData = await guestRes.json();
                if (guestData.ok && guestData.data) {
                  this.cart = guestData.data;
                } else {
                  this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
                }
              } else {
                this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
              }
            } catch (error_) {
              this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
            }
            return;
          }

          console.warn('Error cargando carrito:', res.status);
          return;
        }

        const responseData = await res.json();

        if (responseData.ok && responseData.data) {
          this.cart = responseData.data;
        } else {
          console.warn('Formato de carrito inesperado:', responseData);
          this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
        }
      } catch (e) {
        console.error('Error cargando carrito:', e);
        this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
      }
    },

    async updateQty(productId, change) {
      if (this.loading) return;

      this.loading = true;
      productId = Number.parseInt(productId, 10);
      change = Number.parseInt(change, 10);
      if (!Number.isFinite(productId) || !Number.isFinite(change)) {
        this.loading = false;
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Parámetros de cantidad inválidos', type: 'error' } }));
        return;
      }

      // Backup para rollback
      const oldCart = structuredClone(this.cart || { items: {}, total_qty: 0, totalPrice: 0 });

      // Optimistic UI
      if (!this.cart) this.cart = { items: {}, total_qty: 0, totalPrice: 0 };
      if (!this.cart.items) this.cart.items = {};

      const currentQty = this.cart.items[productId] || 0;
      const newQty = currentQty + change;

      if (newQty < 0 || newQty > 99) {
        this.loading = false;
        return;
      }

      if (newQty === 0) {
        delete this.cart.items[productId];
      } else {
        this.cart.items[productId] = newQty;
      }

      const tokenEl = document.querySelector('meta[name="csrf-token"]');
      const token = tokenEl ? tokenEl.getAttribute('content') : '';

      try {
        const res = await fetch(`/api/v1/cart/items/${productId}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
          },
          body: JSON.stringify({ change })
        });

        const responseData = await res.json();

        if (!res.ok) {
          // Manejo de errores HTTP
          if (res.status === 419) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Sesión expirada. Recarga la página.', type: 'error' } }));
          } else {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: responseData.error || responseData.message || 'Error al actualizar', type: 'error' } }));
          }
          this.cart = oldCart; // Rollback
          return;
        }

        // Extraer data del wrapper {ok: true, data: {...}}
        if (responseData.ok && responseData.data) {
          this.cart = responseData.data;
        } else {
          console.error('⚠️ Formato inesperado:', responseData);
          window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Error de formato en respuesta', type: 'error' } }));
          this.cart = oldCart;
        }

      } catch (e) {
        console.error('❌ Error de red:', e);
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Error de conexión', type: 'error' } }));
        this.cart = oldCart;
      } finally {
        this.loading = false;
      }
    },

    // ----------------------------
    // UI helpers
    // ----------------------------
    getQty(productId) {
      // Defensive: cart.items puede ser undefined durante init
      return (this.cart && this.cart.items && this.cart.items[productId]) || 0;
    },

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

        // Si no tiene el atributo (undefined) o está vacío = disponible en TODOS los cafés
        if (!cafeTypesAttr || cafeTypesAttr.trim() === '') {
          return true;
        }

        // Dividir por comas y verificar si el tipo seleccionado está presente
        // Valores esperados: 'lounge', 'playroom', 'farm', 'zen'
        const types = cafeTypesAttr.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
        const selectedType = this.selectedCafeType.toLowerCase();

        if (!types.includes(selectedType)) {
          return false; // NO mostrar si el tipo no coincide
        }
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

    toggleComanda() {
      this.showComanda = !this.showComanda;
      document.body.style.overflow = this.showComanda ? 'hidden' : '';
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

        // Filtrado en cliente: ocultar los productos que contengan alguno de los alérgenos excluidos
        const grids = document.querySelectorAll('.menu__grid');
        const excludedSet = new Set(this.excludedAllergens.map(String));

        for (const grid of grids) {
          const cards = grid.querySelectorAll('article.producto-card');
          for (const card of cards) {
            card.hidden = this.cardHasExcludedAllergen(card, excludedSet);
          }
        }
      } catch (e) {
        console.error('Error applying allergen filter:', e);
      }
    }
  }));
});
