(function () {
  'use strict';

  function createManagerStaff(config = {}) {
    return {
      csrfToken: config.csrfToken || '',
      activeTab: 'staff',
      showMessage: false,
      message: '',
      messageType: 'success',
      showShiftModal: false,
      showEditModal: false,
      saving: false,

      shiftForm: {
        user_id: '',
        shift_date: '',
        shift_start: '',
        shift_end: '',
        notes: '',
      },

      editForm: {
        shift_id: 0,
        shift_date: '',
        shift_start: '',
        shift_end: '',
        notes: '',
      },

      showMsg(msg, ok) {
        this.message = msg;
        this.messageType = ok ? 'success' : 'error';
        this.showMessage = true;
        setTimeout(() => { this.showMessage = false; }, 3000);
      },

      openShiftModal() {
        this.shiftForm = { user_id: '', shift_date: '', shift_start: '', shift_end: '', notes: '' };
        this.showShiftModal = true;
      },

      closeShiftModal() {
        this.showShiftModal = false;
      },

      openEditModal(shift) {
        if (!shift || !shift.id) return;
        this.editForm = {
          shift_id: shift.id,
          shift_date: shift.shift_date || '',
          shift_start: shift.shift_start || '',
          shift_end: shift.shift_end || '',
          notes: shift.notes || '',
        };
        this.showEditModal = true;
      },

      closeEditModal() {
        this.showEditModal = false;
      },

      async assignShift() {
        if (!this.shiftForm.user_id || !this.shiftForm.shift_date ||
          !this.shiftForm.shift_start || !this.shiftForm.shift_end) {
          this.showMsg('Completa todos los campos requeridos', false);
          return;
        }

        this.saving = true;
        try {
          const res = await fetch('/api/v1/manager/staff/assign-shift', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ csrf_token: this.csrfToken, ...this.shiftForm }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            this.showMsg(data.data?.message || 'Turno asignado correctamente', true);
            this.closeShiftModal();
            setTimeout(() => { globalThis.location.reload(); }, 1500);
          } else {
            this.showMsg(data.error || data.detail || 'Error al asignar turno', false);
          }
        } catch {
          this.showMsg('Error de conexión', false);
        } finally {
          this.saving = false;
        }
      },

      async updateShift() {
        if (!this.editForm.shift_date || !this.editForm.shift_start || !this.editForm.shift_end) {
          this.showMsg('Fecha y horas son obligatorias', false);
          return;
        }

        this.saving = true;
        try {
          const res = await fetch(`/api/v1/manager/staff/shifts/${this.editForm.shift_id}`, {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': this.csrfToken,
            },
            body: new URLSearchParams({
              csrf_token: this.csrfToken,
              shift_date: this.editForm.shift_date,
              shift_start: this.editForm.shift_start,
              shift_end: this.editForm.shift_end,
              notes: this.editForm.notes,
            }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            this.showMsg('Turno actualizado correctamente', true);
            this.closeEditModal();
            setTimeout(() => { globalThis.location.reload(); }, 1200);
          } else {
            this.showMsg(data.error || 'Error al actualizar turno', false);
          }
        } catch {
          this.showMsg('Error de conexión', false);
        } finally {
          this.saving = false;
        }
      },

      async deleteShift(shiftId) {
        if (!shiftId) return;
        if (!globalThis.confirm('¿Eliminar este turno? Esta acción no se puede deshacer.')) return;

        this.saving = true;
        try {
          const res = await fetch(`/api/v1/manager/staff/shifts/${shiftId}`, {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': this.csrfToken,
            },
            body: new URLSearchParams({ csrf_token: this.csrfToken }),
          });
          const data = await res.json();
          if (res.ok && data.ok) {
            this.showMsg('Turno eliminado correctamente', true);
            setTimeout(() => { globalThis.location.reload(); }, 1200);
          } else {
            this.showMsg(data.error || 'Error al eliminar turno', false);
          }
        } catch {
          this.showMsg('Error de conexión', false);
        } finally {
          this.saving = false;
        }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('managerStaff', createManagerStaff);
  });

})();
