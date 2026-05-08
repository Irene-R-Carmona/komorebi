document.addEventListener('alpine:init', () => {
  Alpine.data('profileApp', () => ({
    loading: true,
    profile: { name: '', email: '', avatar_url: '', created_at: '' },
    stats: { reservations_count: 0, level: { nivel: 1, nombre: 'Aprendiz', progreso: 0, siguiente: 0 } },
    reservations: [],
    reviews: [],

    async init() {
      try {
        const [pRes, sRes, rRes, revRes] = await Promise.all([
          fetch('/api/v1/user/profile'),
          fetch('/api/v1/user/stats'),
          fetch('/api/v1/user/reservations'),
          fetch('/api/v1/user/reviews'),
        ]);
        if (pRes.ok) { const j = await pRes.json(); this.profile = j.data ?? this.profile; }
        if (sRes.ok) { const j = await sRes.json(); this.stats = j.data ?? this.stats; }
        if (rRes.ok) { const j = await rRes.json(); this.reservations = j.data?.items ?? []; }
        if (revRes.ok) { const j = await revRes.json(); this.reviews = j.data?.items ?? []; }
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
      const name = this.profile.name ?? 'U';
      const initials = name.split(' ').map(w => w[0] ?? '').slice(0, 2).join('').toUpperCase() || 'U';
      const colors = ['#5C3D2E', '#4a2f23', '#C9A959', '#5E6F64', '#5A9FD4'];
      const idx = name.split('').reduce((a, c) => a + c.charCodeAt(0), 0) % colors.length;
      const bg = colors[idx];
      const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="40" fill="${bg}"/><text x="40" y="40" text-anchor="middle" dominant-baseline="central" fill="white" font-size="28" font-family="system-ui,sans-serif" font-weight="600">${initials}</text></svg>`;
      return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
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
        pending: 'status-pill--pending',
        confirmed: 'status-pill--confirmed',
        active: 'status-pill--active',
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
