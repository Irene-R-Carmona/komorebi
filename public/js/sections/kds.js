document.addEventListener('alpine:init', () => {
  Alpine.data('kdsApp', () => ({
    sopOpen: false,
    sopData: {
      title: '', station: '', ingred: [], steps: [], check: '', allergens: []
    },

    init() {
      document.addEventListener('kds:refresh', () => this.refresh());
      document.addEventListener('mark-ready', (e) => this.completeOrder(e.detail.id));
      document.addEventListener('show-sop', (e) => this.openSop(e.detail));
    },

    refresh() {
      window.location.reload();
    },

    async completeOrder(orderId) {
      if (!orderId) return;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      try {
        const res = await fetch(`/api/v1/ops/kitchen/orders/${orderId}/complete`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({}),
        });
        const data = await res.json();
        if (data.ok) {
          document.dispatchEvent(new CustomEvent('kds:refresh'));
        } else {
          console.error('Complete order error:', data.error || data.detail);
        }
      } catch (e) {
        console.error('Complete order fetch failed:', e);
      }
    },

    // Helper para normalizar listas (ingredientes, alérgenos, etc.)
    parseList(list) {
      if (Array.isArray(list)) {
        return list;
      }

      if (list == null || list === '') {
        return [];
      }

      try {
        const parsed = typeof list === 'string' ? JSON.parse(list) : list;
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        console.warn('Failed to parse list:', e);
        return [];
      }
    },

    // Helper para parsear pasos (si es string con \n)
    parseSteps(text) {
      if (!text) return [];
      const normalized = text.replaceAll('\r\n', '\n');
      return normalized.split('\n').filter(s => s.trim().length > 0);
    },

    openSop(data) {
      console.log("Opening SOP", data);

      this.sopData = {
        title: data.title,
        station: data.station || 'General',
        ingred: this.parseList(data.ingred),
        steps: this.parseSteps(data.steps),
        check: data.check,
        allergens: Array.isArray(data.allergens) ? data.allergens : [],
      };

      this.sopOpen = true;
    },

    closeSop() {
      this.sopOpen = false;
    },
  }))
})

  // Mercure SSE: recibe nuevas órdenes y refresca el KDS en tiempo real
  ; (function initKdsMercure() {
    const cfg = globalThis.__MERCURE__;
    if (!cfg || !cfg.cafeId || typeof EventSource === 'undefined') return;

    const url = cfg.hub + '?topic=' + encodeURIComponent('kds/' + cfg.cafeId + '/orders');

    let es;
    function connect() {
      es = new EventSource(url);
      es.onmessage = function () { document.dispatchEvent(new CustomEvent('kds:refresh')); };
      es.onerror = function () { es.close(); setTimeout(connect, 5000); };
    }
    connect();
  }());
