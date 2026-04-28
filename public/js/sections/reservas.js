const reservaFormFactory = (config = {}) => {
    return {
      cafes:           Array.isArray(config.cafes)   ? config.cafes   : [],
      passes:          Array.isArray(config.passes)  ? config.passes  : [],
      historial:       [],
      historialLoading: false,

      selectedCafeId:    '',
      selectedPassId:    '',
      preselectedPassId: '',
      personas:          2,

      get cafeActivo() {
        if (!this.selectedCafeId) return null;
        return this.cafes.find(c => String(c.id) === String(this.selectedCafeId)) || null;
      },

      get pasesDisponibles() {
        const cafe = this.cafeActivo;
        if (!cafe) return [];

        const cafeType   = String(cafe.category);
        const cafeAnimal = String(cafe.animal_type || '');

        return (this.passes || []).filter((p) => {
          const targets = this.parseJsonArray(p.target_cafe_types);
          if (Array.isArray(targets) && targets.length > 0) {
            if (!targets.map(String).includes(cafeType)) return false;
          }

          const animalTargets = this.parseJsonArray(p.target_animal_types);
          if (Array.isArray(animalTargets) && animalTargets.length > 0) {
            if (!animalTargets.map(String).includes(cafeAnimal)) return false;
          }

          const min = Number.parseInt(p.min_pax ?? 1, 10);
          const max = (p.max_pax === null || p.max_pax === undefined || p.max_pax === '')
            ? null : Number.parseInt(p.max_pax, 10);
          if (this.personas < min) return false;
          if (max !== null && this.personas > max) return false;

          return Number.parseInt(p.duration_minutes ?? 0, 10) > 0;
        });
      },

      isFixedPass(p) {
        const min = Number(p.min_pax ?? 1);
        const max = (p.max_pax === null || p.max_pax === undefined || p.max_pax === '')
          ? null : Number(p.max_pax);
        return (max !== null && min === max && max > 1);
      },

      priceLabel(p) {
        const price = Number(p.price) || 0;
        return this.isFixedPass(p)
          ? `¥${price.toLocaleString()} (fijo)`
          : `¥${price.toLocaleString()} / persona`;
      },

      passBadges(p) {
        const attrs = this.parseJsonObject(p.attributes) || {};
        const out = [];
        if (attrs.includes_drink)  out.push({ icon: '🥤', label: 'Bebida' });
        if (attrs.includes_dessert) out.push({ icon: '🍡', label: 'Postre' });
        if (attrs.includes_feed)   out.push({ icon: '🥕', label: 'Feed' });
        if (attrs.private_room)    out.push({ icon: '🪟', label: 'Privado' });
        if (attrs.guided)          out.push({ icon: '🧑‍🏫', label: 'Guiado' });
        if (attrs.quiet)           out.push({ icon: '🤫', label: 'Quiet' });
        if (attrs.high_energy)     out.push({ icon: '⚡', label: 'Energía' });
        if (attrs.allowed_start && attrs.allowed_end) {
          out.push({ icon: '🌙', label: `${attrs.allowed_start}-${attrs.allowed_end}` });
        }
        return out;
      },

      passAnimalLabel(p) {
        const animals = this.parseJsonArray(p.target_animal_types);
        if (!Array.isArray(animals) || animals.length === 0) return '';
        return animals.length === 1 ? String(animals[0]) : 'Multi-animal';
      },

      incrementar() { this.personas = Math.min(6, this.personas + 1); },
      decrementar() { this.personas = Math.max(1, this.personas - 1); },

      onSelectedCafeChange() {
        this.selectedPassId = '';
        this.applyPreselectedPassIfPossible();
      },

      onPersonasChange() {
        if (this.selectedPassId) {
          const stillValid = this.pasesDisponibles.some(p => String(p.id) === String(this.selectedPassId));
          if (!stillValid) this.selectedPassId = '';
        }
        this.applyPreselectedPassIfPossible();
      },

      applyPreselectedPassIfPossible() {
        if (!this.preselectedPassId) return;
        const ok = this.pasesDisponibles.some(p => String(p.id) === String(this.preselectedPassId));
        if (ok) {
          this.selectedPassId    = String(this.preselectedPassId);
          this.preselectedPassId = '';
        }
      },

      parseJsonArray(val) {
        if (!val) return null;
        if (Array.isArray(val)) return val;
        if (typeof val === 'string') {
          try { return JSON.parse(val); } catch { return null; }
        }
        return null;
      },

      parseJsonObject(val) {
        if (!val) return null;
        if (typeof val === 'object') return val;
        if (typeof val === 'string') {
          try { return JSON.parse(val); } catch { return null; }
        }
        return null;
      },

      async init() {
        const urlParams = new URLSearchParams(globalThis.location.search);

        const cafeParam = urlParams.get('cafe');
        if (cafeParam && this.cafes.some(c => String(c.id) === String(cafeParam))) {
          this.selectedCafeId = String(cafeParam);
        }

        const passParam = urlParams.get('pass');
        if (passParam && this.passes.some(p => String(p.id) === String(passParam))) {
          this.preselectedPassId = String(passParam);
        }

        this.$watch('selectedCafeId', this.onSelectedCafeChange.bind(this));
        this.$watch('personas',       this.onPersonasChange.bind(this));

        this.applyPreselectedPassIfPossible();
        await this.loadHistorial();
      },

      async loadHistorial() {
        this.historialLoading = true;
        try {
          const res = await fetch('/api/v1/user/reservations');
          if (res.ok) {
            const json = await res.json();
            this.historial = json.data?.items ?? [];
          }
        } catch { /* historial not critical */ } finally {
          this.historialLoading = false;
        }
      },

      isPast(res) {
        const ts = Date.parse(res.reservation_date + 'T' + (res.reservation_time || '00:00:00'));
        return !Number.isNaN(ts) && ts < Date.now();
      },

      isCancelable(res) {
        const status = res.status || '';
        return ['pending', 'confirmed'].includes(status) && !this.isPast(res);
      },

      statusLabel(status) {
        const labels = {
          pending:   'Pendiente',
          confirmed: 'Confirmada',
          cancelled: 'Cancelada',
          completed: 'Completada',
          active:    'Activa',
          no_show:   'No presentado',
        };
        return labels[status] ?? (status || '').toUpperCase();
      },

      formatFecha(dateStr) {
        if (!dateStr) return '—';
        const [y, m, d] = dateStr.split('-');
        return (d ?? '') + '/' + (m ?? '') + '/' + (y ?? '');
      },

      async cancelReservation(reservationId) {
        if (!reservationId) return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        try {
          const resp = await fetch('/api/v1/reservations/' + reservationId + '/cancel', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    '{}',
          });
          if (resp.ok) {
            globalThis.location.reload();
          } else {
            const json = await resp.json();
            alert(json.detail ?? 'No se pudo cancelar la reserva');
          }
        } catch {
          alert('Error de conexión. Por favor, inténtalo de nuevo.');
        }
      },
    };
};

globalThis.reservaForm = reservaFormFactory;
