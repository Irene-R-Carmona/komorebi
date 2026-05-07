document.addEventListener('alpine:init', () => {
  Alpine.data('reservaForm', (cartTotal = 0, festivosData = []) => {
    // Festivos desde PHP (no requieren API)
    let festivos = [];
    try {
      festivos = Array.isArray(festivosData) ? festivosData : [];
    } catch (error) {
      console.error('❌ Error al procesar festivos:', error);
    }

    return {
      cafes: [],
      passes: [],
      historial: [],
      historialLoading: false,
      festivos: festivos,
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

      // APIs externas
      weatherData: null,
      holidayData: null,
      loadingWeather: false,
      loadingHoliday: false,
      minDate: new Date().toISOString().split('T')[0],

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
          // Cafe types
          const targets = this.parseJsonArray(p.target_cafe_types);
          if (Array.isArray(targets) && targets.length > 0) {
            if (!targets.map(String).includes(cafeType)) return false;
          }

          // Animal types
          const animalTargets = this.parseJsonArray(p.target_animal_types);
          if (Array.isArray(animalTargets) && animalTargets.length > 0) {
            if (!animalTargets.map(String).includes(cafeAnimal)) return false;
          }

          // Pax rules
          const min = Number.parseInt(p.min_pax ?? 1, 10);
          const max = (p.max_pax === null || p.max_pax === undefined || p.max_pax === '') ? null : Number.parseInt(p.max_pax, 10);
          if (this.personas < min) return false;
          if (max !== null && this.personas > max) return false;

          // Duration
          const dur = Number.parseInt(p.duration_minutes ?? 0, 10);
          return dur > 0;


        });
      },

      get passActivo() {
        if (!this.selectedPassId) return null;
        return this.pasesDisponibles.find(p => String(p.id) === String(this.selectedPassId)) || null;
      },

      // Steps: 1 café+pase, 2 fecha+hora, 3 confirmar (NUEVO FLUJO: 3 en lugar de 5)
      get step() {
        if (!this.selectedCafeId || !this.selectedPassId) return 1;
        if (!this.fecha || !this.hora) return 2;
        return 3;
      },

      get progressPercent() {
        return ({ 1: 33, 2: 66, 3: 100 })[this.step] || 33;
      },

      get canSubmit() {
        return !!(this.selectedCafeId && this.selectedPassId && this.fecha && this.hora);
      },

      get horariosDisponibles() {
        const cafe = this.cafeActivo;
        const pass = this.passActivo;
        if (!cafe || !pass || !cafe.opening_time || !cafe.closing_time) return [];

        const openingMinutes = this.timeToMinutes(String(cafe.opening_time));
        const closingMinutes = this.timeToMinutes(String(cafe.closing_time));

        const dur = Number.parseInt(pass.duration_minutes ?? 60, 10);
        const STEP_MINUTES = 30;

        let slots = [];
        for (let start = openingMinutes; start + dur <= closingMinutes; start += STEP_MINUTES) {
          const h = Math.floor(start / 60);
          const m = start % 60;
          slots.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
        }

        const attrs = this.parseJsonObject(pass.attributes);
        if (attrs && (attrs.allowed_start || attrs.allowed_end)) {
          const allowedStart = attrs.allowed_start ? this.timeToMinutes(attrs.allowed_start) : null;
          const allowedEnd = attrs.allowed_end ? this.timeToMinutes(attrs.allowed_end) : null;

          slots = slots.filter((hhmm) => {
            const t = this.timeToMinutes(hhmm);
            if (allowedStart !== null && t < allowedStart) return false;
            return !(allowedEnd !== null && (t + dur) > allowedEnd);

          });
        }

        return slots;
      },

      // Precio: fijo si min=max>1, si no por persona
      isFixedPass(p) {
        const min = Number(p.min_pax ?? 1);
        const max = (p.max_pax === null || p.max_pax === undefined || p.max_pax === '') ? null : Number(p.max_pax);
        return (max !== null && min === max && max > 1);
      },

      priceLabel(p) {
        const price = Number(p.price) || 0;
        return this.isFixedPass(p) ? `¥${price.toLocaleString()} (fijo)` : `¥${price.toLocaleString()} / persona`;
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

      incrementar() {
        this.personas = Math.min(6, this.personas + 1);
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

      // Verifica si una fecha es festivo japonés
      getFestivoInfo(fecha) {
        if (!fecha || !this.festivos) return null;
        return this.festivos.find(f => f.fecha === fecha) || null;
      },

      // Verifica si se puede reservar en una fecha
      permiteReserva(fecha) {
        const festivo = this.getFestivoInfo(fecha);
        if (!festivo) return { permitido: true, mensaje: '' };

        return {
          permitido: festivo.permite_reservas === true || festivo.permite_reservas === 1,
          mensaje: festivo.permite_reservas
            ? `[Festivo] ${festivo.nombre_es} (${festivo.nombre_ja}) - Reserva con anticipación`
            : `[No disponible] ${festivo.nombre_es} (${festivo.nombre_ja}) - No se aceptan reservas este día`,
          festivo: festivo
        };
      },

      onSelectedCafeChange() {
        this.selectedPassId = '';
        this.fecha = '';
        this.hora = '';
        this.applyPreselectedPassIfPossible();
      },

      onPersonasChange() {
        if (this.selectedPassId) {
          const stillValid = this.isSelectedPassAvailable();
          if (!stillValid) this.clearAfterPassInvalidated();
        }
        this.applyPreselectedPassIfPossible();
      },

      onSelectedPassChange(val) {
        if (val) {
          this.hora = '';
        } else {
          this.fecha = '';
          this.hora = '';
        }
      },

      onFechaChange() {
        this.hora = '';

        // Verificar si la fecha seleccionada es un festivo
        const permisoReserva = this.permiteReserva(this.fecha);

        if (!permisoReserva.permitido) {
          // Resetear la fecha si no se permiten reservas
          alert(permisoReserva.mensaje);
          this.fecha = '';
          return;
        }

        // Si es festivo pero se permite reservar, mostrar aviso
        if (permisoReserva.festivo) {
          console.info(permisoReserva.mensaje);
        }

        this.loadWeatherAndHolidays();
      },

      async init() {
        // Cargar cafés y pases desde la API (FASE 3: shell)
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
        await this.loadHistorial();
      },

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

      // Cargar información de clima y festividades desde APIs reales
      async loadWeatherAndHolidays() {
        if (!this.fecha) {
          this.weatherData = null;
          this.holidayData = null;
          this.loadingWeather = false;
          this.loadingHoliday = false;
          return;
        }

        const cafe = this.cafeActivo;
        if (!cafe) return;

        // Verificar que el café tenga coordenadas
        if (!cafe.latitude || !cafe.longitude) {
          console.warn('El café no tiene coordenadas configuradas');
          this.weatherData = null;
          this.holidayData = null;
          this.loadingWeather = false;
          this.loadingHoliday = false;
          return;
        }

        // Inicializar estados de carga para clima y festivos
        this.loadingWeather = true;
        this.loadingHoliday = true;

        // Lanzar ambas peticiones en paralelo
        const weatherPromise = (async () => {
          try {
            const weatherResponse = await fetch(
              `/api/v1/weather?lat=${cafe.latitude}&lon=${cafe.longitude}&timezone=Asia/Tokyo`
            );

            if (weatherResponse.ok) {
              const weatherData = await weatherResponse.json();

              if (weatherData.ok && weatherData.data && weatherData.data.current) {
                const current = weatherData.data.current;
                this.weatherData = {
                  temp: Math.round(current.temp),
                  description: this.getWeatherDescription(current.weather_code)
                };
              } else {
                this.weatherData = null;
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
        })();

        const holidayPromise = (async () => {
          try {
            const holidayResponse = await fetch(
              `/api/v1/holidays/${this.fecha}`
            );

            if (holidayResponse.ok) {
              const holidayData = await holidayResponse.json();

              if (holidayData.ok && holidayData.data && holidayData.data.is_holiday) {
                this.holidayData = {
                  name: holidayData.data.holiday_name,
                  description: this.getHolidayDescription(holidayData.data.holiday_name)
                };
              } else {
                this.holidayData = null;
              }
            } else {
              console.error('Error al verificar festividad:', holidayResponse.status);
              this.holidayData = null;
            }
          } catch (error) {
            console.error('Error verificando festividad:', error);
            this.holidayData = null;
          } finally {
            this.loadingHoliday = false;
          }
        })();

        // Esperar a que ambas peticiones terminen
        await Promise.all([weatherPromise, holidayPromise]);
      },

      // Obtiene descripción del clima según el código WMO
      getWeatherDescription(code) {
        const descriptions = {
          0: 'Despejado',
          1: 'Mayormente despejado',
          2: 'Parcialmente nublado',
          3: 'Nublado',
          45: 'Niebla',
          48: 'Niebla con escarcha',
          51: 'Llovizna ligera',
          53: 'Llovizna moderada',
          55: 'Llovizna intensa',
          61: 'Lluvia ligera',
          63: 'Lluvia moderada',
          65: 'Lluvia intensa',
          71: 'Nieve ligera',
          73: 'Nieve moderada',
          75: 'Nieve intensa',
          77: 'Granizo',
          80: 'Chubascos ligeros',
          81: 'Chubascos moderados',
          82: 'Chubascos intensos',
          85: 'Chubascos de nieve ligeros',
          86: 'Chubascos de nieve intensos',
          95: 'Tormenta',
          96: 'Tormenta con granizo ligero',
          99: 'Tormenta con granizo intenso'
        };
        return descriptions[code] || 'Clima variable';
      },

      // Traduce descripciones meteorológicas al español (legacy)
      translateWeatherDescription(description) {
        const translations = {
          'clear sky': 'Despejado',
          'few clouds': 'Pocas nubes',
          'scattered clouds': 'Nubes dispersas',
          'broken clouds': 'Nublado',
          'overcast clouds': 'Muy nublado',
          'shower rain': 'Chubascos',
          'rain': 'Lluvia',
          'thunderstorm': 'Tormenta',
          'snow': 'Nieve',
          'mist': 'Niebla',
          'fog': 'Niebla densa'
        };

        const lowerDesc = description.toLowerCase();
        return translations[lowerDesc] || description;
      },

      // Obtiene descripciones para festividades japonesas
      getHolidayDescription(name) {
        const descriptions = {
          "New Year's Day": 'Año Nuevo - El café puede tener horario especial',
          "Coming of Age Day": 'Día de la Mayoría de Edad',
          "National Foundation Day": 'Día de la Fundación Nacional',
          "Emperor's Birthday": 'Cumpleaños del Emperador',
          "Vernal Equinox Day": 'Equinoccio de Primavera',
          "Showa Day": 'Día de Showa',
          "Constitution Day": 'Día de la Constitución',
          "Greenery Day": 'Día del Verde',
          "Children's Day": 'Día del Niño',
          "Marine Day": 'Día del Mar',
          "Mountain Day": 'Día de la Montaña',
          "Respect for the Aged Day": 'Día del Respeto a los Ancianos',
          "Autumnal Equinox Day": 'Equinoccio de Otoño',
          "Health and Sports Day": 'Día del Deporte y la Salud',
          "Culture Day": 'Día de la Cultura',
          "Labor Thanksgiving Day": 'Día de Acción de Gracias por el Trabajo'
        };

        return descriptions[name] || 'Festividad nacional - Reserva con anticipación';
      },

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
