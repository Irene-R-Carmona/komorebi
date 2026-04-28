(function () {
  'use strict';

  const MODULE_NAMES = {
    user:        'Usuarios',
    cafe:        'Cafés',
    product:     'Productos',
    reservation: 'Reservas',
    review:      'Reseñas',
    animal:      'Animales',
    shift:       'Turnos',
    report:      'Reportes',
    setting:     'Configuración',
    role:        'Roles y Permisos',
    general:     'General',
  };

  function sortByName(arr) {
    arr.sort((a, b) => a.name.localeCompare(b.name));
  }

  function matchesSearch(p, term, mod) {
    return p.name.toLowerCase().includes(term) ||
      p.code.toLowerCase().includes(term) ||
      mod.toLowerCase().includes(term);
  }

  function createRoleManagement(config = {}) {
    return {
      permissions:     config.permissions     || [],
      rolePermissions: config.rolePermissions || {},
      csrfToken:       config.csrfToken       || '',

      saving:          false,
      savingPermission: false,
      activeTab:       'roles',

      // Form state (role modal)
      editingRole:  null,
      roleModal:    null,
      formErrors:   [],
      form: { id: null, code: '', name: '', description: '' },

      // Permissions modal state
      selectedRole:          null,
      permissionsModal:      null,
      permissionSearch:      '',
      currentPermissionPage: 1,
      permissionsPerPage:    3,

      // Computed-cache properties (updated by watchers)
      totalFilteredPermissions: 0,
      paginatedPermissions:     {},
      totalPermissionPages:     1,
      visiblePermissionPages:   [],

      init() {
        if (globalThis.location.hash === '#permisos') {
          this.activeTab = 'matrix';
        }

        const roleModalEl = document.getElementById('roleFormModal');
        if (roleModalEl) {
          this.roleModal = new bootstrap.Modal(roleModalEl);
          roleModalEl.addEventListener('hidden.bs.modal', () => this.resetForm());
        }

        const permModalEl = document.getElementById('permissionsModal');
        if (permModalEl) {
          this.permissionsModal = new bootstrap.Modal(permModalEl);
        }

        this.updatePaginatedPermissions();
        this.$watch('permissionSearch', () => {
          this.currentPermissionPage = 1;
          this.updatePaginatedPermissions();
        });
        this.$watch('currentPermissionPage', () => this.updatePaginatedPermissions());
      },

      // ── Helpers ───────────────────────────────────────────────────

      formatModuleName(module) {
        return MODULE_NAMES[module] || module.charAt(0).toUpperCase() + module.slice(1);
      },

      get permissionsByModule() {
        const grouped = {};
        this.permissions.forEach(p => {
          const mod = p.resource || 'general';
          if (!grouped[mod]) { grouped[mod] = []; }
          grouped[mod].push(p);
        });
        Object.values(grouped).forEach(sortByName);
        return grouped;
      },

      get filteredPermissionsByModule() {
        if (!this.permissionSearch.trim()) { return this.permissionsByModule; }
        const term = this.permissionSearch.toLowerCase().trim();
        const filtered = {};
        Object.entries(this.permissionsByModule).forEach(([mod, perms]) => {
          const matched = perms.filter(p => matchesSearch(p, term, mod));
          if (matched.length > 0) { filtered[mod] = matched; }
        });
        return filtered;
      },

      updatePaginatedPermissions() {
        const filtered  = this.filteredPermissionsByModule;
        const modules   = Object.keys(filtered);

        this.totalFilteredPermissions = Object.values(filtered).reduce((s, a) => s + a.length, 0);

        const start  = (this.currentPermissionPage - 1) * this.permissionsPerPage;
        const result = {};
        modules.slice(start, start + this.permissionsPerPage).forEach(mod => {
          result[mod] = filtered[mod];
        });
        this.paginatedPermissions = result;

        this.totalPermissionPages = Math.ceil(modules.length / this.permissionsPerPage) || 1;

        const total   = this.totalPermissionPages;
        const current = this.currentPermissionPage;
        const delta   = 2;
        const pages   = [];
        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
          pages.push(i);
        }
        if (current - delta > 2)     { pages.unshift('...'); }
        if (current + delta < total - 1) { pages.push('...'); }
        pages.unshift(1);
        if (total > 1) { pages.push(total); }
        this.visiblePermissionPages = pages.filter((v, i, a) =>
          (v !== '...' && a.indexOf(v) === i) || v === '...'
        );
      },

      // ── Permission matrix / modal ─────────────────────────────────

      hasPermission(roleId, permissionId) {
        return (this.rolePermissions[roleId] || []).includes(Number.parseInt(permissionId));
      },

      async togglePermission(roleId, permissionId, granted, checkbox = null) {
        this.savingPermission = true;
        try {
          const url = granted
            ? `/api/v1/admin/roles/${roleId}/permissions/${permissionId}/grant`
            : `/api/v1/admin/roles/${roleId}/permissions/${permissionId}/revoke`;

          const res  = await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body:    new URLSearchParams({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();

          if (res.ok && data.ok) {
            if (!this.rolePermissions[roleId]) { this.rolePermissions[roleId] = []; }
            const id = Number.parseInt(permissionId);
            if (granted) {
              this.rolePermissions[roleId].push(id);
            } else {
              this.rolePermissions[roleId] = this.rolePermissions[roleId].filter(x => x !== id);
            }
            KomorebiToast.success('Permiso actualizado');
          } else {
            KomorebiToast.error(data.detail || 'Error al actualizar permiso');
            if (checkbox) { checkbox.checked = !granted; }
          }
        } catch {
          KomorebiToast.error('Error de conexión');
          if (checkbox) { checkbox.checked = !granted; }
        } finally {
          this.savingPermission = false;
        }
      },

      openPermissionsModal(role) {
        this.selectedRole    = role;
        this.permissionSearch = '';
        this.permissionsModal?.show();
      },

      async togglePermissionInModal(permissionId, checkbox = null) {
        if (!this.selectedRole) return;
        const granted = !this.hasPermission(this.selectedRole.id, permissionId);
        await this.togglePermission(this.selectedRole.id, permissionId, granted, checkbox);
      },

      // ── Role CRUD ─────────────────────────────────────────────────

      openCreateModal() {
        this.editingRole = null;
        this.resetForm();
        this.roleModal?.show();
      },

      openEditModal(role) {
        this.editingRole = role;
        this.form        = { id: role.id, code: role.code, name: role.name, description: role.description || '' };
        this.formErrors  = [];
        this.roleModal?.show();
      },

      resetForm() {
        this.form        = { id: null, code: '', name: '', description: '' };
        this.formErrors  = [];
        this.editingRole = null;
      },

      async submitRole() {
        this.formErrors = [];
        if (!this.form.name || this.form.name.trim().length < 3) {
          this.formErrors.push('El nombre debe tener al menos 3 caracteres');
        }
        if (!this.editingRole && (!this.form.code || !/^[a-z_]+$/.test(this.form.code))) {
          this.formErrors.push('El código solo puede contener letras minúsculas y guiones bajos');
        }
        if (this.formErrors.length > 0) { return; }

        this.saving = true;
        try {
          const url    = this.editingRole ? `/api/v1/admin/roles/${this.editingRole.id}` : '/api/v1/admin/roles';
          const body   = this.editingRole
            ? { csrf_token: this.csrfToken, name: this.form.name, description: this.form.description }
            : { csrf_token: this.csrfToken, code: this.form.code, name: this.form.name, description: this.form.description };
          const result = await KomorebiForm.submit(url, body, { method: this.editingRole ? 'PUT' : 'POST' });

          if (result.success) {
            KomorebiToast.success(result.data?.message || 'Rol guardado correctamente');
            this.roleModal?.hide();
            setTimeout(() => globalThis.location.reload(), 800);
          } else {
            this.formErrors = [result.data?.message || 'Error al guardar'];
            KomorebiToast.error(this.formErrors[0]);
          }
        } catch { KomorebiToast.error('Error de conexión'); }
        finally  { this.saving = false; }
      },

      async confirmDelete(roleId, roleName, isSystem) {
        if (isSystem) { KomorebiToast.warning('No se pueden eliminar roles del sistema'); return; }
        if (!await KomorebiConfirm.delete(`el rol "${roleName}"`)) return;
        try {
          const result = await KomorebiForm.submit(
            `/api/v1/admin/roles/${roleId}`,
            { csrf_token: this.csrfToken },
            { method: 'DELETE' }
          );
          if (result.success) {
            KomorebiToast.success(result.data?.message || 'Rol eliminado');
            globalThis.location.reload();
          } else {
            KomorebiToast.error(result.data?.message || 'Error al eliminar');
          }
        } catch { KomorebiToast.error('Error de conexión'); }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('roleManagement', createRoleManagement);
  });

})();
