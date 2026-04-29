/**
 * Gestión de Productos del Admin
 * ============================================================================
 * Sistema de manejo de productos con Alpine.js.
 * Incluye funcionalidades para listado, creación y edición.
 *
 * Componentes Alpine:
 * - productManagement: Gestión completa de productos (índice)
 * - productForm: Formulario de crear/editar producto
 *
 * Funciones globales:
 * - ProductsTable: Acciones de la tabla (activar/desactivar, eliminar)
 *
 * @version 2.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  // ========================================================================
  // COMPONENTE ALPINE: Gestión de Productos (Índice)
  // ========================================================================

  document.addEventListener('alpine:init', () => {

    /**
     * Componente principal de gestión de productos
     * @param {Object} config - Configuración inicial
     */
    Alpine.data('productManagement', (config = {}) => ({
      // ─────────────────────────────────────────────────────────────
      // ESTADO INICIAL
      // ─────────────────────────────────────────────────────────────

      products: config.products || [],
      availableCategories: config.categories || [],
      csrfToken: config.csrfToken || '',

      // ─────────────────────────────────────────────────────────────
      // FILTROS
      // ─────────────────────────────────────────────────────────────

      searchQuery: '',
      filterCategory: '',
      filterAvailable: '',
      filterAllergen: '',
      filterPriceRange: '',

      // ─────────────────────────────────────────────────────────────
      // PAGINACIÓN
      // ─────────────────────────────────────────────────────────────

      currentPage: 1,
      itemsPerPage: 20,

      // ─────────────────────────────────────────────────────────────
      // UI STATE
      // ─────────────────────────────────────────────────────────────

      isLoading: false,
      isToggling: {},

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: FILTROS
      // ─────────────────────────────────────────────────────────────

      get filteredProducts() {
        let filtered = [...this.products];

        // Filtro por búsqueda
        if (this.searchQuery) {
          const query = this.searchQuery.toLowerCase();
          filtered = filtered.filter(p =>
            p.name.toLowerCase().includes(query) ||
            (p.japanese_name && p.japanese_name.toLowerCase().includes(query))
          );
        }

        // Filtro por categoría
        if (this.filterCategory) {
          filtered = filtered.filter(p => p.category_id === this.filterCategory);
        }

        // Filtro por disponibilidad
        if (this.filterAvailable !== '') {
          const isActive = this.filterAvailable === '1';
          filtered = filtered.filter(p => p.is_active === isActive);
        }

        // Filtro por alérgenos
        if (this.filterAllergen === 'with') {
          filtered = filtered.filter(p => p.allergens_list && p.allergens_list.length > 0);
        } else if (this.filterAllergen === 'without') {
          filtered = filtered.filter(p => !p.allergens_list || p.allergens_list.length === 0);
        }

        // Filtro por rango de precio
        if (this.filterPriceRange) {
          if (this.filterPriceRange === '1500+') {
            filtered = filtered.filter(p => parseFloat(p.price || 0) >= 1500);
          } else {
            const [min, max] = this.filterPriceRange.split('-').map(Number);
            filtered = filtered.filter(p => {
              const price = parseFloat(p.price || 0);
              return price >= min && price < max;
            });
          }
        }

        return filtered;
      },

      get activeFilterCount() {
        let count = 0;
        if (this.searchQuery) count++;
        if (this.filterCategory) count++;
        if (this.filterAvailable !== '') count++;
        if (this.filterAllergen) count++;
        if (this.filterPriceRange) count++;
        return count;
      },

      get paginatedProducts() {
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        return this.filteredProducts.slice(start, end);
      },

      get totalPages() {
        return Math.ceil(this.filteredProducts.length / this.itemsPerPage);
      },
      clearAllFilters() {
        this.searchQuery = '';
        this.filterCategory = '';
        this.filterAvailable = '';
        this.filterAllergen = '';
        this.filterPriceRange = '';
        this.currentPage = 1;
      },


      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: HELPERS
      // ─────────────────────────────────────────────────────────────

      formatPrice(price) {
        return Number(price || 0).toLocaleString('ja-JP');
      },

      getCategoryBadgeClass(categoryName) {
        const classes = {
          'Bebidas Calientes': 'bg-danger',
          'Bebidas Frías': 'bg-info',
          'Postres': 'bg-warning',
          'Comida': 'bg-success'
        };
        return classes[categoryName] || 'bg-secondary';
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: ACCIONES
      // ─────────────────────────────────────────────────────────────

      async toggleProductStatus(productId) {
        if (this.isToggling[productId]) return;

        this.isToggling[productId] = true;

        try {
          const formData = new URLSearchParams();
          formData.append('csrf_token', this.csrfToken);

          const response = await fetch(`/api/v1/admin/menu/${productId}/toggle`, {
            method: 'PATCH',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const data = await response.json();

          if (response.ok && data.ok
            // Actualizar producto en array
            const product = this.products.find(p => p.id === productId);
          if (product) {
            product.is_active = !product.is_active;
          }

          if (window.notificationManager) {
            window.notificationManager.show(
              data.message || 'Estado actualizado',
              'success'
            );
          }
        } else {
          throw new Error(data.message || 'Error al cambiar estado');
        }
    } catch (error) {
      console.error('Error toggling product:', error);
      if (window.notificationManager) {
        window.notificationManager.show(
          error.message || 'Error al cambiar el estado del producto',
          'error'
        );
      }
    } finally {
      this.isToggling[productId] = false;
    }
  },

    async deleteProduct(productId, productName) {
      if(!confirm(`¿Eliminar "${productName}"? Esta acción no se puede deshacer.`)) {
    return;
  }

  try {
    const formData = new URLSearchParams();
    formData.append('csrf_token', this.csrfToken);

    const response = await fetch(`/api/v1/admin/menu/${productId}`, {
      method: 'DELETE',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const data = await response.json();

    if (response.ok && data.ok
            // Remover producto del array
            this.products = this.products.filter(p => p.id !== productId);

    if (window.notificationManager) {
      window.notificationManager.show(
        data.message || 'Producto eliminado',
        'success'
      );
    }
  } else {
    throw new Error(data.message || 'Error al eliminar');
  }
} catch (error) {
  console.error('Error deleting product:', error);
  if (window.notificationManager) {
    window.notificationManager.show(
      error.message || 'Error al eliminar el producto',
      'error'
    );
  }
}
      }
    }));

// ========================================================================
// ALPINE COMPONENT: Product Form
// ========================================================================

/**
 * Componente para formulario de producto (crear/editar)
 * @param {Object} config - Configuración inicial
 */
Alpine.data('productForm', (config = {}) => ({
  // ─────────────────────────────────────────────────────────────
  // CONFIGURACIÓN
  // ─────────────────────────────────────────────────────────────

  isEdit: config.isEdit || false,
  productId: config.productId || null,
  submitUrl: config.submitUrl || '/admin/productos/crear',

  // ─────────────────────────────────────────────────────────────
  // ESTADO DEL FORMULARIO
  // ─────────────────────────────────────────────────────────────

  form: {
    name: config.name || '',
    japanese_name: config.japanese_name || '',
    slug: config.slug || '',
    description: config.description || '',
    image_url: config.image_url || '',
    category_id: config.category_id || '',
    price: config.price || '',
    calories: config.calories || '',
    prep_time: config.prep_time || '',
    is_active: config.is_active ?? true
  },

  // Alérgenos seleccionados (array de IDs numéricos)
  selectedAllergens: (config.allergens || []).map(id => parseInt(id)),

  // ─────────────────────────────────────────────────────────────
  // ESTADO UI
  // ─────────────────────────────────────────────────────────────

  isSubmitting: false,
  imageError: false,
  slugManuallyEdited: config.isEdit || false,
  formErrors: [],

  // ─────────────────────────────────────────────────────────────
  // INICIALIZACIÓN
  // ─────────────────────────────────────────────────────────────

  init() {
    // Validar imagen inicial si existe
    if (this.form.image_url) {
      this.validateImageUrl();
    }

    console.log('[ProductForm] Initialized', {
      isEdit: this.isEdit,
      productId: this.productId,
      allergensCount: this.selectedAllergens.length
    });
  },

  // ─────────────────────────────────────────────────────────────
  // MÉTODOS: SLUG
  // ─────────────────────────────────────────────────────────────

  /**
   * Genera slug a partir del nombre
   */
  generateSlug() {
    if (this.slugManuallyEdited) return;
    this.form.slug = KomorebiForm.generateSlug(this.form.name);
  },

  /**
   * Marca el slug como editado manualmente
   */
  onSlugInput() {
    this.slugManuallyEdited = true;
  },

  /**
   * Resetea el slug para auto-generación
   */
  resetSlugMode() {
    this.slugManuallyEdited = false;
    this.generateSlug();
  },

  // ─────────────────────────────────────────────────────────────
  // MÉTODOS: IMAGEN
  // ─────────────────────────────────────────────────────────────

  /**
   * Valida URL de imagen
   */
  validateImageUrl() {
    if (!this.form.image_url) {
      this.imageError = false;
      return;
    }

    const img = new Image();
    img.onload = () => {
      this.imageError = false;
    };
    img.onerror = () => {
      this.imageError = true;
    };
    img.src = this.form.image_url;
  },

  /**
   * Limpia la URL de imagen
   */
  clearImage() {
    this.form.image_url = '';
    this.imageError = false;
  },

  // ─────────────────────────────────────────────────────────────
  // MÉTODOS: ALÉRGENOS
  // ─────────────────────────────────────────────────────────────

  /**
   * Toggle de alérgeno en el array
   * @param {number|string} allergenId
   */
  toggleAllergen(allergenId) {
    const id = parseInt(allergenId);
    const index = this.selectedAllergens.indexOf(id);

    if (index > -1) {
      this.selectedAllergens.splice(index, 1);
    } else {
      this.selectedAllergens.push(id);
    }
  },

  /**
   * Verifica si un alérgeno está seleccionado
   * @param {number|string} allergenId
   * @returns {boolean}
   */
  hasAllergen(allergenId) {
    return this.selectedAllergens.includes(parseInt(allergenId));
  },

  /**
   * Selecciona todos los alérgenos
   * @param {Array} allIds - Array de todos los IDs
   */
  selectAllAllergens(allIds) {
    this.selectedAllergens = allIds.map(id => parseInt(id));
  },

  /**
   * Deselecciona todos los alérgenos
   */
  clearAllAllergens() {
    this.selectedAllergens = [];
  },

  // ─────────────────────────────────────────────────────────────
  // MÉTODOS: VALIDACIÓN
  // ─────────────────────────────────────────────────────────────

  /**
   * Valida el formulario
   * @returns {Array} Array de errores
   */
  validate() {
    const errors = [];

    if (!this.form.name || this.form.name.trim().length < 2) {
      errors.push('El nombre debe tener al menos 2 caracteres');
    }

    if (!this.form.slug || !/^[a-z0-9-]+$/.test(this.form.slug)) {
      errors.push('El slug solo puede contener letras minúsculas, números y guiones');
    }

    if (!this.form.category_id) {
      errors.push('Selecciona una categoría');
    }

    if (!this.form.price || parseFloat(this.form.price) < 0) {
      errors.push('El precio debe ser mayor o igual a 0');
    }

    return errors;
  },

  // ─────────────────────────────────────────────────────────────
  // MÉTODOS: ENVÍO
  // ─────────────────────────────────────────────────────────────

  /**
   * Envía el formulario
   * @param {Event} event
   */
  async submitForm(event) {
    // Prevenir doble envío
    if (this.isSubmitting) return;

    // Validar
    this.formErrors = this.validate();
    if (this.formErrors.length > 0) {
      KomorebiToast.error(this.formErrors[0]);
      return;
    }

    this.isSubmitting = true;

    try {
      const form = event.target;
      const formData = new FormData(form);

      // Limpiar alérgenos existentes y agregar los seleccionados
      formData.delete('allergens[]');
      this.selectedAllergens.forEach(id => {
        formData.append('allergens[]', id);
      });

      // Agregar is_active como valor booleano
      formData.set('is_active', this.form.is_active ? '1' : '0');

      const url = this.isEdit ? `/api/v1/admin/menu/${this.productId}` : '/api/v1/admin/menu';

      const response = await fetch(url, {
        method: this.isEdit ? 'PUT' : 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await response.json();

      if (response.ok && data.okn();

      if (response.ok && data.ok) {
        KomorebiToast.success(data.message || 'Producto guardado correctamente');

        // Redirección después de un breve delay
        setTimeout(() => {
          window.location.href = data.redirect || '/admin/productos';
        }, 800);
      } else {
        // Manejar errores de validación del servidor
        if (data.errors && typeof data.errors === 'object') {
          this.formErrors = Object.values(data.errors).flat();
          KomorebiToast.error(this.formErrors[0]);
        } else {
          KomorebiToast.error(data.message || 'Error al guardar el producto');
        }
        this.isSubmitting = false;
      }
    } catch (error) {
      console.error('[ProductForm] Submit error:', error);
      KomorebiToast.error('Error de conexión. Inténtalo de nuevo.');
      this.isSubmitting = false;
    }
  }
}));
  });

// ========================================================================
// GLOBAL: Products Table Actions
// ========================================================================

window.ProductsTable = {
  /**
   * Elimina un producto
   * @param {number} productId
   * @param {string} csrfToken
   */
  async delete(productId, csrfToken) {
    if (!await KomorebiConfirm.delete('este producto')) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append('csrf_token', cspi / v1 / admin / menu / ${ productId }`, {
          method: 'DELETE
        const response = await fetch(`/ api / v1 / admin / menu / ${ productId }`, {
          method: 'DELETE',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        const data = await response.json();

        if (response.ok && data.ok) {
          // Animar y remover fila
          const row = document.querySelector(`tr[data - product - id= "${productId}"]`);
          if (row) {
            row.style.transition = 'opacity 0.3s, transform 0.3s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            setTimeout(() => row.remove(), 300);
          }

          KomorebiToast.success(data.message || 'Producto eliminado');

          // Recargar si no quedan productos
          setTimeout(() => {
            const remaining = document.querySelectorAll('tr[data-product-id]');
            if (remaining.length === 0) {
              window.location.reload();
            }
          }, 500);
        } else {
          KomorebiToast.error(data.message || 'Error al eliminar');
        }
      } catch (error) {
        console.error('[ProductsTable] Delete error:', error);
        KomorebiToast.error('Error de conexión');
      }
    },

    /**
     * Toggle de disponibilidad
     * @param {number} productId
     * @param {string} csrfToken
     * @param {HTMLButtonElement} button
     */
    async toggleAvailability(productId, csrfToken, button) {
      const originalText = button.textContent;

      try {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const formData = new FormData();
        formData.append('csrf_token', cspi/v1/admin/menu/${productId}/toggle`, {
        method: 'PATCH',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await response.json();

      if (response.ok && data.okn();

      if (response.ok && data.ok) {
        // Toggle visual del botón
        const wasActive = button.classList.contains('btn-success');

        button.classList.toggle('btn-success', !wasActive);
        button.classList.toggle('btn-secondary', wasActive);
        button.textContent = wasActive ? 'Inactivo' : 'Activo';

        KomorebiToast.success(data.message || 'Estado actualizado');
      } else {
        button.textContent = originalText;
        KomorebiToast.error(data.message || 'Error al cambiar estado');
      }
    } catch (error) {
      console.error('[ProductsTable] Toggle error:', error);
      button.textContent = originalText;
      KomorebiToast.error('Error de conexión');
    } finally {
      button.disabled = false;
    }
  }
};

// ========================================================================
// INITIALIZATION
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {
  console.log('[AdminProducts] Module loaded');
});

}) ();
