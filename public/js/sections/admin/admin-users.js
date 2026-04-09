/**
 * Gestión de Usuarios del Admin
 * ============================================================================
 * Sistema de manejo de usuarios con Alpine.js.
 * Incluye listado con filtros, ordenamiento, paginación y operaciones CRUD.
 *
 * Componentes Alpine:
 * - userManagement: Gestión completa de usuarios
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  // ========================================================================
  // COMPONENTE ALPINE: Gestión de Usuarios
  // ========================================================================

  document.addEventListener('alpine:init', () => {

    /**
     * Componente principal de gestión de usuarios
     * @param {Object} config - Configuración inicial
     */
    Alpine.data('userManagement', (config = {}) => ({
      // ─────────────────────────────────────────────────────────────
      // ESTADO INICIAL
      // ─────────────────────────────────────────────────────────────

      users: config.users || [],
      availableRoles: config.roles || [],
      currentUserId: config.currentUserId || null,
      csrfToken: config.csrfToken || '',

      // ─────────────────────────────────────────────────────────────
      // FORMULARIO
      // ─────────────────────────────────────────────────────────────

      form: {
        id: null,
        name: '',
        email: '',
        password: '',
        password_confirm: '',
        role_id: '',
        is_active: true
      },

      // ─────────────────────────────────────────────────────────────
      // ESTADO UI
      // ─────────────────────────────────────────────────────────────

      isEditMode: false,
      isSubmitting: false,
      showPassword: false,
      formErrors: [],
      modalInstance: null,

      // ─────────────────────────────────────────────────────────────
      // FILTROS Y BÚSQUEDA
      // ─────────────────────────────────────────────────────────────

      searchQuery: '',
      filterStatus: 'all', // all, active, inactive
      filterRole: '',

      // ─────────────────────────────────────────────────────────────
      // ORDENAMIENTO
      // ─────────────────────────────────────────────────────────────

      sortColumn: 'id',
      sortDirection: 'desc',

      // ─────────────────────────────────────────────────────────────
      // PAGINACIÓN
      // ─────────────────────────────────────────────────────────────

      currentPage: 1,
      perPage: 10,

      // ─────────────────────────────────────────────────────────────
      // INICIALIZACIÓN
      // ─────────────────────────────────────────────────────────────

      init() {
        // Normalizar datos de usuarios
        this.users = this.users.map(user => ({
          ...user,
          is_active: Boolean(Number(user.is_active)),
          roles: this.normalizeRoles(user.roles)
        }));

        // Inicializar modal de Bootstrap
        const modalEl = document.getElementById('userModal');
        if (modalEl) {
          this.modalInstance = new bootstrap.Modal(modalEl);

          // Limpiar formulario al cerrar modal
          modalEl.addEventListener('hidden.bs.modal', () => {
            this.resetForm();
          });
        }

        console.log('[UserManagement] Initialized with', this.users.length, 'users');
      },

      /**
       * Normaliza el campo roles a array
       */
      normalizeRoles(roles) {
        if (Array.isArray(roles)) return roles;
        if (typeof roles === 'string' && roles) {
          return roles.split(',').map(r => r.trim()).filter(Boolean);
        }
        return [];
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: ESTADÍSTICAS
      // ─────────────────────────────────────────────────────────────

      get totalUsers() {
        return this.users.length;
      },

      get activeUsersCount() {
        return this.users.filter(u => u.is_active).length;
      },

      get inactiveUsersCount() {
        return this.users.filter(u => !u.is_active).length;
      },

      get adminUsersCount() {
        return this.users.filter(u =>
          u.roles.some(r => r.toLowerCase() === 'admin')
        ).length;
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: FILTRADO Y ORDENAMIENTO
      // ─────────────────────────────────────────────────────────────

      get filteredUsers() {
        let filtered = [...this.users];

        // Filtro por estado
        if (this.filterStatus === 'active') {
          filtered = filtered.filter(u => u.is_active);
        } else if (this.filterStatus === 'inactive') {
          filtered = filtered.filter(u => !u.is_active);
        }

        // Filtro por rol
        if (this.filterRole) {
          filtered = filtered.filter(u =>
            u.roles.some(r => r.toLowerCase() === this.filterRole.toLowerCase())
          );
        }

        // Búsqueda
        if (this.searchQuery.trim()) {
          const query = this.searchQuery.toLowerCase().trim();
          filtered = filtered.filter(u =>
            u.name?.toLowerCase().includes(query) ||
            u.email?.toLowerCase().includes(query) ||
            u.roles.some(r => r.toLowerCase().includes(query))
          );
        }

        // Ordenamiento
        filtered.sort((a, b) => {
          let aVal = a[this.sortColumn];
          let bVal = b[this.sortColumn];

          // Manejar valores nulos
          if (aVal === null || aVal === undefined) aVal = '';
          if (bVal === null || bVal === undefined) bVal = '';

          // Comparación
          let comparison = 0;
          if (typeof aVal === 'string') {
            comparison = aVal.localeCompare(bVal);
          } else {
            comparison = aVal < bVal ? -1 : (aVal > bVal ? 1 : 0);
          }

          return this.sortDirection === 'asc' ? comparison : -comparison;
        });

        return filtered;
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: PAGINACIÓN
      // ─────────────────────────────────────────────────────────────

      get totalPages() {
        return Math.ceil(this.filteredUsers.length / this.perPage);
      },

      get startIndex() {
        return (this.currentPage - 1) * this.perPage;
      },

      get endIndex() {
        return Math.min(this.startIndex + this.perPage, this.filteredUsers.length);
      },

      get paginatedUsers() {
        return this.filteredUsers.slice(this.startIndex, this.endIndex);
      },

      get visiblePages() {
        const pages = [];
        const maxVisible = 5;
        let start = Math.max(1, this.currentPage - 2);
        let end = Math.min(this.totalPages, start + maxVisible - 1);

        if (end - start < maxVisible - 1) {
          start = Math.max(1, end - maxVisible + 1);
        }

        for (let i = start; i <= end; i++) {
          pages.push(i);
        }

        return pages;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: ORDENAMIENTO
      // ─────────────────────────────────────────────────────────────

      sortBy(column) {
        if (this.sortColumn === column) {
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortColumn = column;
          this.sortDirection = 'asc';
        }
        this.currentPage = 1;
      },

      getSortIcon(column) {
        if (this.sortColumn !== column) {
          return 'bi-arrow-down-up';
        }
        return this.sortDirection === 'asc' ? 'bi-sort-down' : 'bi-sort-up';
      },

      isSortedBy(column) {
        return this.sortColumn === column;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: PAGINACIÓN
      // ─────────────────────────────────────────────────────────────

      goToPage(page) {
        if (page >= 1 && page <= this.totalPages) {
          this.currentPage = page;
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: MODAL
      // ─────────────────────────────────────────────────────────────

      openCreateModal() {
        this.isEditMode = false;
        this.resetForm();
        this.form.is_active = true;
        this.form.role_id = this.availableRoles.find(r => r.code === 'user')?.id || '';
        this.modalInstance?.show();
      },

      openEditModal(user) {
        this.isEditMode = true;
        this.form = {
          id: user.id,
          name: user.name || '',
          email: user.email || '',
          password: '',
          password_confirm: '',
          role_id: user.role_id || '',
          is_active: user.is_active
        };
        this.formErrors = [];
        this.showPassword = false;
        this.modalInstance?.show();
      },

      closeModal() {
        this.modalInstance?.hide();
      },

      resetForm() {
        this.form = {
          id: null,
          name: '',
          email: '',
          password: '',
          password_confirm: '',
          role_id: '',
          is_active: true
        };
        this.formErrors = [];
        this.showPassword = false;
        this.isEditMode = false;
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: VALIDACIÓN
      // ─────────────────────────────────────────────────────────────

      validate() {
        const errors = [];

        // Nombre
        if (!this.form.name || this.form.name.trim().length < 2) {
          errors.push('El nombre debe tener al menos 2 caracteres');
        }

        // Email
        if (!this.form.email || !KomorebiForm.isValidEmail(this.form.email)) {
          errors.push('Introduce un email válido');
        }

        // Password (solo requerido en creación)
        if (!this.isEditMode) {
          if (!this.form.password || this.form.password.length < 8) {
            errors.push('La contraseña debe tener al menos 8 caracteres');
          }
        }

        // Confirmar password (si se introdujo password)
        if (this.form.password && this.form.password !== this.form.password_confirm) {
          errors.push('Las contraseñas no coinciden');
        }

        // Rol
        if (!this.form.role_id) {
          errors.push('Selecciona un rol');
        }

        return errors;
      },

      /**
       * Calcula la fortaleza de la contraseña
       * @returns {string} weak|medium|strong|very-strong
       */
      get passwordStrength() {
        const password = this.form.password;
        if (!password) return '';

        let strength = 0;

        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;

        if (strength <= 1) return 'weak';
        if (strength <= 2) return 'medium';
        if (strength <= 3) return 'strong';
        return 'very-strong';
      },

      get passwordStrengthText() {
        const texts = {
          'weak': 'Débil',
          'medium': 'Media',
          'strong': 'Fuerte',
          'very-strong': 'Muy fuerte'
        };
        return texts[this.passwordStrength] || '';
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: CRUD
      // ─────────────────────────────────────────────────────────────

      async submitUser() {
        // Validar
        this.formErrors = this.validate();
        if (this.formErrors.length > 0) {
          KomorebiToast.error(this.formErrors[0]);
          return;
        }

        this.isSubmitting = true;

        try {
          const url = this.isEditMode
            ? `/admin/usuarios/${this.form.id}/edit`
            : '/admin/usuarios/create';

          const formData = new URLSearchParams({
            csrf_token: this.csrfToken,
            name: this.form.name,
            email: this.form.email,
            role_id: this.form.role_id,
            is_active: this.form.is_active ? '1' : '0'
          });

          // Solo incluir password si se proporcionó
          if (this.form.password) {
            formData.append('password', this.form.password);
            formData.append('password_confirm', this.form.password_confirm);
          }

          const response = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          });

          const data = await response.json();

          if (response.ok && data.success) {
            KomorebiToast.success(data.message || 'Usuario guardado correctamente');
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
          console.error('[UserManagement] Submit error:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.isSubmitting = false;
        }
      },

      async toggleUserStatus(userId) {
        const user = this.users.find(u => u.id === userId);
        if (!user) return;

        if (!await KomorebiConfirm.toggle(`el usuario "${user.name}"`, user.is_active)) {
          return;
        }

        try {
          const response = await fetch(`/admin/usuarios/${userId}/toggle-active`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ csrf_token: this.csrfToken })
          });

          const data = await response.json();

          if (response.ok && data.success) {
            // Actualizar estado local
            user.is_active = !user.is_active;
            KomorebiToast.success(data.message || 'Estado actualizado');
          } else {
            KomorebiToast.error(data.message || 'Error al actualizar');
          }
        } catch (error) {
          console.error('[UserManagement] Toggle error:', error);
          KomorebiToast.error('Error de conexión');
        }
      },

      async deleteUser(userId) {
        const user = this.users.find(u => u.id === userId);
        if (!user) return;

        // No permitir eliminarse a sí mismo
        if (userId === this.currentUserId) {
          KomorebiToast.warning('No puedes eliminarte a ti mismo');
          return;
        }

        if (!await KomorebiConfirm.delete(`el usuario "${user.name}"`)) {
          return;
        }

        try {
          const response = await fetch(`/admin/usuarios/${userId}/delete`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ csrf_token: this.csrfToken })
          });

          const data = await response.json();

          if (response.ok && data.success) {
            // Remover de la lista local
            const index = this.users.findIndex(u => u.id === userId);
            if (index > -1) {
              this.users.splice(index, 1);
            }

            KomorebiToast.success(data.message || 'Usuario eliminado');

            // Ajustar paginación si es necesario
            if (this.currentPage > this.totalPages && this.totalPages > 0) {
              this.currentPage = this.totalPages;
            }
          } else {
            KomorebiToast.error(data.message || 'Error al eliminar');
          }
        } catch (error) {
          console.error('[UserManagement] Delete error:', error);
          KomorebiToast.error('Error de conexión');
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: UTILIDADES UI
      // ─────────────────────────────────────────────────────────────

      /**
       * Obtiene la inicial del usuario para el avatar
       */
      getInitial(name) {
        return (name || '?').charAt(0).toUpperCase();
      },

      /**
       * Obtiene la clase CSS del badge de rol
       */
      getRoleBadgeClass(role) {
        const classes = {
          'admin': 'role-badge--admin',
          'manager': 'role-badge--manager',
          'supervisor': 'role-badge--supervisor',
          'reception': 'role-badge--reception',
          'kitchen': 'role-badge--kitchen',
          'keeper': 'role-badge--keeper',
          'user': 'role-badge--user'
        };
        return classes[role?.toLowerCase()] || 'role-badge--user';
      },

      /**
       * Obtiene la clase CSS del avatar basado en rol principal
       */
      getAvatarClass(roles) {
        if (!roles || roles.length === 0) return '';

        const primaryRole = roles[0]?.toLowerCase();
        const classes = {
          'admin': 'user-avatar--admin',
          'manager': 'user-avatar--manager',
          'supervisor': 'user-avatar--supervisor'
        };
        return classes[primaryRole] || '';
      },

      /**
       * Formatea fecha a formato legible
       */
      formatDate(dateString) {
        if (!dateString) return '-';
        try {
          const date = new Date(dateString);
          return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
          }).format(date);
        } catch (e) {
          return dateString;
        }
      },

      /**
       * Verifica si un usuario puede ser eliminado
       */
      canDelete(userId) {
        return userId !== this.currentUserId;
      }
    }));
  });

  // ========================================================================
  // INITIALIZATION
  // ========================================================================

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminUsers] Module loaded');
  });

})();
