document.addEventListener('alpine:init', () => {
  Alpine.data('reservaForm', (cartTotal = 0) => {
    return {
      // ─── Datos básicos ──────────────────────────────────────────
      cafes: [],
      passes: [],
      historial: [],
      historialLoading: false,
      cartTotal: Number(cartTotal) || 0,

      selectedCafeId: '',
      selectedPassId: '',
      preselectedPassId: '',

      fecha: '',
      hora: '',
      personas: 2,
      comentarios: '',

      submitting: false,
      submitError: null,

      // Accordion
      openStep: 1,

      // ─── APIs externas ──────────────────────────────────────────
      weatherData: null,
      holidayData: null,
      loadingWeather: false,
      loadingHoliday: false,
      dateForecast: null,
      forecastUnavailable: false,
      allSlots: [],
      loadingSlots: false,
      slotsError: null,
      sidebarWeather: null,
      sidebarWeatherLoading: false,
      minDate: new Date().toISOString().split('T')[0],

      // ─── Festivos ─────
      holidaysCache: {}, // { '2026-05-12': { is_holiday: bool, holiday_name: string } }

      // ─── Feature D — Comanda opcional ───────────────────────────
      productos: {},
      loadingProductos: false,
      carrito: null,
      loadingCarrito: false,
      comandaLocal: [],
      comandaCatActiva: 0,

      // ─── Alérgenos ──────────────────────────────────────────────
      alergenos: [],
      loadingAlergenos: false,
      alergenosExcluidos: [],

      // ─── Waitlist — NUEVO ───────────────────────────────────────
      waitlistModalOpen: false,
      waitlistLoading: false,
      waitlistSuccess: false,
      waitlistError: false,
      waitlistMessage: '',
      waitlistErrorMessage: '',
      waitlistTargetTime: null,
      waitlistFormError: '',
      waitlistGuests: 2,
      waitlistEmail: '',
      waitlistPhone: '',
      waitlistNotes: '',
      waitlistToken: null,
      waitlistPosition: null,

      // ─── Getters ────────────────────────────────────────────────

      get cafeActivo() {
        if (!this.selectedCafeId) return null;
        return this.cafes.find(c => String(c.id) === String(this.selectedCafeId)) || null;
      },

      get pasesDisponibles() {
        const cafe = this.cafeActivo;
        if (!cafe) return [];

        const cafeType = String(cafe.category);
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
          const max = (p.max_pax === null || p.max_pax === undefined || p.max_pax === '') ? null : Number.parseInt(p.max_pax, 10);
          if (this.personas < min) return false;
          if (max !== null && this.personas > max) return false;

          const dur = Number.parseInt(p.duration_minutes ?? 0, 10);
          return dur > 0;
        });
      },

      get passActivo() {
        if (!this.selectedPassId) return null;
        return this.pasesDisponibles.find(p => String(p.id) === String(this.selectedPassId)) || null;
      },

      get slotsConOcupacion() {
        return this.allSlots.map(slot => {
          const totalCap = slot.total_capacity ?? 0;
          const capacityPct = totalCap > 0
            ? Math.round(((slot.occupied_guests ?? 0) / totalCap) * 100)
            : 0;
          let occupancyLevel = 'available';
          let occupancyLabel = 'Disponible';
          let occupancyColor = 'success';

          if (capacityPct >= 100 || !slot.available) {
            occupancyLevel = 'full';
            occupancyLabel = 'Completo';
            occupancyColor = 'danger';
          } else if (capacityPct >= 75) {
            occupancyLevel = 'limited';
            occupancyLabel = 'Quedan pocos';
            occupancyColor = 'warning';
          } else if (capacityPct >= 50) {
            occupancyLevel = 'moderate';
            occupancyLabel = 'Ocupación media';
            occupancyColor = 'info';
          }

          return {
            ...slot,
            occupancyLevel,
            occupancyLabel,
            occupancyColor
          };
        });
      },

      get slotsFiltradosPorHorario() {
        if (!this.cafeActivo) return this.allSlots;

        const openingTime = this.cafeActivo.opening_time || '00:00';
        const closingTime = this.cafeActivo.closing_time || '23:00';
        const passDuration = this.passActivo?.duration_minutes || 60;

        return this.allSlots.filter(slot => {
          const slotMinutes = this.timeToMinutes(slot.time);
          const openMinutes = this.timeToMinutes(openingTime);
          const closeMinutes = this.timeToMinutes(closingTime);

          return slotMinutes >= openMinutes && (slotMinutes + passDuration) <= closeMinutes;
        });
      },

      get productosParaCafe() {
        const cafeCategory = this.cafeActivo?.category ?? null;
        return Object.values(this.productos).flat().filter(p => {
          const targets = this.parseJsonArray(p.target_cafe_types);
          if (!Array.isArray(targets) || targets.length === 0) return true;
          return cafeCategory !== null && targets.includes(cafeCategory);
        });
      },

      get categoriasDisponibles() {
        const map = {};
        for (const p of this.productosParaCafe) {
          if (!map[p.category_id]) map[p.category_id] = { id: p.category_id, name: p.category_name };
        }
        return Object.values(map);
      },

      get quotasPorCategoria() {
        const inclusions = this.passActivo?.inclusions ?? [];
        if (!inclusions.length) return [];
        const pax = this.personas || 1;
        return inclusions.map(inc => {
          const maxUnits = (inc.quantity_per_pax ?? 0) * pax;
          const maxPrice = inc.max_unit_price != null ? Number(inc.max_unit_price) : null;
          let used = 0;
          for (const item of this.comandaLocal) {
            if (Number(item.category_id) !== Number(inc.category_id)) continue;
            const priceCents = item.price_cents ?? 0;
            if (maxPrice !== null && priceCents > maxPrice) continue;
            used += item.qty;
          }
          for (const item of Object.values(this.carrito?.items ?? {})) {
            if (Number(item.category_id) !== Number(inc.category_id)) continue;
            const priceCents = item.price_cents ?? 0;
            if (maxPrice !== null && priceCents > maxPrice) continue;
            used += item.qty ?? item.quantity ?? 0;
          }
          return {
            category_id: inc.category_id,
            category_name: inc.category_name,
            max_units: maxUnits,
            used,
            remaining: Math.max(0, maxUnits - used),
            max_unit_price: maxPrice,
          };
        });
      },

      get productosPorCategoria() {
        const base = this.productosParaCafe;
        let filtered = this.comandaCatActiva === 0
          ? base
          : base.filter(p => p.category_id === this.comandaCatActiva);
        if (this.alergenosExcluidos.length > 0) {
          filtered = filtered.filter(p => !this.productoContieneAlergenoExcluido(p));
        }
        return filtered;
      },

      get comandaBreakdown() {
        const inclusions = this.passActivo?.inclusions ?? [];
        const pax = this.personas || 1;
        const slotsRemaining = {};
        for (const inc of inclusions) {
          slotsRemaining[inc.category_id] = (inc.quantity_per_pax ?? 0) * pax;
        }
        const resultado = [];
        for (const item of this.comandaLocal) {
          const inc = inclusions.find(i => Number(i.category_id) === Number(item.category_id));
          let slotsUsed = 0;
          let totalCents = (item.price_cents ?? 0) * item.qty;

          if (inc) {
            const maxPrice = inc.max_unit_price != null ? Number(inc.max_unit_price) : null;
            const priceCents = item.price_cents ?? 0;
            const disponibles = slotsRemaining[inc.category_id] ?? 0;
            slotsUsed = Math.min(item.qty, disponibles);
            slotsRemaining[inc.category_id] = Math.max(0, disponibles - slotsUsed);
            const qtyCobrada = item.qty - slotsUsed;
            if (maxPrice === null || priceCents <= maxPrice) {
              totalCents = priceCents * qtyCobrada;
            } else {
              totalCents = (priceCents - maxPrice) * slotsUsed + priceCents * qtyCobrada;
            }
          }

          resultado.push({
            ...item,
            id: item.product_id,
            slots_incluidos: slotsUsed,
            qty_cobrada: item.qty - slotsUsed,
            total_cents: totalCents,
            incluido: slotsUsed > 0,
          });
        }
        return resultado;
      },

      get comandaTotal() {
        return this.comandaBreakdown.reduce((sum, i) => sum + i.total_cents, 0);
      },

      get comandaHasItems() {
        return this.comandaBreakdown.length > 0;
      },

      get hasUnusedInclusions() {
        return this.quotasPorCategoria.some(q => q.remaining > 0);
      },

      get step() {
        if (!this.selectedCafeId || !this.selectedPassId) return 1;
        if (!this.fecha || !this.hora) return 2;
        return 3;
      },

      get progressPercent() {
        return ({ 1: 33, 2: 66, 3: 100 })[this.step] || 33;
      },

      get canSubmit() {
        return !!(
          this.selectedCafeId &&
          this.selectedPassId &&
          this.fecha &&
          this.hora &&
          this.allSlots.some(s => s.time === this.hora && s.available)
        );
      },

      // ─── Métodos de utilidad ────────────────────────────────────

      isFixedPass(p) {
        const min = Number(p.min_pax ?? 1);
        const max = (p.max_pax === null || p.max_pax === undefined || p.max_pax === '') ? null : Number(p.max_pax);
        return (max !== null && min === max && max > 1);
      },

      formatEuro(cents) {
        return (Number(cents) / 100).toFixed(2).replace('.', ',') + ' €';
      },

      toggleStep(n) {
        this.openStep = (this.openStep === n) ? 0 : n;
      },

      priceLabel(p) {
        const price = Number(p.price) || 0;
        return this.isFixedPass(p)
          ? this.formatEuro(price) + ' (precio fijo)'
          : this.formatEuro(price) + ' / persona';
      },

      passBadges(p) {
        const attrs = this.parseJsonObject(p.attributes) || {};
        const out = [];
        if (attrs.includes_drink) out.push({ icon: 'bi-cup-straw', label: 'Bebida' });
        if (attrs.includes_dessert) out.push({ icon: 'bi-cake2', label: 'Postre' });
        if (attrs.includes_feed) out.push({ icon: 'bi-basket2', label: 'Feed' });
        if (attrs.private_room) out.push({ icon: 'bi-door-closed', label: 'Privado' });
        if (attrs.guided) out.push({ icon: 'bi-person-video3', label: 'Guiado' });
        if (attrs.quiet) out.push({ icon: 'bi-volume-mute', label: 'Quiet' });
        if (attrs.high_energy) out.push({ icon: 'bi-lightning-charge', label: 'Energía' });
        if (attrs.allowed_start && attrs.allowed_end) out.push({
          icon: 'bi-moon-stars',
          label: `${attrs.allowed_start}-${attrs.allowed_end}`
        });
        return out;
      },

      passAnimalLabel(p) {
        const animals = this.parseJsonArray(p.target_animal_types);
        if (!Array.isArray(animals) || animals.length === 0) return '';
        return animals.length === 1 ? String(animals[0]) : 'Multi-animal';
      },

      get passTotal() {
        const p = this.passActivo;
        if (!p) return 0;
        const price = Number(p.price) || 0;
        return this.isFixedPass(p) ? price : price * this.personas;
      },

      get grandTotal() {
        return Math.max(0, this.passTotal + this.cartTotal);
      },

      get grandTotalFormatted() {
        return this.grandTotal > 0 ? this.formatEuro(this.grandTotal) : '—';
      },

      incrementar() {
        this.personas = Math.min(10, this.personas + 1);
      },

      decrementar() {
        this.personas = Math.max(1, this.personas - 1);
      },

      clearAfterPassInvalidated() {
        this.selectedPassId = '';
        this.fecha = '';
        this.hora = '';
      },

      matchesSelectedPass(p) {
        return String(p.id) === String(this.selectedPassId);
      },

      matchesPreselectedPass(p) {
        return String(p.id) === String(this.preselectedPassId);
      },

      isSelectedPassAvailable() {
        return this.pasesDisponibles.some(this.matchesSelectedPass);
      },

      applyPreselectedPassIfPossible() {
        if (!this.preselectedPassId) return;
        const ok = this.pasesDisponibles.some(this.matchesPreselectedPass);
        if (ok) {
          this.selectedPassId = String(this.preselectedPassId);
          this.preselectedPassId = '';
        }
      },

      parseJsonArray(val) {
        if (!val) return null;
        if (Array.isArray(val)) return val;
        if (typeof val === 'string') {
          try {
            return JSON.parse(val);
          } catch {
            return null;
          }
        }
        return null;
      },

      parseJsonObject(val) {
        if (!val) return null;
        if (typeof val === 'object') return val;
        if (typeof val === 'string') {
          try {
            return JSON.parse(val);
          } catch {
            return null;
          }
        }
        return null;
      },

      timeToMinutes(hhmm) {
        const parts = String(hhmm).split(':');
        const h = Number.parseInt(parts[0] || '0', 10);
        const m = Number.parseInt(parts[1] || '0', 10);
        return h * 60 + m;
      },

      minutesToTime(minutes) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
      },

      // ─── Festivos — API (NO de PHP) ─────────────────────────────

      async checkHoliday(fecha) {
        if (!fecha) return { is_holiday: false, holiday_name: null };

        // Check cache first
        if (this.holidaysCache[fecha]) {
          return this.holidaysCache[fecha];
        }

        try {
          const res = await fetch(`/api/v1/holidays/${fecha}`);
          if (!res.ok) {
            console.warn('Holiday API failed, assuming not holiday');
            return { is_holiday: false, holiday_name: null };
          }

          const json = await res.json();
          const result = {
            is_holiday: json.data?.is_holiday ?? false,
            holiday_name: json.data?.holiday_name ?? null,
          };

          // Cache result
          this.holidaysCache[fecha] = result;

          return result;
        } catch (e) {
          console.error('checkHoliday error:', e);
          return { is_holiday: false, holiday_name: null };
        }
      },

      permiteReserva(fecha) {
        const holidayData = this.holidayData;
        if (!holidayData || !holidayData.is_holiday) {
          return { permitido: true, mensaje: '' };
        }

        return {
          permitido: true, // Festivos SÍ permiten reservas (solo mostramos aviso)
          mensaje: `[Festivo] ${this.getHolidayDescription(holidayData.holiday_name)}`,
          is_holiday: true,
        };
      },

      getHolidayDescription(name) {
        if (!name) return 'Festivo nacional';

        const descriptions = {
          "New Year's Day": 'Año Nuevo — El café puede tener horario especial',
          'Epiphany': 'Epifanía del Señor (Reyes Magos)',
          'Good Friday': 'Viernes Santo — horario reducido',
          "Easter Sunday": 'Domingo de Pascua',
          "Easter Monday": 'Lunes de Pascua',
          "Labour Day": 'Día del Trabajo',
          "Assumption of Mary": 'Asunción de la Virgen',
          "National Day": 'Fiesta Nacional de España',
          "All Saints' Day": 'Todos los Santos',
          "Constitution Day": 'Día de la Constitución Española',
          "Immaculate Conception": 'Inmaculada Concepción',
          "Christmas Day": 'Navidad — El café puede tener horario especial',
          "Saint Stephen's Day": 'San Esteban',
        };

        return descriptions[name] || 'Festivo nacional — reserva con anticipación';
      },

      // ─── Watchers ───────────────────────────────────────────────

      onSelectedCafeChange() {
        this.selectedPassId = '';
        this.fecha = '';
        this.hora = '';
        this.allSlots = [];
        this.holidayData = null;
        this.applyPreselectedPassIfPossible();
        this.loadSidebarWeather();
      },

      onPersonasChange() {
        if (this.selectedPassId) {
          const stillValid = this.isSelectedPassAvailable();
          if (!stillValid) this.clearAfterPassInvalidated();
        }
        this.applyPreselectedPassIfPossible();
        if (this.selectedPassId && this.fecha) {
          this.fetchSlots();
        }
      },

      onSelectedPassChange(val) {
        this.hora = '';
        this.allSlots = [];
        if (!val) {
          this.fecha = '';
        }
        if (val && this.fecha) {
          this.fetchSlots();
        }
      },

      async onFechaChange() {
        this.hora = '';
        this.allSlots = [];
        this.holidayData = null;

        if (!this.fecha) return;

        // 1. Check holiday via API
        this.loadingHoliday = true;
        const holidayResult = await this.checkHoliday(this.fecha);
        this.holidayData = holidayResult;
        this.loadingHoliday = false;

        // 2. Check if reservations allowed
        const permisoReserva = this.permiteReserva(this.fecha);

        if (!permisoReserva.permitido) {
          alert(permisoReserva.mensaje);
          this.fecha = '';
          this.holidayData = null;
          return;
        }

        // 3. Load weather
        this.loadWeatherAndHolidays();

        // 4. Fetch slots if pass selected
        if (this.selectedPassId) {
          this.fetchSlots();
        }
      },

      // ─── Init ──────────────────────────────────────────────────

      async init() {
        try {
          const [cafesRes, passesRes] = await Promise.all([
            fetch('/api/v1/cafes'),
            fetch('/api/v1/passes'),
          ]);
          if (cafesRes.ok) {
            const json = await cafesRes.json();
            this.cafes = json.data?.items ?? [];
          } else {
            console.error('❌ Error cargando cafés:', cafesRes.status);
          }
          if (passesRes.ok) {
            const json = await passesRes.json();
            this.passes = json.data?.items ?? [];
          } else {
            console.error('❌ Error cargando pases:', passesRes.status);
          }
        } catch (err) {
          console.error('❌ Error cargando datos de reserva:', err);
        }

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
        this.$watch('personas', this.onPersonasChange.bind(this));
        this.$watch('selectedPassId', this.onSelectedPassChange.bind(this));
        this.$watch('fecha', this.onFechaChange.bind(this));

        this.applyPreselectedPassIfPossible();
        if (this.selectedCafeId) {
          this.loadSidebarWeather();
        }
        this.loadCarrito();
        await this.loadHistorial();
      },

      // ─── Loaders ────────────────────────────────────────────────

      async loadHistorial() {
        this.historialLoading = true;
        try {
          const res = await fetch('/api/v1/user/reservations');
          if (res.ok) {
            const json = await res.json();
            this.historial = json.data?.items ?? [];
          } else {
            console.error('❌ Error cargando historial:', res.status);
          }
        } catch (e) {
          console.error('❌ loadHistorial error:', e);
        } finally {
          this.historialLoading = false;
        }
      },

      async loadWeatherAndHolidays() {
        if (!this.fecha) {
          this.weatherData = null;
          this.loadingWeather = false;
          return;
        }

        const cafe = this.cafeActivo;
        const hasCoordinates = cafe && cafe.latitude && cafe.longitude;

        if (!hasCoordinates) {
          this.weatherData = null;
          this.loadingWeather = false;
          return;
        }

        this.loadingWeather = true;

        try {
          const weatherResponse = await fetch(
            `/api/v1/weather?lat=${cafe.latitude}&lon=${cafe.longitude}&timezone=${cafe.timezone || 'Europe/Madrid'}&themed_district=${cafe.themed_district || ''}${this.fecha ? '&date=' + this.fecha : ''}`
          );

          if (weatherResponse.ok) {
            const weatherData = await weatherResponse.json();

            if (weatherData.ok && weatherData.data && weatherData.data.local?.current) {
              const local = weatherData.data.local;
              const current = local.current;

              this.dateForecast = local.date_forecast ?? null;
              this.forecastUnavailable = local.forecast_unavailable ?? false;

              if (this.dateForecast && this.hora) {
                const [hh, mm] = this.hora.split(':').map(Number);
                const targetMin = hh * 60 + mm;
                let closest = this.dateForecast[0];
                let minDiff = Infinity;
                for (const entry of this.dateForecast) {
                  const t = entry.time.slice(11, 16);
                  const [eh, em] = t.split(':').map(Number);
                  const diff = Math.abs(eh * 60 + em - targetMin);
                  if (diff < minDiff) { minDiff = diff; closest = entry; }
                }
                this.weatherData = {
                  temp: Math.round(closest.temp),
                  description: this.getWeatherDescription(closest.weather_code),
                  is_forecast: true,
                };
              } else {
                this.weatherData = {
                  temp: Math.round(current.temp),
                  description: this.getWeatherDescription(current.weather_code),
                  is_forecast: false,
                };
              }
            } else {
              this.weatherData = null;
              this.dateForecast = null;
              this.forecastUnavailable = false;
            }
          } else {
            console.error('Error al cargar clima:', weatherResponse.status);
            this.weatherData = null;
          }
        } catch (error) {
          console.error('Error cargando clima:', error);
          this.weatherData = null;
        } finally {
          this.loadingWeather = false;
        }
      },

      getWeatherDescription(code) {
        const descriptions = {
          0: 'Despejado', 1: 'Mayormente despejado', 2: 'Parcialmente nublado', 3: 'Nublado',
          45: 'Niebla', 48: 'Niebla con escarcha',
          51: 'Llovizna ligera', 53: 'Llovizna moderada', 55: 'Llovizna intensa',
          61: 'Lluvia ligera', 63: 'Lluvia moderada', 65: 'Lluvia intensa',
          71: 'Nieve ligera', 73: 'Nieve moderada', 75: 'Nieve intensa', 77: 'Granizo',
          80: 'Chubascos ligeros', 81: 'Chubascos moderados', 82: 'Chubascos intensos',
          85: 'Chubascos de nieve ligeros', 86: 'Chubascos de nieve intensos',
          95: 'Tormenta', 96: 'Tormenta con granizo ligero', 99: 'Tormenta con granizo intenso'
        };
        return descriptions[code] || 'Clima variable';
      },

      // ─── Fetch Slots ────────────────────────────────────────────

      async fetchSlots() {
        if (!this.selectedCafeId || !this.selectedPassId || !this.fecha || !this.personas) {
          this.allSlots = [];
          return;
        }
        this.loadingSlots = true;
        this.slotsError = null;
        this.hora = '';
        try {
          const res = await fetch(
            `/api/v1/passes/slots?cafe_id=${this.selectedCafeId}&pass_id=${this.selectedPassId}&date=${encodeURIComponent(this.fecha)}&guests=${this.personas}`
          );
          if (res.ok) {
            const json = await res.json();
            this.allSlots = json.data?.slots ?? [];
          } else {
            const errJson = await res.json().catch(() => ({}));
            console.error('[fetchSlots] API error', res.status, errJson);
            this.slotsError = 'No se pudieron cargar los turnos disponibles. Inténtalo de nuevo.';
            this.allSlots = [];
          }
        } catch {
          this.slotsError = 'Error de conexión al cargar los turnos.';
          this.allSlots = [];
        } finally {
          this.loadingSlots = false;
        }
      },

      // ─── Waitlist Methods ───────────────────────────────────────

      openWaitlistModal() {
        this.waitlistModalOpen = true;
        this.waitlistGuests = this.personas;
        this.waitlistEmail = '';
        this.waitlistPhone = '';
        this.waitlistNotes = '';
        this.waitlistFormError = '';
        this.$nextTick(() => {
          const input = document.getElementById('waitlist-email');
          if (input) input.focus();
        });
      },

      async submitWaitlist() {
        if (!this.selectedCafeId || !this.selectedPassId || !this.fecha) {
          this.waitlistFormError = 'Selecciona café, pase y fecha primero';
          return;
        }

        if (this.waitlistLoading) return;

        this.waitlistLoading = true;
        this.waitlistFormError = '';

        try {
          const slotRes = await fetch(
            `/api/v1/time-slots/available?cafe_id=${this.selectedCafeId}&start_date=${this.fecha}&end_date=${this.fecha}&min_spots=0`
          );
          const slotData = await slotRes.json();

          if (!slotData.ok || !slotData.data?.slots?.length) {
            this.waitlistFormError = 'No se pudo obtener información del horario';
            return;
          }

          const pass = this.passActivo;
          const duration = pass?.duration_minutes || 60;
          const passStartMinutes = this.timeToMinutes(pass?.attributes?.allowed_start || '00:00');

          const targetSlot = this.waitlistTargetTime
            ? (slotData.data.slots.find(slot => slot.time === this.waitlistTargetTime) ?? slotData.data.slots[0])
            : (slotData.data.slots.find(slot => {
              const slotMinutes = this.timeToMinutes(slot.time);
              return slotMinutes >= passStartMinutes && slotMinutes + duration <= this.timeToMinutes('23:00');
            }) ?? slotData.data.slots[0]);

          if (!targetSlot?.id) {
            this.waitlistFormError = 'No hay horarios disponibles para este pase';
            return;
          }

          const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

          const res = await fetch('/api/v1/waitlists', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
              time_slot_id: targetSlot.id,
              guest_count: this.waitlistGuests,
              contact_email: this.waitlistEmail,
              contact_phone: this.waitlistPhone,
              special_requests: this.waitlistNotes,
            }),
          });

          const json = await res.json();

          if (res.ok && json.ok) {
            this.waitlistSuccess = true;
            this.waitlistError = false;
            this.waitlistMessage = `¡Te has unido a la lista de espera! Posición #${json.data?.position || '?'}`;
            this.waitlistPosition = json.data?.position;
            this.waitlistToken = json.data?.token;
            this.waitlistModalOpen = false;
            this.waitlistTargetTime = null;

            setTimeout(() => {
              this.waitlistSuccess = false;
            }, 8000);
          } else {
            this.waitlistError = true;
            this.waitlistSuccess = false;
            console.error('[submitWaitlist] API error', res.status, json);
            this.waitlistErrorMessage = 'No se pudo procesar la solicitud. Inténtalo de nuevo.';
          }
        } catch (e) {
          console.error('submitWaitlist error:', e);
          this.waitlistError = true;
          this.waitlistSuccess = false;
          this.waitlistErrorMessage = 'Error de conexión. Por favor, inténtalo de nuevo.';
        } finally {
          this.waitlistLoading = false;
        }
      },

      // ─── Feature D — Comanda ────────────────────────────────────

      async loadAlergenos() {
        if (this.alergenos.length > 0) return;
        this.loadingAlergenos = true;
        try {
          const res = await fetch('/api/v1/menu/alergenos');
          const json = await res.json();
          if (json.ok) this.alergenos = json.data ?? [];
        } catch (e) {
          console.error('loadAlergenos error', e);
        } finally {
          this.loadingAlergenos = false;
        }
      },

      toggleAlergeno(id) {
        const idx = this.alergenosExcluidos.indexOf(id);
        if (idx === -1) {
          this.alergenosExcluidos.push(id);
        } else {
          this.alergenosExcluidos.splice(idx, 1);
        }
        this.comandaLocal = this.comandaLocal.filter(item => {
          const prod = this.productosParaCafe.find(p => p.id === item.product_id);
          return prod ? !this.productoContieneAlergenoExcluido(prod) : true;
        });
      },

      sinAlergenos() {
        this.alergenosExcluidos = [];
      },

      productoContieneAlergenoExcluido(producto) {
        if (!this.alergenosExcluidos.length) return false;
        const alergenos = this.parseJsonArray(producto.allergens_list);
        if (!Array.isArray(alergenos)) return false;
        return alergenos.some(a => this.alergenosExcluidos.includes(a.id));
      },

      async loadProductos() {
        if (Object.keys(this.productos).length > 0) return;
        this.loadingProductos = true;
        try {
          const res = await fetch('/api/v1/menu/productos');
          const json = await res.json();
          if (json.ok) {
            this.productos = json.data ?? {};
          }
        } catch (e) {
          console.error('loadProductos error', e);
        } finally {
          this.loadingProductos = false;
        }
      },

      async loadCarrito() {
        if (this.carrito !== null) return;
        this.loadingCarrito = true;
        try {
          const res = await fetch('/api/cart');
          const json = await res.json();
          this.carrito = json.ok ? json.data : { items: {}, total_qty: 0, totalPrice: 0 };
        } catch {
          this.carrito = { items: {}, total_qty: 0, totalPrice: 0 };
        } finally {
          this.loadingCarrito = false;
        }
      },

      addToComanda(product) {
        if (product.stock_quantity !== null && product.stock_quantity !== undefined && Number(product.stock_quantity) === 0) return;
        const existing = this.comandaLocal.find(i => i.product_id === product.id);
        if (existing) {
          existing.qty++;
        } else {
          this.comandaLocal.push({
            product_id: product.id,
            name: product.name,
            price_cents: product.price,
            category_id: product.category_id,
            qty: 1,
          });
        }
      },

      removeFromComanda(productId) {
        const idx = this.comandaLocal.findIndex(i => i.product_id === productId);
        if (idx === -1) return;
        if (this.comandaLocal[idx].qty > 1) {
          this.comandaLocal[idx].qty--;
        } else {
          this.comandaLocal.splice(idx, 1);
        }
      },

      cantidadEnComanda(productId) {
        const inCart = this.carrito?.items?.[productId]?.qty ?? 0;
        const inLocal = this.comandaLocal.find(i => i.product_id === productId)?.qty ?? 0;
        return inCart + inLocal;
      },

      async loadSidebarWeather() {
        const cafe = this.cafeActivo;
        if (!cafe || !cafe.latitude || !cafe.longitude) {
          this.sidebarWeather = null;
          return;
        }
        this.sidebarWeatherLoading = true;
        try {
          const res = await fetch(
            `/api/v1/weather?lat=${cafe.latitude}&lon=${cafe.longitude}&timezone=${cafe.timezone || 'Europe/Madrid'}&themed_district=${cafe.themed_district || ''}`
          );
          if (res.ok) {
            const json = await res.json();
            if (json.ok && json.data?.local?.current) {
              const c = json.data.local.current;
              this.sidebarWeather = { temp: Math.round(c.temp), description: this.getWeatherDescription(c.weather_code) };
            } else {
              this.sidebarWeather = null;
            }
          } else {
            this.sidebarWeather = null;
          }
        } catch {
          this.sidebarWeather = null;
        } finally {
          this.sidebarWeatherLoading = false;
        }
      },

      // ─── Submit Reservation ─────────────────────────────────────

      async submitReservation() {
        if (!this.canSubmit || this.submitting) return;

        this.submitting = true;
        this.submitError = null;

        try {
          const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

          const resp = await fetch('/api/v1/reservations', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
              cafe_id: this.selectedCafeId,
              pass_product_id: this.selectedPassId,
              date: this.fecha,
              time: this.hora,
              guests: this.personas,
              special_requests: this.comentarios,
              pre_order: this.comandaLocal.map(i => ({ product_id: i.product_id, qty: i.qty })),
            }),
          });

          const json = await resp.json();

          if (resp.status === 201) {
            const id = json.data?.reservation_id ?? '';
            window.location.href = '/reservas/confirmacion/' + id;
          } else {
            this.submitError = json.detail ?? json.error ?? 'Error al crear la reserva';
          }
        } catch {
          this.submitError = 'Error de conexión. Por favor, inténtalo de nuevo.';
        } finally {
          this.submitting = false;
        }
      },

      // ─── Historial Helpers ──────────────────────────────────────

      isPast(res) {
        const ts = Date.parse(res.reservation_date + 'T' + (res.reservation_time || '00:00:00'));
        return !isNaN(ts) && ts < Date.now();
      },

      isCancelable(res) {
        const status = res.status || '';
        return ['pending', 'confirmed'].includes(status) && !this.isPast(res);
      },

      statusLabel(status) {
        const labels = {
          pending: 'Pendiente',
          confirmed: 'Confirmada',
          cancelled: 'Cancelada',
          completed: 'Completada',
          active: 'Activa',
          no_show: 'No presentado',
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
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            body: '{}',
          });

          if (resp.ok) {
            window.location.reload();
          } else {
            const json = await resp.json();
            alert(json.detail ?? 'No se pudo cancelar la reserva');
          }
        } catch {
          alert('Error de conexión. Por favor, inténtalo de nuevo.');
        }
      }
    };
  });
});
