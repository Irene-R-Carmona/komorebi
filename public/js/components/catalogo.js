// Componente centralizado: catalogoApp
// Exporta la fábrica en window.catalogoApp para que el registro central la use.
(function () {
  'use strict';

  globalThis.catalogoApp = function (favoritosIniciales = []) {
    return {
      filtroTipo: 'todos',
      busqueda: '',
      cafes: [],
      favoritos: new Set((favoritosIniciales || []).map(String)),
      filtrosGuardados: false,

      get hayResultados() {
        if (this.cafes.length === 0) return true;
        return this.cafesFiltrados.length > 0;
      },

      get cafesFiltrados() {
        return this.cafes.filter(c => this.filtrar(c));
      },

      async init() {
        try {
          const res = await fetch('/api/v1/cafes');
          if (res.ok) {
            const j = await res.json();
            this.cafes = j.data?.items ?? [];
          } else {
            console.error('❌ Error cargando cafés:', res.status);
          }
        } catch (e) {
          console.error('❌ catalogoApp init error:', e);
        }
        await this.restaurarFiltros();
      },

      async restaurarFiltros() {
        try {
          if (!globalThis.CookieHelper || !CookieHelper.hasConsent('functional')) return;
          const resp = await fetch('/api/v1/cookies/filters', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          if (!resp.ok) return;
          const data = await resp.json();
          if (data.success && data.filters) {
            if (data.filters.tipo) {
              const tiposValidos = ['todos', 'lounge', 'playroom', 'farm', 'zen'];
              this.filtroTipo = tiposValidos.includes(data.filters.tipo) ? data.filters.tipo : 'todos';
            }
            if (data.filters.busqueda) this.busqueda = data.filters.busqueda;
            this.filtrosGuardados = true;
          }
        } catch (e) { console.error('catalogo.restoreFilters error', e); }
      },

      async guardarFiltros() {
        try {
          if (!globalThis.CookieHelper || !CookieHelper.hasConsent('functional')) { console.info('Cookies funcionales deshabilitadas, no se guardarán filtros'); return; }
          const filtros = { tipo: this.filtroTipo, busqueda: this.busqueda };
          const resp = await fetch('/api/v1/cookies/filters', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(filtros) });
          const data = await resp.json(); if (data.success) this.filtrosGuardados = true;
        } catch (e) { console.error('catalogo.guardarFiltros error', e); }
      },

      async limpiarFiltrosGuardados() {
        try {
          const resp = await fetch('/api/v1/cookies/filters', { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          if (resp.ok) { this.filtrosGuardados = false; this.limpiarFiltros(); }
        } catch (e) { console.error('catalogo.limpiarFiltrosGuardados error', e); }
      },

      filtrar(cafe) {
        if (this.filtroTipo !== 'todos' && cafe.category !== this.filtroTipo) return false;
        if (this.busqueda) {
          const term = this.busqueda.toLowerCase();
          const nombre = (cafe.name || '').toLowerCase();
          const ubicacion = (cafe.location || '').toLowerCase();
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
        const method = this.esFavorito(id) ? 'DELETE' : 'PUT';
        try {
          const response = await fetch(`/api/v1/favorites/${cafeId}`, { method, headers: { 'X-CSRF-TOKEN': token } });
          if (response.status === 401) { globalThis.location.href = '/login'; return; }
          if (response.ok) {
            if (method === 'DELETE') this.favoritos.delete(cafeId);
            else this.favoritos.add(cafeId);
          }
        } catch (e) { console.error('catalogo.toggleFavorito error', e); }
      },

      esFavorito(id) { return this.favoritos.has(String(id)); }
    };
  };

})();
