/**
 * Gestión de Roles y Permisos
 * ============================================================================
 * Sistema de manejo de roles y permisos del área administrativa.
 *
 * Componentes Alpine:
 * - roleManagement: Gestión de roles y permisos del sistema
 *
 * @version 1.0.0
 * @requires Alpine.js
 * @requires admin-common.js
 */

(function () {
  'use strict';

  // Mapeo de módulos para nombres amigables
  const MODULE_NAMES = {
    'user': 'Usuarios',
    'cafe': 'Cafés',
    'product': 'Productos',
    'reservation': 'Reservas',
    'review': 'Reseñas',
    'animal': 'Animales',
    'shift': 'Turnos',
    'report': 'Reportes',
    'setting': 'Configuración',
    'role': 'Roles y Permisos',
    'general': 'General'
  };

  // ========================================================================
  // COMPONENTE ALPINE: Gestión de Roles
  // ========================================================================

  document.addEventListener('alpine:init', () => {

    Alpine.data('roleManagement', (config = {}) => ({
      // ─────────────────────────────────────────────────────────────
      // ESTADO INICIAL
      // ─────────────────────────────────────────────────────────────

      roles: config.roles || [],
      permissions: config.permissions || [],
      rolePermissions: config.rolePermissions || {}, // { roleId: [permissionIds] }
      stats: config.stats || {},
      csrfToken: config.csrfToken || '',

      // ─────────────────────────────────────────────────────────────
      // ESTADO UI
      // ─────────────────────────────────────────────────────────────

      loading: false,
      saving: false,
      savingPermission: false,
      activeTab: 'roles',

      // ─────────────────────────────────────────────────────────────
      // FORMULARIO ROL

      // ─────────────────────────────────────────────────────────────
      // MODAL PERMISOS Y PAGINACIÓN
      // ─────────────────────────────────────────────────────────────

      selectedRole: null,
      permissionSearch: '',
      currentPermissionPage: 1,
      permissionsPerPage: 3, // 3 módulos por página

      // Propiedades calculadas (actualizadas en init y watchers)
      totalFilteredPermissions: 0,
      paginatedPermissions: {},
      totalPermissionPages: 1,
      visiblePermissionPages: [],

      // ─────────────────────────────────────────────────────────────
      // INICIALIZACIÓN
      // ─────────────────────────────────────────────────────────────

      init() {
        // Auto-switch a matriz si hay hash #permisos
        if (window.location.hash === '#permisos') {
          this.activeTab = 'matrix';
        }

        // Normalizar datos de roles
        this.roles = this.roles.map(role => ({
          ...role,
          isSystem: this.isSystemRole(role.code),
          permissions_count: parseInt(role.permissions_count) || 0,
          users_count: parseInt(role.users_count) || 0
        }));

        // Inicializar modales
        const roleModalEl = document.getElementById('roleModal');
        if (roleModalEl) {
          this.roleModal = new bootstrap.Modal(roleModalEl);
          roleModalEl.addEventListener('hidden.bs.modal', () => {
            this.resetForm();
          });
        }

        const permModalEl = document.getElementById('permissionsModal');
        if (permModalEl) {
          this.permissionsModal = new bootstrap.Modal(permModalEl);
        }

        // Calcular propiedades paginadas
        this.updatePaginatedPermissions();

        // Watchers para recalcular
        this.$watch('permissionSearch', () => {
          this.currentPermissionPage = 1;
          this.updatePaginatedPermissions();
        });

        this.$watch('currentPermissionPage', () => {
          this.updatePaginatedPermissions();
        });

        console.log('[RoleManagement] Inicializado', {
          roles: this.roles.length,
          permissions: this.permissions.length,
          permissionsByModule: Object.keys(this.permissionsByModule).length,
          totalFilteredPermissions: this.totalFilteredPermissions,
          paginatedPermissions: Object.keys(this.paginatedPermissions).length
        });
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: ESTADÍSTICAS
      // ─────────────────────────────────────────────────────────────

      get totalRoles() {
        return this.roles.length;
      },

      get totalPermissions() {
        return this.permissions.length;
      },

      get totalModules() {
        return Object.keys(this.permissionsByModule).length;
      },

      // ─────────────────────────────────────────────────────────────
      // COMPUTED: PERMISOS AGRUPADOS
      // ─────────────────────────────────────────────────────────────

      get permissionsByModule() {
        const grouped = {};

        this.permissions.forEach(permission => {
          const module = permission.resource || 'general';
          if (!grouped[module]) {
            grouped[module] = [];
          }
          grouped[module].push(permission);
        });

        // Ordenar por nombre dentro de cada módulo
        Object.keys(grouped).forEach(module => {
          grouped[module].sort((a, b) => a.name.localeCompare(b.name));
        });

        return grouped;
      },

      get filteredPermissionsByModule() {
        if (!this.permissionSearch.trim()) {
          return this.permissionsByModule;
        }

        const searchTerm = this.permissionSearch.toLowerCase().trim();
        const filtered = {};

        Object.keys(this.permissionsByModule).forEach(module => {
          const matchingPerms = this.permissionsByModule[module].filter(perm =>
            perm.name.toLowerCase().includes(searchTerm) ||
            perm.code.toLowerCase().includes(searchTerm) ||
            module.toLowerCase().includes(searchTerm)
          );

          if (matchingPerms.length > 0) {
            filtered[module] = matchingPerms;
          }
        });

        return filtered;
      },

      // Actualiza propiedades calculadas de paginación
      updatePaginatedPermissions() {
        const filtered = this.filteredPermissionsByModule;

        // Total de permisos filtrados
        this.totalFilteredPermissions = Object.values(filtered)
          .reduce((sum, perms) => sum + perms.length, 0);

        // Permisos paginados
        const modules = Object.keys(filtered);
        const start = (this.currentPermissionPage - 1) * this.permissionsPerPage;
        const end = start + this.permissionsPerPage;
        const paginatedModules = modules.slice(start, end);

        const result = {};
        paginatedModules.forEach(module => {
          result[module] = filtered[module];
        });
        this.paginatedPermissions = result;

        // Total de páginas
        this.totalPermissionPages = Math.ceil(modules.length / this.permissionsPerPage) || 1;

        // Páginas visibles
        const pages = [];
        const total = this.totalPermissionPages;
        const current = this.currentPermissionPage;
        const delta = 2;

        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
          pages.push(i);
        }

        if (current - delta > 2) {
          pages.unshift('...');
        }
        if (current + delta < total - 1) {
          pages.push('...');
        }

        pages.unshift(1);
        if (total > 1) {
          pages.push(total);
        }

        this.visiblePermissionPages = pages.filter((v, i, a) => a.indexOf(v) === i && v !== '...' || v === '...');
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: HELPERS
      // ─────────────────────────────────────────────────────────────

      isSystemRole(code) {
        return ['admin', 'user'].includes(code);
      },

      formatModuleName(module) {
        return MODULE_NAMES[module] || module.charAt(0).toUpperCase() + module.slice(1);
      },

      getRoleBadgeClass(code) {
        const classes = {
          'admin': 'role-list-badge--admin',
          'manager': 'role-list-badge--manager',
          'supervisor': 'role-list-badge--supervisor',
          'reception': 'role-list-badge--reception',
          'kitchen': 'role-list-badge--kitchen',
          'keeper': 'role-list-badge--keeper',
          'user': 'role-list-badge--user'
        };
        return classes[code] || 'role-list-badge--user';
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: PERMISOS
      // ─────────────────────────────────────────────────────────────

      hasPermission(roleId, permissionId) {
        const rolePerms = this.rolePermissions[roleId] || [];
        return rolePerms.includes(parseInt(permissionId));
      },

      async togglePermission(roleId, permissionId, granted) {
        // No permitir modificar roles de sistema en la matriz
        const role = this.roles.find(r => r.id === roleId);
        if (role && role.isSystem) {
          KomorebiToast.warning('No se pueden modificar permisos de roles del sistema desde la matriz');
          return;
        }

        this.savingPermission = true;

        try {
          const url = granted
            ? `/admin/roles/${roleId}/permissions/${permissionId}/grant`
            : `/admin/roles/${roleId}/permissions/${permissionId}/revoke`;

          const response = await fetch(url, {
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
            if (!this.rolePermissions[roleId]) {
              this.rolePermissions[roleId] = [];
            }

            if (granted) {
              this.rolePermissions[roleId].push(parseInt(permissionId));
            } else {
              this.rolePermissions[roleId] = this.rolePermissions[roleId].filter(
                id => id !== parseInt(permissionId)
              );
            }

            // Actualizar contador en el rol
            const role = this.roles.find(r => r.id === roleId);
            if (role) {
              role.permissions_count = this.rolePermissions[roleId].length;
            }

            KomorebiToast.success('Permiso actualizado');
          } else {
            KomorebiToast.error(data.message || 'Error al actualizar permiso');
            event.target.checked = !granted;
          }
        } catch (error) {
          console.error('[RoleManagement] Error al alternar permiso:', error);
          KomorebiToast.error('Error de conexión');
          event.target.checked = !granted;
        } finally {
          this.savingPermission = false;
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: MODAL FORMULARIO
      // ─────────────────────────────────────────────────────────────

      openCreateModal() {
        this.editingRole = null;
        this.resetForm();
        this.roleModal?.show();
      },

      openEditModal(role) {
        if (role.isSystem) {
          KomorebiToast.warning('No se pueden editar roles del sistema');
          return;
        }

        this.editingRole = role;
        this.form = {
          id: role.id,
          code: role.code,
          name: role.name,
          description: role.description || ''
        };
        this.formErrors = [];
        this.roleModal?.show();
      },

      resetForm() {
        this.form = {
          id: null,
          code: '',
          name: '',
          description: ''
        };
        this.formErrors = [];
        this.editingRole = null;
      },

      async submitRole() {
        // Validación
        this.formErrors = [];

        if (!this.form.name || this.form.name.trim().length < 3) {
          this.formErrors.push('El nombre debe tener al menos 3 caracteres');
        }

        if (!this.editingRole) {
          // Código solo válido al crear
          if (!this.form.code || !/^[a-z_]+$/.test(this.form.code)) {
            this.formErrors.push('El código solo puede contener letras minúsculas y guiones bajos');
          }
        }

        if (this.formErrors.length > 0) {
          return;
        }

        this.saving = true;

        try {
          const url = this.editingRole
            ? `/admin/roles/${this.editingRole.id}/edit`
            : '/admin/roles/create';

          const body = this.editingRole
            ? {
              csrf_token: this.csrfToken,
              name: this.form.name,
              description: this.form.description
            }
            : {
              csrf_token: this.csrfToken,
              code: this.form.code,
              name: this.form.name,
              description: this.form.description
            };

          const result = await KomorebiForm.submit(url, body);

          if (result.success) {
            KomorebiToast.success(result.data?.message || 'Rol guardado correctamente');
            this.roleModal?.hide();
            setTimeout(() => window.location.reload(), 800);
          } else {
            this.formErrors = [result.data?.message || 'Error al guardar'];
            KomorebiToast.error(this.formErrors[0]);
          }
        } catch (error) {
          console.error('[RoleManagement] Error al guardar:', error);
          KomorebiToast.error('Error de conexión');
        } finally {
          this.saving = false;
        }
      },

      async confirmDelete(role) {
        if (role.isSystem) {
          KomorebiToast.warning('No se pueden eliminar roles del sistema');
          return;
        }

        if (!await KomorebiConfirm.delete(`el rol "${role.name}"`)) {
          return;
        }

        try {
          const result = await KomorebiForm.submit(
            `/admin/roles/${role.id}/delete`,
            { csrf_token: this.csrfToken }
          );

          if (result.success) {
            const index = this.roles.findIndex(r => r.id === role.id);
            if (index > -1) {
              this.roles.splice(index, 1);
            }
            KomorebiToast.success(result.data?.message || 'Rol eliminado');
          } else {
            KomorebiToast.error(result.data?.message || 'Error al eliminar');
          }
        } catch (error) {
          console.error('[RoleManagement] Error al eliminar:', error);
          KomorebiToast.error('Error de conexión');
        }
      },

      // ─────────────────────────────────────────────────────────────
      // MÉTODOS: MODAL PERMISOS
      // ─────────────────────────────────────────────────────────────

      openPermissionsModal(role) {
        this.selectedRole = role;
        this.permissionSearch = '';
        this.permissionsModal?.show();
      },

      async togglePermissionInModal(permissionId) {
        if (!this.selectedRole) return;

        const granted = !this.hasPermission(this.selectedRole.id, permissionId);
        await this.togglePermission(this.selectedRole.id, permissionId, granted);
      }
    }));
  });

  // ========================================================================
  // INICIALIZACIÓN
  // ========================================================================

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[AdminRoles] Módulo cargado');
  });

})();
