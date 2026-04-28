(function () {
  'use strict';

  function createManagerCafe(config = {}) {
    return {
      csrfToken:   config.csrfToken || '',
      activeTab:   'info',
      showMessage: false,
      message:     '',
      messageType: 'success',

      scheduleForm: {
        opening_time: config.openingTime || '',
        closing_time: config.closingTime || '',
      },
      capacityForm: {
        capacity_max: config.capacityMax || 1,
      },
      settingsForm: {
        description:    config.description    || '',
        price_per_hour: config.pricePerHour   || 0,
      },

      showMsg(msg, ok) {
        this.message     = msg;
        this.messageType = ok ? 'success' : 'error';
        this.showMessage = true;
        setTimeout(() => { this.showMessage = false; }, 3000);
      },

      async updateSchedule() {
        try {
          const res  = await fetch('/api/v1/manager/cafe/schedule', {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken, ...this.scheduleForm }),
          });
          const data = await res.json();
          this.showMsg(
            data.ok ? (data.data?.message || 'Horarios actualizados correctamente') : (data.error || 'Error'),
            data.ok,
          );
          if (data.ok) { setTimeout(() => { globalThis.location.reload(); }, 1500); }
        } catch {
          this.showMsg('Error al actualizar los horarios', false);
        }
      },

      async updateCapacity() {
        try {
          const res  = await fetch('/api/v1/manager/cafe/capacity', {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken, ...this.capacityForm }),
          });
          const data = await res.json();
          this.showMsg(
            data.ok ? (data.data?.message || 'Capacidad actualizada correctamente') : (data.error || 'Error'),
            data.ok,
          );
          if (data.ok) { setTimeout(() => { globalThis.location.reload(); }, 1500); }
        } catch {
          this.showMsg('Error al actualizar la capacidad', false);
        }
      },

      async updateSettings() {
        try {
          const res  = await fetch('/api/v1/manager/cafe/settings', {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ csrf_token: this.csrfToken, ...this.settingsForm }),
          });
          const data = await res.json();
          this.showMsg(
            data.ok ? (data.data?.message || 'Configuración actualizada correctamente') : (data.error || 'Error'),
            data.ok,
          );
          if (data.ok) { setTimeout(() => { globalThis.location.reload(); }, 1500); }
        } catch {
          this.showMsg('Error al actualizar la configuración', false);
        }
      },
    };
  }

  document.addEventListener('alpine:init', () => {
    Alpine.data('managerCafe', createManagerCafe);
  });

})();
