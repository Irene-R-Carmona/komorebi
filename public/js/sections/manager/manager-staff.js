(function () {
  'use strict';

  function createManagerStaff(config = {}) {
    return {
      csrfToken:   config.csrfToken || '',
      activeTab:   'staff',
      showMessage: false,
      message:     '',
      messageType: 'success',
      showShiftModal: false,
      saving:         false,

      shiftForm: {
        user_id:     '',
        shift_date:  '',
        shift_start: '',
        shift_end:   '',
        notes:       '',
      },

      showMsg(msg, ok) {
        this.message     = msg;
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

      async assignShift() {
        if (!this.shiftForm.user_id || !this.shiftForm.shift_date ||
            !this.shiftForm.shift_start || !this.shiftForm.shift_end) {
          this.showMsg('Completa todos los campos requeridos', false);
          return;
        }

        this.saving = true;
        try {
          const res  = await fetch('/api/v1/manager/staff/assign-shift', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body:    new URLSearchParams({ csrf_token: this.csrfToken, ...this.shiftForm }),
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
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('managerStaff', createManagerStaff);
  });

})();
