(function () {
  'use strict';

  // ── Index: toggle / delete ───────────────────────────────────────────────
  function createProductActions(config = {}) {
    return {
      csrfToken: config.csrfToken || '',

      async toggleProduct(productId, isActive) {
        if (!await KomorebiConfirm.toggle('el producto', isActive)) return;
        try {
          const res  = await fetch(`/api/v1/admin/menu/${productId}/toggle`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken }),
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

      async deleteProduct(productId, productName) {
        if (!await KomorebiConfirm.delete(`el producto "${productName}"`)) return;
        try {
          const res  = await fetch(`/api/v1/admin/menu/${productId}`, {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Producto eliminado');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al eliminar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },
    };
  }

  // ── Create / Edit form ───────────────────────────────────────────────────
  function createProductForm(config = {}) {
    return {
      isEdit:    config.isEdit    || false,
      productId: config.productId || null,

      form: {
        name:          config.name          || '',
        japanese_name: config.japanese_name || '',
        slug:          config.slug          || '',
        description:   config.description   || '',
        image_url:     config.image_url     || '',
        category_id:   config.category_id   || '',
        price:         config.price         || '',
        calories:      config.calories      || '',
        prep_time:     config.prep_time     || '',
        is_active:     config.is_active     ?? true,
      },

      selectedAllergens: (config.allergens || []).map(id => Number.parseInt(id)),

      isSubmitting:       false,
      imageError:         false,
      slugManuallyEdited: config.isEdit || false,
      formErrors:         [],

      init() {
        if (this.form.image_url) {
          this.validateImageUrl();
        }
      },

      generateSlug() {
        if (!this.slugManuallyEdited) {
          this.form.slug = KomorebiForm.generateSlug(this.form.name);
        }
      },

      onSlugInput()  { this.slugManuallyEdited = true; },

      resetSlugMode() {
        this.slugManuallyEdited = false;
        this.generateSlug();
      },

      validateImageUrl() {
        if (!this.form.image_url) { this.imageError = false; return; }
        const img    = new Image();
        img.onload  = () => { this.imageError = false; };
        img.onerror = () => { this.imageError = true; };
        img.src = this.form.image_url;
      },

      clearImage() {
        this.form.image_url = '';
        this.imageError     = false;
      },

      toggleAllergen(allergenId) {
        const id    = Number.parseInt(allergenId);
        const index = this.selectedAllergens.indexOf(id);
        if (index > -1) {
          this.selectedAllergens.splice(index, 1);
        } else {
          this.selectedAllergens.push(id);
        }
      },

      hasAllergen(allergenId)       { return this.selectedAllergens.includes(Number.parseInt(allergenId)); },
      selectAllAllergens(allIds)    { this.selectedAllergens = allIds.map(id => Number.parseInt(id)); },
      clearAllAllergens()           { this.selectedAllergens = []; },

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
        if (!this.form.price || Number.parseFloat(this.form.price) < 0) {
          errors.push('El precio debe ser mayor o igual a 0');
        }
        return errors;
      },

      async submitForm(event) {
        if (this.isSubmitting) return;

        this.formErrors = this.validate();
        if (this.formErrors.length > 0) {
          KomorebiToast.error(this.formErrors[0]);
          return;
        }

        this.isSubmitting = true;

        const csrfToken = event.target.elements['csrf_token']?.value || '';

        try {
          let res;

          if (this.isEdit) {
            res = await fetch(`/api/v1/admin/menu/${this.productId}`, {
              method:  'PUT',
              headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              body:    JSON.stringify({
                csrf_token:    csrfToken,
                name:          this.form.name,
                japanese_name: this.form.japanese_name,
                slug:          this.form.slug,
                description:   this.form.description,
                image_url:     this.form.image_url,
                category_id:   this.form.category_id,
                price:         this.form.price,
                calories:      this.form.calories,
                prep_time:     this.form.prep_time,
                is_active:     this.form.is_active ? 1 : 0,
                allergens:     this.selectedAllergens,
              }),
            });
          } else {
            const params = new URLSearchParams({
              csrf_token:    csrfToken,
              name:          this.form.name,
              japanese_name: this.form.japanese_name,
              slug:          this.form.slug,
              description:   this.form.description,
              image_url:     this.form.image_url || '',
              category_id:   this.form.category_id,
              price:         this.form.price,
              calories:      this.form.calories || '',
              prep_time:     this.form.prep_time || '',
              is_active:     this.form.is_active ? '1' : '0',
            });
            this.selectedAllergens.forEach(id => params.append('allergens[]', id));
            res = await fetch('/api/v1/admin/menu', {
              method:  'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
              body:    params.toString(),
            });
          }

          const data = await res.json();

          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Producto guardado correctamente');
            setTimeout(() => {
              globalThis.location.href = data.data?.redirect || '/admin/productos';
            }, 800);
          } else {
            this.formErrors = data.errors
              ? Object.values(data.errors).flat()
              : [data.detail || 'Error al guardar'];
            KomorebiToast.error(this.formErrors[0]);
            this.isSubmitting = false;
          }
        } catch {
          KomorebiToast.error('Error de conexión. Inténtalo de nuevo.');
          this.isSubmitting = false;
        }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('productActions', createProductActions);
    Alpine.data('productForm',    createProductForm);
  });

})();
