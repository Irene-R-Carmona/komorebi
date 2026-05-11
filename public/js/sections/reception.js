document.addEventListener('alpine:init', () => {
  Alpine.data('receptionApp', () => ({
    // ── check-in ──────────────────────────────────────────────
    checkinOpen: false,
    selectedResId: null,
    loading: false,

    // ── pre-order (check-in) ──────────────────────────────────
    checkinPreOrder: [],
    activatingPreOrder: false,
    preOrderResult: null,

    // ── POS (añadir pedido) ───────────────────────────────────
    posOpen: false,
    posResId: null,
    posLines: [],
    posProducts: [],
    posError: '',
    posActiveCat: 'all',
    posExcludedAllergens: [],

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

    // ── UTILIDADES ────────────────────────────────────────────

    /**
     * Formatea céntimos como Euros: 1250 → '12,50 €'
     */
    formatEuro(cents) {
      const euros = (Number(cents) || 0) / 100;
      return euros.toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }) + ' €';
    },

    // ── check-in ──────────────────────────────────────────────

    openCheckin(reservationId) {
      this.selectedResId = reservationId;
      this.checkinPreOrder = [];
      this.preOrderResult = null;
      this.checkinOpen = true;
      this.$nextTick(() => {
        const select = document.querySelector('select[name="tracker_id"]');
        if (select) select.focus();
      });
      fetch(`/api/v1/ops/reception/reservations`)
        .then(r => r.json())
        .then(json => {
          const list = json.data?.reservations ?? json.data?.items ?? json.data ?? [];
          const res = list.find(r => r.id === reservationId);
          this.checkinPreOrder = res?.pre_order_items ?? [];
        })
        .catch(() => { this.checkinPreOrder = []; });
    },

    closeCheckin() {
      this.checkinOpen = false;
      this.selectedResId = null;
      this.checkinPreOrder = [];
      this.preOrderResult = null;
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

    // ── pre-order activation ──────────────────────────────────

    async activatePreOrder() {
      if (!this.selectedResId || this.activatingPreOrder) return;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.activatingPreOrder = true;
      this.preOrderResult = null;
      try {
        const res = await fetch(`/api/v1/ops/reception/reservations/${this.selectedResId}/activate-preorder`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: '{}',
        });
        const data = await res.json();
        if (data.ok) {
          this.preOrderResult = {
            ok: true,
            activated: data.data?.activated ?? 0,
            unavailable: data.data?.unavailable ?? []
          };
          this.checkinPreOrder = [];
        } else {
          this.preOrderResult = {
            ok: false,
            message: data.detail || 'No se pudo activar la pre-comanda.'
          };
        }
      } catch (e) {
        this.preOrderResult = { ok: false, message: 'Error de conexión.' };
        console.error('activatePreOrder failed:', e);
      } finally {
        this.activatingPreOrder = false;
      }
    },

    // ── POS (añadir pedido) ───────────────────────────────────

    openPos(reservationId) {
      this.posResId = reservationId;
      this.posLines = [];
      this.posProducts = JSON.parse(
        document.querySelector('[data-orderable-items]')?.dataset?.orderableItems || '[]'
      );
      this.posActiveCat = 'all';
      this.posExcludedAllergens = [];
      this.posError = '';
      this.posOpen = true;
    },

    closePos() {
      this.posOpen = false;
      this.posResId = null;
    },

    posCatTabs() {
      const cats = [...new Set(this.posProducts.map(p => p.category_name).filter(Boolean))];
      return cats;
    },

    posAllAllergens() {
      const seen = new Map();
      for (const p of this.posProducts) {
        for (const a of (p.allergen_data || '').split(';;').filter(Boolean)) {
          const parts = a.split('|');
          const code = parts[0];
          if (code && !seen.has(code)) {
            seen.set(code, { code, name: parts[1] || code });
          }
        }
      }
      return [...seen.values()];
    },

    posFilteredProducts() {
      return this.posProducts.filter(p => {
        if (this.posActiveCat !== 'all' && p.category_name !== this.posActiveCat) return false;
        if (this.posExcludedAllergens.length === 0) return true;
        const itemAllergens = (p.allergen_data || '').split(';;').map(a => a.split('|')[0]).filter(Boolean);
        return !this.posExcludedAllergens.some(a => itemAllergens.includes(a));
      });
    },

    togglePosAllergen(code) {
      const idx = this.posExcludedAllergens.indexOf(code);
      if (idx >= 0) this.posExcludedAllergens.splice(idx, 1);
      else this.posExcludedAllergens.push(code);
    },

    posLineForProduct(productId) {
      return this.posLines.find(l => l.productId === productId) || null;
    },

    posToggleProduct(productId) {
      const id = parseInt(productId, 10);
      const idx = this.posLines.findIndex(l => l.productId === id);
      if (idx >= 0) {
        this.posLines.splice(idx, 1);
      } else {
        this.posLines.push({ productId: id, qty: 1 });
      }
    },

    posChangeQty(productId, delta) {
      const id = parseInt(productId, 10);
      const line = this.posLines.find(l => l.productId === id);
      if (!line) return;
      line.qty = Math.max(1, Math.min(20, (line.qty || 1) + delta));
    },

    posLineSubtotal(line) {
      const product = this.posProducts.find(p => parseInt(p.id, 10) === line.productId);
      if (!product) return 0;
      const priceCents = parseInt(product.price, 10) || 0;
      return priceCents * (line.qty || 0);
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

// Mercure SSE
(function initReceptionMercure() {
  const cfg = globalThis.__MERCURE__;
  if (!cfg || !cfg.cafeId || typeof EventSource === 'undefined') return;

  const topics = [
    'topic=' + encodeURIComponent('reception/' + cfg.cafeId + '/reservations'),
    'topic=' + encodeURIComponent('waitlist/' + cfg.cafeId),
  ].join('&');

  let es;
  function connect() {
    es = new EventSource(cfg.hub + '?' + topics);
    es.onmessage = function () {
      document.dispatchEvent(new CustomEvent('reception:refresh'));
    };
    es.onerror = function () {
      es.close();
      setTimeout(connect, 5000);
    };
  }
  connect();
}());

(function initKitchenReadyMercure() {
  const cfg = globalThis.__MERCURE__;
  if (!cfg || !cfg.cafeId || typeof EventSource === 'undefined') return;

  const url = cfg.hub + '?topic=' + encodeURIComponent('reception/' + cfg.cafeId + '/kitchen-ready');
  const notif = typeof NotificationManager !== 'undefined' ? new NotificationManager() : null;

  let es;
  function connect() {
    es = new EventSource(url);
    es.onmessage = function (e) {
      if (notif) {
        notif.show('\u00a1Un pedido de cocina est\u00e1 listo para servir!', 'success', 8000);
      }
    };
    es.onerror = function () {
      es.close();
      setTimeout(connect, 5000);
    };
  }
  connect();
}());
