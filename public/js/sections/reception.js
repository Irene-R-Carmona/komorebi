document.addEventListener('alpine:init', () => {
  Alpine.data('receptionApp', () => ({
    checkinOpen: false,
    selectedResId: null,
    loading: false,

    init() {
      document.addEventListener('reception:refresh', () => this.refresh());
    },

    refresh() {
      window.location.reload();
    },

    openCheckin(reservationId) {
      this.selectedResId = reservationId;
      this.checkinOpen = true;

      this.$nextTick(() => {
        const select = document.querySelector('select[name="tracker_id"]');
        if (select) select.focus();
      });
    },

    closeCheckin() {
      this.checkinOpen = false;
      this.selectedResId = null;
    },

    async submitCheckin() {
      if (!this.selectedResId || this.loading) return;
      const select = document.querySelector('select[name="tracker_id"]');
      const trackerId = select ? parseInt(select.value, 10) : 0;
      if (!trackerId) return;

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.loading = true;
      try {
        const res = await fetch(`/api/v1/ops/reception/reservations/${this.selectedResId}/checkin`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ tracker_id: trackerId }),
        });
        const data = await res.json();
        if (data.ok) {
          this.closeCheckin();
          this.refresh();
        } else {
          console.error('Check-in error:', data.error || data.detail);
        }
      } catch (e) {
        console.error('Check-in fetch failed:', e);
      } finally {
        this.loading = false;
      }
    },

    async submitCheckout(reservationId) {
      if (!reservationId || this.loading) return;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.loading = true;
      try {
        const res = await fetch(`/api/v1/ops/reception/reservations/${reservationId}/checkout`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({}),
        });
        const data = await res.json();
        if (data.ok) {
          this.refresh();
        } else {
          console.error('Check-out error:', data.error || data.detail);
        }
      } catch (e) {
        console.error('Check-out fetch failed:', e);
      } finally {
        this.loading = false;
      }
    },
  }));
});

// Mercure SSE: recibe reservas nuevas/actualizadas y refresca la recepción en tiempo real
; (function initReceptionMercure() {
  const cfg = globalThis.__MERCURE__;
  if (!cfg || !cfg.cafeId || typeof EventSource === 'undefined') return;

  const topics = [
    'topic=' + encodeURIComponent('reception/' + cfg.cafeId + '/reservations'),
    'topic=' + encodeURIComponent('waitlist/' + cfg.cafeId),
  ].join('&');

  let es;
  function connect() {
    es = new EventSource(cfg.hub + '?' + topics);
    es.onmessage = function () { document.dispatchEvent(new CustomEvent('reception:refresh')); };
    es.onerror = function () { es.close(); setTimeout(connect, 5000); };
  }
  connect();
}());
