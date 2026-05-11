# Time Slots y Sistema de Disponibilidad — Plan de Implementación

> **Para agentes:** Implementar tarea a tarea usando `executing-plans` o `subagent-driven-development`.
> Ejecutar `make db-reset` tras Fase 2 para aplicar el cambio de migration.

**Goal:** Corregir el sistema de time_slots: timezone bug (slots ocultos antes de las 12h), duración
incorrecta (DEFAULT 60 en vez de 30), falta de actualización span-aware al crear/cancelar reservas,
y cambiar el display de `%` a `X/Y personas`.

**Causa raíz del timezone bug:** `APP_BUSINESS_TIMEZONE=Asia/Tokyo` en `.env` pero el café opera en
`Europe/Madrid`. España 05:26 = Tokio 12:26 → `AvailabilityService` descarta slots antes de 12:30.

**Architecture:** 6 fases secuenciales. Fases 1 y 2 son independientes entre sí (paralelas).
Fases 3-6 son secuenciales. La Fase 4 depende de la 3. La Fase 5 depende de la 3. La Fase 6 depende de la 5.

**Tech Stack:** PHP 8.4, Custom MVC, MySQL, FrankenPHP Worker Mode (requiere `docker compose restart app` tras cambios PHP).

---

## Fase 1 — Fix timezone (independiente)

**Archivos:** `app/Services/AvailabilityService.php`

### Tareas

- [ ] **1.1** Añadir `use DateTimeZone;` en los imports de `AvailabilityService.php` (ya tiene `use DateTimeImmutable;`).

- [ ] **1.2** En `getAvailableSlots()`, bloque `if ($daysAhead === 0)`, sustituir:

  ```php
  $now = Time::nowBusiness();
  ```

  por:

  ```php
  $cafeTz = new DateTimeZone($cafe->timezone ?: 'Europe/Madrid');
  $now = new DateTimeImmutable('now', $cafeTz);
  ```

- [ ] **1.3** En el array del `Result::ok()` final, cambiar:

  ```php
  'timezone' => Env::get('APP_BUSINESS_TIMEZONE', 'Asia/Tokyo'),
  ```

  por:

  ```php
  'timezone' => $cafe->timezone,
  ```

**Verificación fase 1:**

- `docker compose exec app php -l app/Services/AvailabilityService.php` → sin errores
- En browser `/reservas` con fecha de hoy → slots de 11:00 visibles (antes ocultos por Tokyo TZ)

---

## Fase 2 — Fix duration_minutes (independiente, paralela con Fase 1)

**Archivos:** `migrations/011_time_slots_waitlist.sql`, `app/Core/Seeders/TimeSlotSeeder.php`

### Tareas

- [ ] **2.1** Editar **directamente** `migrations/011_time_slots_waitlist.sql` línea 39. Cambiar:

  ```sql
  duration_minutes TINYINT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Duración estándar en minutos',
  ```

  por:

  ```sql
  duration_minutes TINYINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Duración estándar en minutos',
  ```

  > Regla del proyecto: NO crear migration nueva. Editar la migration definitiva directamente.
  > Aplicar con `make db-reset` (destructivo — pedir confirmación al usuario antes de ejecutar).

- [ ] **2.2** En `app/Core/Seeders/TimeSlotSeeder.php`, método `createTimeSlots()`, el `INSERT` actual NO incluye `duration_minutes`. Cambiar:

  ```sql
  INSERT INTO time_slots
  (cafe_id, slot_date, slot_time, total_capacity, reserved_spots, available_spots, is_blocked)
  VALUES (:cafe_id, :date, :time, :capacity, 0, :available, 0)
  ```

  por:

  ```sql
  INSERT INTO time_slots
  (cafe_id, slot_date, slot_time, total_capacity, reserved_spots, available_spots, is_blocked, duration_minutes)
  VALUES (:cafe_id, :date, :time, :capacity, 0, :available, 0, :slot_duration)
  ```

  Y añadir el parámetro en el array `execute()`:

  ```php
  'slot_duration' => $slotDuration,
  ```

**Verificación fase 2** (tras `make db-reset`):

- `SELECT DISTINCT duration_minutes FROM time_slots;` → solo devuelve `30`

---

## Fase 3 — Método span-aware en TimeSlotRepository (depende de ninguna)

**Archivos:** `app/Repositories/Contracts/TimeSlotRepositoryInterface.php`, `app/Repositories/TimeSlotRepository.php`

> Este método actualiza **todos los slots solapados** por rango de tiempo (hora inicio + duración),
> no solo el slot exacto. Un pase de 90 min abarca 3 slots de 30 min.

### Tareas

- [ ] **3.1** Añadir la firma del método al final de `TimeSlotRepositoryInterface.php`:

  ```php
  /**
   * Ajusta la ocupación de todos los slots que solapen con el rango [startTime, startTime+duration).
   * Operación atómica. Usa overlap SQL para abarcar pases de múltiples slots.
   *
   * @param int    $cafeId          ID del café
   * @param string $date            Fecha en formato Y-m-d
   * @param string $startHHMM       Hora de inicio en formato HH:MM (sin segundos)
   * @param int    $durationMinutes Duración total de la reserva en minutos
   * @param int    $guests          Número de personas
   * @param bool   $release         true = liberar (cancelación), false = reservar (creación)
   */
  public function adjustOccupancyByRange(
      int $cafeId,
      string $date,
      string $startHHMM,
      int $durationMinutes,
      int $guests,
      bool $release,
  ): void;
  ```

- [ ] **3.2** Implementar `adjustOccupancyByRange()` en `TimeSlotRepository.php`:

  ```php
  #[Override]
  public function adjustOccupancyByRange(
      int $cafeId,
      string $date,
      string $startHHMM,
      int $durationMinutes,
      int $guests,
      bool $release,
  ): void {
      // Calcular hora de fin como HH:MM:SS
      $startTotalMins = (int) \substr($startHHMM, 0, 2) * 60 + (int) \substr($startHHMM, 3, 2);
      $endTotalMins   = $startTotalMins + $durationMinutes;
      $endHHMMSS      = \sprintf('%02d:%02d:00', (int) ($endTotalMins / 60), $endTotalMins % 60);
      $startHHMMSS    = $startHHMM . ':00';

      $this->getDb()->prepare(
          'UPDATE time_slots
           SET
               reserved_spots = CASE
                   WHEN :release1 = 1 THEN GREATEST(0, reserved_spots - :guests1)
                   ELSE LEAST(total_capacity, reserved_spots + :guests2)
               END,
               available_spots = CASE
                   WHEN :release2 = 1 THEN LEAST(total_capacity, available_spots + :guests3)
                   ELSE GREATEST(0, available_spots - :guests4)
               END,
               updated_at = NOW()
           WHERE cafe_id = :cafe_id
             AND slot_date = :date
             AND TIME_TO_SEC(slot_time) < TIME_TO_SEC(:end_time)
             AND (TIME_TO_SEC(slot_time) + duration_minutes * 60) > TIME_TO_SEC(:start_time)
             AND is_blocked = 0'
      )->execute([
          'release1'   => $release ? 1 : 0,
          'release2'   => $release ? 1 : 0,
          'guests1'    => $guests,
          'guests2'    => $guests,
          'guests3'    => $guests,
          'guests4'    => $guests,
          'cafe_id'    => $cafeId,
          'date'       => $date,
          'start_time' => $startHHMMSS,
          'end_time'   => $endHHMMSS,
      ]);
  }
  ```

  Añadir `use Override;` si no está ya en los imports.

**Verificación fase 3:**

- `docker compose exec app php -l app/Repositories/TimeSlotRepository.php`
- `make phpstan` → sin errores nuevos relacionados con este método

---

## Fase 4 — Conectar ReservationService con time_slots (depende de Fase 3)

**Archivos:** `app/Services/ReservationService.php`, `app/Providers/ReservationServiceProvider.php`

### Tareas

- [ ] **4.1** En `ReservationService.php`, añadir import al bloque `use`:

  ```php
  use App\Repositories\Contracts\TimeSlotRepositoryInterface;
  ```

  Y añadir propiedad privada:

  ```php
  private ?TimeSlotRepositoryInterface $timeSlotRepo;
  ```

  Y añadir parámetro al constructor (último, tras `$passInclusionRepo`):

  ```php
  ?TimeSlotRepositoryInterface $timeSlotRepo = null,
  ```

  Y en el cuerpo del constructor:

  ```php
  $this->timeSlotRepo = $timeSlotRepo;
  ```

- [ ] **4.2** En el método `create()`, dentro de la transacción `Database::transaction()`, tras la línea que ejecuta `$this->reservationRepo->create()` (que devuelve `$reservationId`), añadir:

  ```php
  // Actualizar ocupación en time_slots (span-aware)
  if ($this->timeSlotRepo !== null && $duration > 0) {
      $this->timeSlotRepo->adjustOccupancyByRange(
          $cafeId,
          $date,
          $time,
          $duration,
          $guests,
          false,
      );
  }
  ```

  > `$duration = $pass->duration_minutes ?? 0` ya existe en el scope de `create()`.

- [ ] **4.3** En el método `cancel()` (si existe), tras la llamada exitosa a `$this->reservationRepo->cancel()`, añadir:

  ```php
  if ($this->timeSlotRepo !== null) {
      $this->timeSlotRepo->adjustOccupancyByRange(
          (int) $reservation['cafe_id'],
          (string) $reservation['reservation_date'],
          \substr((string) $reservation['reservation_time'], 0, 5),
          (int) ($reservation['pass_duration_minutes'] ?? 0),
          (int) $reservation['guest_count'],
          true,
      );
  }
  ```

- [ ] **4.4** En el método `adminCancel()` (si existe), misma lógica que 4.3 usando los datos de la reserva cargada.

- [ ] **4.5** En `app/Providers/ReservationServiceProvider.php`, en el bloque `Container::singleton(ReservationService::class, ...)`, añadir como último argumento:

  ```php
  Container::make(TimeSlotRepositoryInterface::class)
  ```

  El bloque actual termina con `Container::make(PassInclusionRepositoryInterface::class)`. Añadir una línea más.

**Verificación fase 4:**

- `docker compose exec app php -l app/Services/ReservationService.php`
- `make test-unit` → pasan todos
- Crear reserva de prueba → verificar en DB: `SELECT reserved_spots, available_spots FROM time_slots WHERE slot_date = '...' AND cafe_id = ...` muestra valores decrementados para todos los slots solapados.

---

## Fase 5 — AvailabilityService con capacidad por slot (depende de Fase 3)

**Archivos:** `app/Services/AvailabilityService.php`, `app/Providers/ReservationServiceProvider.php`

### Tareas

- [ ] **5.1** En `AvailabilityService.php`, añadir import:

  ```php
  use App\Repositories\Contracts\TimeSlotRepositoryInterface;
  ```

  Añadir propiedad:

  ```php
  private ?TimeSlotRepositoryInterface $timeSlotRepo;
  ```

  Añadir parámetro al constructor (tras `$reservationRepo`, antes de `$maxDaysAhead`):

  ```php
  ?TimeSlotRepositoryInterface $timeSlotRepo = null,
  ```

  Y en el cuerpo del constructor:

  ```php
  $this->timeSlotRepo = $timeSlotRepo;
  ```

- [ ] **5.2** En `getAvailableSlots()`, antes del bucle `for ($t = $first; ...)`, añadir:

  ```php
  // Cargar metadatos de slots desde time_slots para capacidad individual
  $slotMeta = [];
  if ($this->timeSlotRepo !== null) {
      $rawSlots = $this->timeSlotRepo->findAvailableSlots($cafeId, $dateYmd);
      foreach ($rawSlots as $ts) {
          $key = \substr((string) $ts['slot_time'], 0, 5); // HH:MM
          $slotMeta[$key] = [
              'capacity' => (int) $ts['total_capacity'],
              'blocked'  => (bool) $ts['is_blocked'],
          ];
      }
  }
  ```

  > Nota: `findAvailableSlots()` ya existe en `TimeSlotRepository` y filtra `is_blocked = 0`.
  > Para incluir slots bloqueados en el mapa, usar una query directa o añadir `findByDateRaw()`.
  > **Decisión:** Usar `findAvailableSlots()` como base. Los slots bloqueados no aparecen
  > → se tratarán con el fallback de `$capacityMax` y siempre como no disponibles (sin row en $slotMeta).

- [ ] **5.3** Dentro del bucle `for ($t = $first; ...)`, cambiar la lógica de capacidad:

  ```php
  $slotKey = $this->minutesToHHMM($t);
  $slotCapacity = $slotMeta[$slotKey]['capacity'] ?? $capacityMax;

  $isAvailable = ($occupied + $guests) <= $slotCapacity;
  $slots[] = [
      'time'            => $slotKey,
      'available'       => $isAvailable,
      'occupied_guests' => $occupied,
      'total_capacity'  => $slotCapacity,   // ← reemplaza 'capacity_pct'
  ];
  ```

  > Eliminar la línea de `'capacity_pct'` del array.
  > La variable `$capacityMax` ya existe más arriba; seguir usándola como fallback.

- [ ] **5.4** En `ReservationServiceProvider.php`, en el bloque `Container::singleton(AvailabilityService::class, ...)`, añadir como 4º argumento (tras `$reservationRepo`):

  ```php
  Container::make(TimeSlotRepositoryInterface::class),
  ```

**Verificación fase 5:**

- `docker compose exec app php -l app/Services/AvailabilityService.php`
- `make phpstan` → sin errores nuevos
- API `/api/v1/availability/slots?cafe_id=1&pass_product_id=1&date=2026-05-15&guests=2` → respuesta JSON incluye `total_capacity` por slot (no `capacity_pct`)

---

## Fase 6 — Display X/Y personas (depende de Fase 5)

**Archivos:** `public/js/sections/reservas.js`, `resources/views/shared/reservas/index.php`

### Tareas

- [ ] **6.1** En `public/js/sections/reservas.js`, getter `slotsConOcupacion` (~línea 112),
  cambiar el cálculo que usa `slot.capacity_pct`:

  ```js
  // ANTES:
  const capacityPct = slot.capacity_pct ?? 0;

  // DESPUÉS:
  const totalCap = slot.total_capacity ?? 0;
  const capacityPct = totalCap > 0
      ? Math.round(((slot.occupied_guests ?? 0) / totalCap) * 100)
      : 0;
  ```

- [ ] **6.2** En `resources/views/shared/reservas/index.php`, línea ~416 (aria-label del slot):

  ```php
  // ANTES:
  :aria-label="s.capacity_pct + '% ocupado'"
  // DESPUÉS:
  :aria-label="s.occupied_guests + '/' + s.total_capacity + ' personas'"
  ```

- [ ] **6.3** En la misma vista, línea ~418 (x-text del badge de ocupación):

  ```php
  // ANTES:
  x-text="s.capacity_pct + '%'"
  // DESPUÉS:
  x-text="s.occupied_guests + '/' + s.total_capacity"
  ```

  El `x-show="s.occupied_guests > 0"` no cambia.

**Verificación fase 6:**

- En browser `/reservas`, seleccionar fecha de hoy → slots muestran "5/20" en vez de "25%"
- Slot sin reservas: "0/20" (solo si `x-show` lo permite) o se oculta correctamente

---

## Archivos modificados (resumen)

| Archivo | Fase(s) |
|---|---|
| `app/Services/AvailabilityService.php` | 1, 5 |
| `migrations/011_time_slots_waitlist.sql` | 2 |
| `app/Core/Seeders/TimeSlotSeeder.php` | 2 |
| `app/Repositories/Contracts/TimeSlotRepositoryInterface.php` | 3 |
| `app/Repositories/TimeSlotRepository.php` | 3 |
| `app/Services/ReservationService.php` | 4 |
| `app/Providers/ReservationServiceProvider.php` | 4, 5 |
| `public/js/sections/reservas.js` | 6 |
| `resources/views/shared/reservas/index.php` | 6 |

---

## Decisiones registradas

- **Denominador en display:** `time_slots.total_capacity` (solicitado explícitamente — no `cafes.capacity_max`).
- **Fallback:** Cuando no existe row en `time_slots` para un slot, se usa `$cafes.capacity_max` como denominador.
- **`capacity_pct` eliminado:** Se elimina del array de respuesta de `AvailabilityService`; el JS lo recalcula localmente.
- **`ReservationTimeSlotService` (Sistema B):** Queda fuera del scope de este plan. Es un sistema paralelo para otra iteración.
- **No crear migrations nuevas:** Editar `011_time_slots_waitlist.sql` directamente y aplicar con `make db-reset`.
- **FrankenPHP:** Tras cualquier cambio PHP, ejecutar `docker compose restart app` para limpiar bytecode cacheado.

## Comandos de verificación global

```bash
docker compose exec app php -l app/Services/AvailabilityService.php
docker compose exec app php -l app/Services/ReservationService.php
docker compose exec app php -l app/Repositories/TimeSlotRepository.php
make phpstan
make test-unit
make db-reset   # DESTRUCTIVO — solo tras confirmar con usuario
```
