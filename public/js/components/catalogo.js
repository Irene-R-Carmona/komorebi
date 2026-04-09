// Componente centralizado: catalogoApp
// Exporta la fábrica en window.catalogoApp para que el registro central la use.
(function () {
  'use strict';

  globalThis.catalogoApp = function (favoritosIniciales = []) {
    return {
      filtroTipo: 'todos',
      busqueda: '',
      favoritos: new Set((favoritosIniciales || []).map(String)),
      filtrosGuardados: false,

      init() { this.restaurarFiltros(); },

      async restaurarFiltros() {
        try {
          if (!globalThis.CookieHelper || !CookieHelper.hasConsent('functional')) return;
          const resp = await fetch('/api/cookies/get-filters', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          if (!resp.ok) return;
          const data = await resp.json();
          if (data.success && data.filters) {
            if (data.filters.tipo) this.filtroTipo = data.filters.tipo;
            if (data.filters.busqueda) this.busqueda = data.filters.busqueda;
            this.filtrosGuardados = true;
          }
        } catch (e) { console.error('catalogo.restoreFilters error', e); }
      },

      async guardarFiltros() {
        try {
          if (!globalThis.CookieHelper || !CookieHelper.hasConsent('functional')) { console.info('Cookies funcionales deshabilitadas, no se guardarán filtros'); return; }
          const filtros = { tipo: this.filtroTipo, busqueda: this.busqueda };
          const resp = await fetch('/api/cookies/save-filters', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(filtros) });
          const data = await resp.json(); if (data.success) this.filtrosGuardados = true;
        } catch (e) { console.error('catalogo.guardarFiltros error', e); }
      },

      async limpiarFiltrosGuardados() {
        try {
          const resp = await fetch('/api/cookies/clear-filters', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await resp.json(); if (data.success) { this.filtrosGuardados = false; this.limpiarFiltros(); }
        } catch (e) { console.error('catalogo.limpiarFiltrosGuardados error', e); }
      },

      filtrar(cafe) {
        if (this.filtroTipo !== 'todos' && cafe.tipo !== this.filtroTipo) return false;
        if (this.busqueda) {
          const term = this.busqueda.toLowerCase();
          const nombre = (cafe.nombre || '').toLowerCase();
          const ubicacion = (cafe.ubicacion || '').toLowerCase();
          if (!nombre.includes(term) && !ubicacion.includes(term)) return false;
        }
        return true;
      },

      setFiltro(tipo) { this.filtroTipo = tipo; this.guardarFiltros(); },
      limpiarFiltros() { this.filtroTipo = 'todos'; this.busqueda = ''; },

      _busquedaTimeout: null,
      watchBusqueda() { clearTimeout(this._busquedaTimeout); this._busquedaTimeout = setTimeout(() => { this.guardarFiltros(); }, 1000); },

      async toggleFavorito(id) {
        const cafeId = String(id);
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta && tokenMeta.getAttribute('content');
        try {
          const response = await fetch('/api/favorites/toggle', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ cafe_id: cafeId }) });
          if (response.status === 401) { globalThis.location.href = '/login'; return; }
          const data = await response.json(); if (data.status === 'added') this.favoritos.add(cafeId); else this.favoritos.delete(cafeId);
        } catch (e) { console.error('catalogo.toggleFavorito error', e); }
      },

      esFavorito(id) { return this.favoritos.has(String(id)); }
    };
  };

})();
