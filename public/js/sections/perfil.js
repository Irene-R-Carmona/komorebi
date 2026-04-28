document.addEventListener('alpine:init', () => {
  Alpine.data('profileApp', (config = {}) => ({
    loading: false,
    profile: Object.keys(config.profile ?? {}).length > 0
      ? config.profile
      : { name: '', email: '', avatar_url: '', created_at: '' },
    stats: Object.keys(config.stats ?? {}).length > 0
      ? config.stats
      : { reservations_count: 0, level: { nivel: 1, nombre: 'Aprendiz', progreso: 0, siguiente: 0 } },
    reservations: [],
    reviews: [],

    async init() {
      this.loading = true;
      try {
        const [rRes, revRes] = await Promise.all([
          fetch('/api/v1/user/reservations'),
          fetch('/api/v1/user/reviews'),
        ]);
        if (rRes.ok)   { const j = await rRes.json();   this.reservations = j.data?.items ?? []; }
        if (revRes.ok) { const j = await revRes.json(); this.reviews      = j.data?.items ?? []; }
      } catch (e) {
        console.error('❌ profileApp init error:', e);
      } finally {
        this.loading = false;
      }
    },

    get level() {
      return this.stats.level ?? { nivel: 1, nombre: 'Aprendiz', progreso: 0, siguiente: 0 };
    },

    get reservationsCount() {
      return this.stats.reservations_count ?? 0;
    },

    get memberYear() {
      return this.profile.created_at
        ? new Date(this.profile.created_at).getFullYear()
        : '';
    },

    get avatarSrc() {
      if (this.profile.avatar_url) return this.profile.avatar_url;
      const email = this.profile.email ?? '';
      const seed  = email.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
      const gender = seed % 2 === 0 ? 'men' : 'women';
      const id     = Math.abs(seed) % 100;
      return `https://randomuser.me/api/portraits/${gender}/${id}.jpg`;
    },

    get nextReservation() {
      return this.reservations.find(r =>
        ['pending', 'confirmed', 'active'].includes(r.status)
      ) ?? null;
    },

    get nextDateHuman() {
      const r = this.nextReservation;
      if (!r?.reservation_date) return '';
      return new Date(r.reservation_date + 'T' + (r.reservation_time ?? '00:00'))
        .toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },

    get nextTimeHuman() {
      const r = this.nextReservation;
      if (!r?.reservation_time) return '';
      return r.reservation_time.substring(0, 5);
    },

    get nextStatusLabel() {
      const map = { pending: 'Pendiente', confirmed: 'Confirmada', active: 'Activa' };
      return map[this.nextReservation?.status ?? ''] ?? '';
    },

    get nextStatusClass() {
      const map = {
        pending:   'status-pill--pending',
        confirmed: 'status-pill--confirmed',
        active:    'status-pill--active',
      };
      return map[this.nextReservation?.status ?? ''] ?? '';
    },

    get nextReservationIsCancelable() {
      const r = this.nextReservation;
      if (!r) return false;
      if (!['pending', 'confirmed'].includes(r.status)) return false;
      return new Date(r.reservation_date + 'T' + (r.reservation_time ?? '00:00')) > new Date();
    },

    reviewStatusLabel(status) {
      const map = { pending: 'Pendiente', approved: 'Aprobada', rejected: 'Rechazada' };
      return map[status] ?? status;
    },

    reviewStatusClass(status) {
      const map = { pending: 'status-pending', approved: 'status-approved', rejected: 'status-rejected' };
      return map[status] ?? '';
    },

    reviewDateHuman(dateStr) {
      if (!dateStr) return '';
      return new Date(dateStr).toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' });
    },

    editReview(id) {
      globalThis.location.href = `/reviews/${id}/edit`;
    },
  }));
});
