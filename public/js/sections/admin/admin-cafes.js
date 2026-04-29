/**
 * Gestión de Cafés del Admin
 * ============================================================================
 * Sistema de manejo de cafés con Alpine.js.
 * Incluye grid de tarjetas, filtros, búsqueda y operaciones CRUD.
 *
 * Componentes Alpine:
 * - cafeManagement: Gestión completa de cafés
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  // ========================================================================
  // CONSTANTES
  // ========================================================================

  const CATEGORIES = {
    lounge: { label: 'Lounge', icon: '🛋️', class: 'category-badge--lounge' },
    playroom: { label: 'Playroom', icon: '🎮', class: 'category-badge--playroom' },
    farm: { label: 'Farm', icon: '🌾', class: 'category-badge--farm' },
    zen: { label: 'Zen', icon: '🧘', class: 'category-badge--zen' }
  };

  const ANIMAL_TYPES = {
    cat: { label: 'Gatos', icon: '🐱' },
    dog: { label: 'Perros', icon: '🐶' },
    rabbit: { label: 'Conejos', icon: '🐰' },
    bird: { label: 'Aves', icon: '🦜' },
    hedgehog: { label: 'Erizos', icon: '🦔' },
    capybara: { label: 'Capibaras', icon: '🦫' },
    mixed: { label: 'Mixto', icon: '🐾' }
  };

  // ========================================================================
  // COMPONENTE ALPINE: Gestión de Cafés
  // ========================================================================

  document.addEventListener('alpine:init', () => {

    /**
     * Componente principal de gestión de cafés
     * @param {Object} config - Configuración inicial
     */
    Alpine.data('cafeManagement', (config = {}) => ({
      // ─────────────────────────────────────────────────────────────
      // ESTADO INICIAL
      // ─────────────────────────────────────────────────────────────

      cafes: config.cafes || [],
      csrfToken: config.csrfToken || '',

      // ─────────────────────────────────────────────────────────────
      // FORMULARIO
      // ─────────────────────────────────────────────────────────────

      form: {
        id: null,
        name: '',
        japanese_name: '',
        slug: '',
        location: '',
        category: '',
        animal_type: '',
        description: '',
        price_per_hour: 1500,
        capacity_max: 20,
        opening_time: '10:00',
        closing_time: '20:00',
        image_url: '',
        is_active: true,
        has_reservations: true
      },

      // ─────────────────────────────────────────────────────────────
      // ESTADO UI
      // ─────────────────────────────────────────────────────────────

      isEditMode: false,
      isSubmitting: false,
      formErrors: [],
      modalInstance: null,
      imageError: false,

      // ─────────────────────────────────────────────────────────────
      // FILTROS Y BÚSQUEDA
      // ─────────────────────────────────────────────────────────────

      searchQuery: '',
      filterCategory: '',
      filterStatus: '', // '' = todos, '1' = activos, '0' = inactivos

      // ─────────────────────────────────────────────────────────────
      // INICIALIZACIÓN
      // ─────────────────────────────────────────────────────────────

      init() {
        // Normalizar datos de cafés
        this.cafes = this.cafes.map(cafe => ({
          ...cafe,
          is_active: Boolean(Number(cafe.is_active)),
          has_reservations: Boolean(Number(cafe.has_reservations)),
          rating: parseFloat(cafe.rating) || 0,
          capacity_max: parseInt(cafe.capacity_max) || 20,
          price_per_hour: parseInt(cafe.price_per_hour) || 0
        }));

        // Inicializar modal
        const modalEl = document.getElementById('cafeModal');
        if (modalEl) {
          this.modalInstance = new bootstrap.Modal(modalEl);
          modalEl.addEventListener('hidden.bs.modal', () => {
            this.resetForm();
          });
        }

        console.log('[CafeManagement] Initialized with', this.cafes.length, 'cafes');
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: ESTADÍSTICAS
      // ─────────────────────────────────────────────────────────────

      get totalCafes() {
        return this.cafes.length;
      },

      get activeCafesCount() {
        return this.cafes.filter(c => c.is_active).length;
      },

      get cafesWithReservationsCount() {
        return this.cafes.filter(c => c.has_reservations).length;
      },

      get averageRating() {
        if (this.cafes.length === 0) return 0;
        const sum = this.cafes.reduce((acc, c) => acc + (c.rating || 0), 0);
        return (sum / this.cafes.length).toFixed(1);
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: FILTRADO
      // ─────────────────────────────────────────────────────────────

      get filteredCafes() {
        let filtered = [...this.cafes];

        // Filtro por categoría
        if (this.filterCategory) {
          filtered = filtered.filter(c => c.category === this.filterCategory);
        }

        // Filtro por estado
        if (this.filterStatus !== '') {
          const isActive = this.filterStatus === '1';
          filtered = filtered.filter(c => c.is_active === isActive);
        }

        // Búsqueda
        if (this.searchQuery.trim()) {
          const query = this.searchQuery.toLowerCase().trim();
          filtered = filtered.filter(c =>
            c.name?.toLowerCase().includes(query) ||
            c.japanese_name?.toLowerCase().includes(query) ||
            c.location?.toLowerCase().includes(query) ||
            c.description?.toLowerCase().includes(query) ||
            this.getAnimalLabel(c.animal_type)?.toLowerCase().includes(query)
          );
        }

        return filtered;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: MODAL
      // ─────────────────────────────────────────────────────────────

      openCreateModal() {
        this.isEditMode = false;
        this.resetForm();
        this.form.is_active = true;
        this.form.has_reservations = true;
        this.modalInstance?.show();
      },

      openEditModal(cafe) {
        this.isEditMode = true;
        this.form = {
          id: cafe.id,
          name: cafe.name || '',
          japanese_name: cafe.japanese_name || '',
          slug: cafe.slug || '',
          location: cafe.location || '',
          category: cafe.category || '',
          animal_type: cafe.animal_type || '',
          description: cafe.description || '',
          price_per_hour: cafe.price_per_hour || 1500,
          capacity_max: cafe.capacity_max || 20,
          opening_time: cafe.opening_time || '10:00',
          closing_time: cafe.closing_time || '20:00',
          image_url: cafe.image_url || '',
          is_active: cafe.is_active,
          has_reservations: cafe.has_reservations
        };
        this.formErrors = [];
        this.imageError = false;
        this.modalInstance?.show();
      },

      closeModal() {
        this.modalInstance?.hide();
      },

      resetForm() {
        this.form = {
          id: null,
          name: '',
          japanese_name: '',
          slug: '',
          location: '',
          category: '',
          animal_type: '',
          description: '',
          price_per_hour: 1500,
          capacity_max: 20,
          opening_time: '10:00',
          closing_time: '20:00',
          image_url: '',
          is_active: true,
          has_reservations: true
        };
        this.formErrors = [];
        this.isEditMode = false;
        this.imageError = false;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: SLUG E IMAGEN
      // ─────────────────────────────────────────────────────────────

      generateSlug() {
        if (!this.isEditMode && this.form.name) {
          this.form.slug = KomorebiForm.generateSlug(this.form.name);
        }
      },

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

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: VALIDACIÓN
      // ─────────────────────────────────────────────────────────────

      validate() {
        const errors = [];

        if (!this.form.name || this.form.name.trim().length < 3) {
          errors.push('El nombre debe tener al menos 3 caracteres');
        }

        if (!this.form.slug || !/^[a-z0-9-]+$/.test(this.form.slug)) {
          errors.push('El slug solo puede contener letras minúsculas, números y guiones');
        }

        if (!this.form.location || this.form.location.trim().length < 3) {
          errors.push('La ubicación es obligatoria');
        }

        if (!this.form.category) {
          errors.push('Selecciona una categoría');
        }

        if (!this.form.animal_type) {
          errors.push('Selecciona el tipo de animal');
        }

        if (!this.form.description || this.form.description.trim().length < 10) {
          errors.push('La descripción debe tener al menos 10 caracteres');
        }

        if (this.form.price_per_hour < 0) {
          errors.push('El precio debe ser mayor o igual a 0');
        }

        if (this.form.capacity_max < 1 || this.form.capacity_max > 200) {
          errors.push('La capacidad debe estar entre 1 y 200');
        }

        return errors;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: CRUD
      // ─────────────────────────────────────────────────────────────

      async submitCafe() {
        this.formErrors = this.validate();
        if (this.formErrors.length > 0) {
          KomorebiToast.error(this.formErrors[0]);
          return;
        }

        this.isSubmitting = true;

        try {
          const url = this.isEditMode
            ? `/api/v1/admin/cafes/${this.form.id}`
            : '/api/v1/admin/cafes';

          const formData = new URLSearchParams({
            csrf_token: this.csrfToken,
            name: this.form.name,
            japanese_name: this.form.japanese_name,
            slug: this.form.slug,
            location: this.form.location,
            category: this.form.category,
            animal_type: this.form.animal_type,
            description: this.form.description,
            price_per_hour: this.form.price_per_hour,
            capacity_max: this.form.capacity_max,
            opening_time: this.form.opening_time,
            closing_time: this.form.closing_time,
            image_url: this.form.image_url,
            is_active: this.form.is_active ? '1' : '0',
            has_reservations: this.form.has_reservations ? '1' : '0'
          });

          const response = await fetch(url, {
            method: this.isEditMode ? 'PUT' : 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          });

          const data = await response.json();

          if (response.ok && data.ok) {
            KomorebiToast.success(data.message || 'Café guardado correctamente');
            this.closeModal();
            setTimeout(() => window.location.reload(), 800);
          } else {
            if (data.errors && typeof data.errors === 'object') {
              this.formErrors = Object.values(data.errors).flat();
            } else {
              this.formErrors = [data.message || 'Error al guardar'];
            }
            KomorebiToast.error(this.formErrors[0]);
          }
        } catch (error) {
          console.error('[CafeManagement] Submit error:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.isSubmitting = false;
        }
      },

      async toggleCafeStatus(cafeId) {
        const cafe = this.cafes.find(c => c.id === cafeId);
        if (!cafe) return;

        if (!await KomorebiConfirm.toggle(`el café "${cafe.name}"`, cafe.is_active)) {
          return;
        }

        // UI Optimista: guardar estado original y actualizar inmediatamente
        const originalStatus = cafe.is_active;
        cafe.is_active = !cafe.is_active;

        try {
          const response = await fetch(`/api/v1/admin/cafes/${cafeId}/status`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ csrf_token: this.csrfToken })
          });

          const data = await response.json();

          if (response.ok && data.ok) {
            KomorebiToast.success(data.message || 'Estado actualizado correctamente');
          } else {
            // Revertir en caso de error
            cafe.is_active = originalStatus;
            KomorebiToast.error(data.message || 'Error al actualizar');
          }
        } catch (error) {
          // Revertir en caso de error de red
          cafe.is_active = originalStatus;
          console.error('[CafeManagement] Toggle error:', error);
          KomorebiToast.error('Error de conexión al actualizar');
        }
      },

      async deleteCafe(cafeId) {
        const cafe = this.cafes.find(c => c.id === cafeId);
        if (!cafe) return;

        if (!await KomorebiConfirm.delete(`el café "${cafe.name}"`)) {
          return;
        }

        // UI Optimista: remover del array visualmente primero
        const cafeIndex = this.cafes.findIndex(c => c.id === cafeId);
        const removedCafe = this.cafes.splice(cafeIndex, 1)[0];

        try {
          const response = await fetch(`/api/v1/admin/cafes/${cafeId}`, {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ csrf_token: this.csrfToken })
          });

          const data = await response.json();

          if (response.ok && data.ok) {
            KomorebiToast.success(data.message || 'Café eliminado correctamente');
          } else {
            // Revertir: restaurar el café en su posición original
            this.cafes.splice(cafeIndex, 0, removedCafe);
            KomorebiToast.error(data.message || 'Error al eliminar');
          }
        } catch (error) {
          // Revertir en caso de error de red
          this.cafes.splice(cafeIndex, 0, removedCafe);
          console.error('[CafeManagement] Delete error:', error);
          KomorebiToast.error('Error de conexión al eliminar');
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: UTILIDADES UI
      // ─────────────────────────────────────────────────────────────

      getCategoryLabel(category) {
        return CATEGORIES[category]?.label || category;
      },

      getCategoryIcon(category) {
        return CATEGORIES[category]?.icon || '📍';
      },

      getCategoryClass(category) {
        return CATEGORIES[category]?.class || '';
      },

      getCategoryBadgeClass(category) {
        return `category-badge category-badge--${category}`;
      },

      getAnimalLabel(animalType) {
        return ANIMAL_TYPES[animalType]?.label || animalType;
      },

      getAnimalIcon(animalType) {
        return ANIMAL_TYPES[animalType]?.icon || '🐾';
      },

      formatPrice(price) {
        return KomorebiUI.formatPrice(price);
      },

      /**
       * Genera array de estrellas para rating
       */
      getRatingStars(rating) {
        const stars = [];
        const fullStars = Math.floor(rating);
        for (let i = 1; i <= 5; i++) {
          stars.push(i <= fullStars);
        }
        return stars;
      },

      /**
       * Maneja error de carga de imagen
       */
      handleImageError(event) {
        event.target.src = '/images/cafes/default.jpg';
      }
    }));
  });

  // ========================================================================
  // INITIALIZATION
  // ========================================================================

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminCafes] Module loaded');
  });

})();
