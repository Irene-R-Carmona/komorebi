(function () {
  'use strict';

  function createUserManagement(config = {}) {
    return {
      // ── Estado modal ────────────────────────────────────────────
      csrfToken:     config.csrfToken     || '',
      currentUserId: config.currentUserId || null,

      isEditMode:    false,
      isSubmitting:  false,
      showPassword:  false,
      formErrors:    [],
      modalInstance: null,

      form: {
        id: null, name: '', email: '',
        password: '', password_confirm: '',
        role_id: '', is_active: true,
      },

      // ── Inicialización ──────────────────────────────────────────
      init() {
        const modalEl = document.getElementById('userModal');
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

      openEditModal(user) {
        this.isEditMode   = true;
        this.form         = {
          id:               user.id,
          name:             user.name      || '',
          email:            user.email     || '',
          password:         '',
          password_confirm: '',
          role_id:          user.role_id   || '',
          is_active:        user.is_active,
        };
        this.formErrors   = [];
        this.showPassword = false;
        this.modalInstance?.show();
      },

      closeModal() { this.modalInstance?.hide(); },

      resetForm() {
        this.form = {
          id: null, name: '', email: '',
          password: '', password_confirm: '',
          role_id: '', is_active: true,
        };
        this.formErrors   = [];
        this.showPassword = false;
        this.isEditMode   = false;
      },

      // ── Validación ──────────────────────────────────────────────
      validate() {
        const errors = [];
        if (!this.form.name || this.form.name.trim().length < 2)
          errors.push('El nombre debe tener al menos 2 caracteres');
        if (!this.form.email || !KomorebiForm.isValidEmail(this.form.email))
          errors.push('Introduce un email válido');
        if (!this.isEditMode && (!this.form.password || this.form.password.length < 8))
          errors.push('La contraseña debe tener al menos 8 caracteres');
        if (this.form.password && this.form.password !== this.form.password_confirm)
          errors.push('Las contraseñas no coinciden');
        if (!this.form.role_id)
          errors.push('Selecciona un rol');
        return errors;
      },

      get passwordStrength() {
        const p = this.form.password;
        if (!p) return '';
        let s = 0;
        if (p.length >= 8)               s++;
        if (p.length >= 12)              s++;
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
        if (/\d/.test(p))                s++;
        if (/[^a-zA-Z0-9]/.test(p))     s++;
        if (s <= 1) return 'weak';
        if (s <= 2) return 'medium';
        if (s <= 3) return 'strong';
        return 'very-strong';
      },

      get passwordStrengthText() {
        const map = { weak: 'Débil', medium: 'Media', strong: 'Fuerte', 'very-strong': 'Muy fuerte' };
        return map[this.passwordStrength] || '';
      },

      // ── CRUD ────────────────────────────────────────────────────
      async submitUser() {
        this.formErrors = this.validate();
        if (this.formErrors.length > 0) { KomorebiToast.error(this.formErrors[0]); return; }

        this.isSubmitting = true;
        try {
          const url    = this.isEditMode ? `/api/v1/admin/users/${this.form.id}` : '/api/v1/admin/users';
          const method = this.isEditMode ? 'PUT' : 'POST';
          const body   = new URLSearchParams({
            csrf_token: this.csrfToken,
            name:       this.form.name,
            email:      this.form.email,
            role_id:    this.form.role_id,
            is_active:  this.form.is_active ? '1' : '0',
          });
          if (this.form.password) {
            body.append('password',         this.form.password);
            body.append('password_confirm', this.form.password_confirm);
          }
          const res  = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body,
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Usuario guardado');
            this.closeModal();
            globalThis.location.reload();
          } else {
            this.formErrors = data.errors ? Object.values(data.errors).flat() : [data.detail || 'Error al guardar'];
            KomorebiToast.error(this.formErrors[0]);
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.isSubmitting = false; }
      },

      async toggleUserStatus(userId, userName, isActive) {
        if (!await KomorebiConfirm.toggle(`el usuario "${userName}"`, isActive)) return;
        try {
          const res  = await fetch(`/api/v1/admin/users/${userId}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ csrf_token: this.csrfToken }),
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

      async deleteUser(userId, userName) {
        if (userId === this.currentUserId) { KomorebiToast.warning('No puedes eliminarte a ti mismo'); return; }
        if (!await KomorebiConfirm.delete(`el usuario "${userName}"`)) return;
        try {
          const res  = await fetch(`/api/v1/admin/users/${userId}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            KomorebiToast.success(data.data?.message || 'Usuario eliminado');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(data.detail || 'Error al eliminar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },

      // ── UI helpers ──────────────────────────────────────────────
      getInitial(name)  { return (name || '?').charAt(0).toUpperCase(); },
      getAvatarClass()  { return ''; },
      canDelete(userId) { return userId !== this.currentUserId; },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('userManagement', createUserManagement);
  });

})();
