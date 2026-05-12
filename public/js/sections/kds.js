document.addEventListener('alpine:init', () => {
  Alpine.data('kdsApp', () => ({
    sopOpen: false,
    sopData: {
      title: '', station: '', ingred: [], steps: [], check: '', allergens: []
    },

    init() {
      document.addEventListener('kds:refresh', () => this.refresh());
      document.addEventListener('mark-ready', (e) => this.completeOrder(e.detail.id));
      document.addEventListener('start-prep', (e) => this.startOrder(e.detail.id));
      document.addEventListener('show-sop', (e) => this.openSop(e.detail));
    },

    refresh() {
      window.location.reload();
    },

    async startOrder(orderId) {
      if (!orderId) return;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      try {
        const res = await fetch(`/api/v1/ops/kitchen/orders/${orderId}/start`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({}),
        });
        const data = await res.json();
        if (data.ok) {
          document.dispatchEvent(new CustomEvent('kds:refresh'));
        } else {
          console.error('Start order error:', data.error || data.detail);
        }
      } catch (e) {
        console.error('Start order fetch failed:', e);
      }
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
        ingred: this.parseList(data.ingred).map(name => ({ name, checked: false })),
        steps: this.parseSteps(data.steps).map((s, i) => ({ text: s, active: i === 0 })),
        check: data.check,
        allergens: Array.isArray(data.allergens) ? data.allergens : [],
      };

      this.sopOpen = true;
    },

    toggleMise(idx) {
      if (this.sopData.ingred[idx] !== undefined) {
        this.sopData.ingred[idx].checked = !this.sopData.ingred[idx].checked;
      }
    },

    activateStep(idx) {
      this.sopData.steps = this.sopData.steps.map((s, i) => ({ ...s, active: i === idx }));
    },

    closeSop() {
      this.sopOpen = false;
    },

    getContrastColor(hex) {
      hex = (hex || '').replace('#', '');
      if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
      const r = parseInt(hex.substring(0, 2), 16) / 255;
      const g = parseInt(hex.substring(2, 4), 16) / 255;
      const b = parseInt(hex.substring(4, 6), 16) / 255;
      const lum = 0.2126 * (r <= 0.04045 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4))
        + 0.7152 * (g <= 0.04045 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4))
        + 0.0722 * (b <= 0.04045 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4));
      return lum > 0.179 ? '#000000' : '#ffffff';
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
