(function () {
  'use strict';

  function createManagerProducts(config = {}) {
    return {
      csrfToken:    config.csrfToken || '',
      saving:       false,
      formErrors:   [],
      deleteTarget: { id: null, name: '' },
      modalInstance: null,
      deleteModal:   null,

      form: { id: null, name: '', price: '', category_id: '', description: '' },

      init() {
        const productModalEl = document.getElementById('productModal');
        if (productModalEl) {
          this.modalInstance = new bootstrap.Modal(productModalEl);
          productModalEl.addEventListener('hidden.bs.modal', () => this.resetForm());
        }
        const deleteModalEl = document.getElementById('deleteModal');
        if (deleteModalEl) {
          this.deleteModal = new bootstrap.Modal(deleteModalEl);
        }
      },

      resetForm() {
        this.form       = { id: null, name: '', price: '', category_id: '', description: '' };
        this.formErrors = [];
      },

      openCreate() {
        this.resetForm();
        this.modalInstance?.show();
      },

      openEdit(p) {
        this.form = {
          id:          p.id,
          name:        p.name        || '',
          price:       p.price       ?? '',
          category_id: p.category_id ?? '',
          description: p.description || '',
        };
        this.formErrors = [];
        this.modalInstance?.show();
      },

      async saveProduct() {
        this.formErrors = [];
        if (!this.form.name || this.form.name.trim().length < 2) {
          this.formErrors.push('El nombre es obligatorio');
          return;
        }
        if (this.form.price === '' || Number.parseFloat(this.form.price) < 0) {
          this.formErrors.push('El precio debe ser mayor o igual a 0');
          return;
        }

        this.saving = true;
        const isEdit = !!this.form.id;
        const url    = isEdit ? `/api/v1/manager/products/${this.form.id}` : '/api/v1/manager/products';

        try {
          const res  = await fetch(url, {
            method:  isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken, ...this.form }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Producto guardado');
            this.modalInstance?.hide();
            globalThis.location.reload();
          } else {
            this.formErrors = data.errors
              ? Object.values(data.errors).flat()
              : [data.detail || 'Error al guardar'];
            KomorebiToast.error(this.formErrors[0]);
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.saving = false; }
      },

      async toggle(productId) {
        try {
          const res  = await fetch(`/api/v1/manager/products/${productId}/toggle`, {
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

      confirmDelete(productId, productName) {
        this.deleteTarget = { id: productId, name: productName };
        this.deleteModal?.show();
      },

      async doDelete() {
        if (!this.deleteTarget.id) { return; }
        try {
          const res  = await fetch(`/api/v1/manager/products/${this.deleteTarget.id}`, {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Producto eliminado');
            this.deleteModal?.hide();
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al eliminar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('managerProducts', createManagerProducts);
  });

})();
