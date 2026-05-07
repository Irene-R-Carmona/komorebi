document.addEventListener('alpine:init', () => {
  Alpine.data('receptionApp', () => ({
    // ── check-in ──────────────────────────────────────────────
    checkinOpen: false,
    selectedResId: null,
    loading: false,

    // ── POS (añadir pedido) ───────────────────────────────────
    posOpen: false,
    posResId: null,
    posLines: [],     // [{ productId: null, qty: 1 }]
    posProducts: [],  // [{ id, name, price }]
    posError: '',

    // ── cobro ─────────────────────────────────────────────────
    cobroOpen: false,
    cobroResId: null,
    cobroMethod: 'cash',
    cobroNotes: '',
    cobroError: '',

    init() {
      document.addEventListener('reception:refresh', () => this.refresh());
    },

    refresh() {
      window.location.reload();
    },

    // ── check-in ──────────────────────────────────────────────

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

    // ── POS (añadir pedido) ───────────────────────────────────

    openPos(reservationId) {
      this.posResId = reservationId;
      this.posLines = [{ productId: null, qty: 1 }];
      this.posProducts = JSON.parse(
        document.querySelector('[data-orderable-items]')?.dataset?.orderableItems || '[]'
      );
      this.posError = '';
      this.posOpen = true;
    },

    closePos() {
      this.posOpen = false;
      this.posResId = null;
    },

    addPosLine() {
      this.posLines.push({ productId: null, qty: 1 });
    },

    removePosLine(idx) {
      this.posLines.splice(idx, 1);
    },

    posLineSubtotal(line) {
      const product = this.posProducts.find(p => p.id === line.productId);
      return product ? parseFloat(product.price) * (line.qty || 0) : 0;
    },

    posTotal() {
      return this.posLines.reduce((sum, line) => sum + this.posLineSubtotal(line), 0);
    },

    posValid() {
      return this.posLines.length > 0
        && this.posLines.every(l => l.productId && l.qty >= 1);
    },

    async submitPos() {
      if (!this.posResId || this.loading || !this.posValid()) return;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.posError = '';
      this.loading = true;
      try {
        for (const line of this.posLines) {
          const res = await fetch(`/api/v1/ops/reception/reservations/${this.posResId}/items`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ product_id: line.productId, quantity: line.qty }),
          });
          const data = await res.json();
          if (!data.ok) {
            this.posError = data.detail || 'No se pudo añadir el pedido.';
            return;
          }
        }
        this.closePos();
        this.refresh();
      } catch (e) {
        this.posError = 'Error de conexión.';
        console.error('POS fetch failed:', e);
      } finally {
        this.loading = false;
      }
    },

    // ── cobro ─────────────────────────────────────────────────

    openCobro(reservationId) {
      this.cobroResId = reservationId;
      this.cobroMethod = 'cash';
      this.cobroNotes = '';
      this.cobroError = '';
      this.cobroOpen = true;
    },

    closeCobro() {
      this.cobroOpen = false;
      this.cobroResId = null;
    },

    async submitCobro() {
      if (!this.cobroResId || this.loading) return;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.cobroError = '';
      this.loading = true;
      try {
        const res = await fetch(`/api/v1/ops/reception/reservations/${this.cobroResId}/payments`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ payment_method: this.cobroMethod, notes: this.cobroNotes }),
        });
        const data = await res.json();
        if (data.ok) {
          this.closeCobro();
          this.refresh();
        } else {
          this.cobroError = data.detail || 'No se pudo procesar el cobro.';
        }
      } catch (e) {
        this.cobroError = 'Error de conexión.';
        console.error('Cobro fetch failed:', e);
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
