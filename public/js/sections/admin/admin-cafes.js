(function () {
  'use strict';

  function createCafeManagement(config = {}) {
    return {
      // ── Estado ──────────────────────────────────────────────────
      csrfToken:    config.csrfToken || '',

      isEditMode:   false,
      isSubmitting: false,
      formErrors:   [],
      modalInstance: null,
      imageError:   false,

      form: {
        id: null,
        name: '', japanese_name: '', slug: '',
        location: '', category: '', animal_type: '',
        description: '', price_per_hour: 1500, capacity_max: 20,
        opening_time: '10:00', closing_time: '20:00',
        image_url: '', is_active: true, has_reservations: true,
      },

      // ── Init ────────────────────────────────────────────────────
      init() {
        const modalEl = document.getElementById('cafeModal');
        if (modalEl) {
          this.modalInstance = new bootstrap.Modal(modalEl);
          modalEl.addEventListener('hidden.bs.modal', () => this.resetForm());
        }
      },

      // ── Modal ───────────────────────────────────────────────────
      openCreateModal() {
        this.isEditMode = false;
        this.resetForm();
        this.modalInstance?.show();
      },

      openEditModal(cafe) {
        this.isEditMode = true;
        this.form = {
          id:               cafe.id,
          name:             cafe.name             || '',
          japanese_name:    cafe.japanese_name    || '',
          slug:             cafe.slug             || '',
          location:         cafe.location         || '',
          category:         cafe.category         || '',
          animal_type:      cafe.animal_type      || '',
          description:      cafe.description      || '',
          price_per_hour:   cafe.price_per_hour   ?? 1500,
          capacity_max:     cafe.capacity_max     ?? 20,
          opening_time:     cafe.opening_time     || '10:00',
          closing_time:     cafe.closing_time     || '20:00',
          image_url:        cafe.image_url        || '',
          is_active:        cafe.is_active,
          has_reservations: cafe.has_reservations,
        };
        this.formErrors = [];
        this.imageError = false;
        this.modalInstance?.show();
      },

      closeModal() { this.modalInstance?.hide(); },

      resetForm() {
        this.form = {
          id: null,
          name: '', japanese_name: '', slug: '',
          location: '', category: '', animal_type: '',
          description: '', price_per_hour: 1500, capacity_max: 20,
          opening_time: '10:00', closing_time: '20:00',
          image_url: '', is_active: true, has_reservations: true,
        };
        this.formErrors = [];
        this.isEditMode = false;
        this.imageError = false;
      },

      // ── Slug e imagen ───────────────────────────────────────────
      generateSlug() {
        if (!this.isEditMode && this.form.name) {
          this.form.slug = KomorebiForm.generateSlug(this.form.name);
        }
      },

      validateImageUrl() {
        if (!this.form.image_url) { this.imageError = false; return; }
        const img = new Image();
        img.onload  = () => { this.imageError = false; };
        img.onerror = () => { this.imageError = true; };
        img.src = this.form.image_url;
      },

      // ── Validación ──────────────────────────────────────────────
      validate() {
        const errors = [];
        if (!this.form.name || this.form.name.trim().length < 3)
          errors.push('El nombre debe tener al menos 3 caracteres');
        if (!this.form.slug || !/^[a-z0-9-]+$/.test(this.form.slug))
          errors.push('El slug solo puede contener letras minúsculas, números y guiones');
        if (!this.form.location || this.form.location.trim().length < 3)
          errors.push('La ubicación es obligatoria');
        if (!this.form.category)
          errors.push('Selecciona una categoría');
        if (!this.form.animal_type)
          errors.push('Selecciona el tipo de animal');
        if (!this.form.description || this.form.description.trim().length < 10)
          errors.push('La descripción debe tener al menos 10 caracteres');
        if (this.form.price_per_hour < 0)
          errors.push('El precio debe ser mayor o igual a 0');
        if (this.form.capacity_max < 1 || this.form.capacity_max > 200)
          errors.push('La capacidad debe estar entre 1 y 200');
        return errors;
      },

      // ── CRUD ────────────────────────────────────────────────────
      async submitCafe() {
        this.formErrors = this.validate();
        if (this.formErrors.length > 0) { KomorebiToast.error(this.formErrors[0]); return; }

        this.isSubmitting = true;
        try {
          const isEdit = this.isEditMode;
          const url    = isEdit ? `/api/v1/admin/cafes/${this.form.id}` : '/api/v1/admin/cafes';

          let res;
          if (isEdit) {
            res = await fetch(url, {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              body: JSON.stringify({
                csrf_token:       this.csrfToken,
                name:             this.form.name,
                japanese_name:    this.form.japanese_name,
                slug:             this.form.slug,
                location:         this.form.location,
                category:         this.form.category,
                animal_type:      this.form.animal_type,
                description:      this.form.description,
                price_per_hour:   this.form.price_per_hour,
                capacity_max:     this.form.capacity_max,
                opening_time:     this.form.opening_time,
                closing_time:     this.form.closing_time,
                image_url:        this.form.image_url,
                is_active:        this.form.is_active ? 1 : 0,
                has_reservations: this.form.has_reservations ? 1 : 0,
              }),
            });
          } else {
            res = await fetch(url, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
              body: new URLSearchParams({
                csrf_token:       this.csrfToken,
                name:             this.form.name,
                japanese_name:    this.form.japanese_name,
                slug:             this.form.slug,
                location:         this.form.location,
                category:         this.form.category,
                animal_type:      this.form.animal_type,
                description:      this.form.description,
                price_per_hour:   this.form.price_per_hour,
                capacity_max:     this.form.capacity_max,
                opening_time:     this.form.opening_time,
                closing_time:     this.form.closing_time,
                image_url:        this.form.image_url,
                is_active:        this.form.is_active ? '1' : '0',
                has_reservations: this.form.has_reservations ? '1' : '0',
              }),
            });
          }

          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Café guardado');
            this.closeModal();
            globalThis.location.reload();
          } else {
            this.formErrors = data.errors ? Object.values(data.errors).flat() : [data.detail || 'Error al guardar'];
            KomorebiToast.error(this.formErrors[0]);
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.isSubmitting = false; }
      },

      async toggleCafeStatus(cafeId, cafeName, isActive) {
        if (!await KomorebiConfirm.toggle(`el café "${cafeName}"`, isActive)) return;
        try {
          const res  = await fetch(`/api/v1/admin/cafes/${cafeId}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Estado actualizado');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al actualizar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },

      async deleteCafe(cafeId, cafeName) {
        if (!await KomorebiConfirm.delete(`el café "${cafeName}"`)) return;
        try {
          const res  = await fetch(`/api/v1/admin/cafes/${cafeId}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Café eliminado');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al eliminar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('cafeManagement', createCafeManagement);
  });

})();
