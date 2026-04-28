# Repository + Mapper + DTO Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar el patrón Active Record de los 21 modelos y establecer un flujo
estrictamente tipado: Repositorio → Mapper → DTO → Servicio → Controlador.

**Architecture:** Los repositorios concretos inyectan un Mapper y devuelven DTOs directamente.
Los Mappers viven en `app/Domain/Mappers/` y son clases `final readonly` sin estado.
Los modelos se reducen a Value Objects con constantes de dominio.

**Tech Stack:** PHP 8.4, PDO, PHPUnit 13, PHPStan level 5, PSR-7/15, custom MVC.

---

## Archivos afectados

### Creados (FASE 1 — aditivos)

- `app/Domain/Mappers/MapperInterface.php` — contrato genérico
- `app/Domain/DTO/AllergenDTO.php`, `AuditLogDTO.php`, `AuthAuditLogDTO.php`,
  `FavoriteDTO.php`, `LoyaltyCardDTO.php`, `LoyaltyRewardDTO.php`,
  `LoyaltyRewardCatalogDTO.php`, `MenuCategoryDTO.php`, `PermissionDTO.php`,
  `ReservationItemDTO.php`, `RoleDTO.php`, `SettingDTO.php`, `TrackerDTO.php`
- `app/Domain/Mappers/` — 21 clases mapper (una por agregado)

### Modificados (FASES 2–4)

- `app/Repositories/AbstractRepository.php` — métodos de lectura públicos → protegidos con sufijo `Raw`
- `app/Repositories/Contracts/*.php` (31 interfaces) — tipos de retorno actualizados a DTOs
- `app/Repositories/*.php` (31 repos) — inyectar mapper; devolver DTOs
- `app/Http/Controllers/Admin/ReservationController.php` — eliminar `new Reservation()`
- `app/Http/Controllers/Admin/ProductController.php` — eliminar `$productModel`
- `app/Http/Controllers/Admin/MenuController.php` — eliminar `new Product(...)`

### Modificados (FASE 5)

- `app/Domain/DTO/DomainTransferObject.php` — eliminar `fromArray()` de la interfaz
- 9 DTOs existentes — eliminar método `fromArray()`
- ~12 servicios — eliminar imports de modelos; usar tipos de retorno de repos
- ~30 test unitarios — mocks devuelven DTOs en lugar de arrays

### Eliminados (FASE 5)

- `app/Models/AbstractModel.php`
- `app/Models/Contracts/UserModelInterface.php`, `ReservationModelInterface.php`,
  `TimeSlotModelInterface.php`, `WaitlistModelInterface.php`
- Métodos AR de 7 modelos (`User`, `Reservation`, `Product`, `Review`,
  `Waitlist`, `LoyaltyCard`, `Animal`) — quedan solo con constantes

---

## FASE 1 — Infraestructura de Mappers (aditiva, sin romper nada)

### F1.1 — MapperInterface

- [x] Crear `app/Domain/Mappers/MapperInterface.php`
- [x] Ejecutar `make phpstan` — 0 errores nuevos

### F1.2 — 13 nuevos DTOs

- [x] Crear `AllergenDTO` (id, code, name, japanese_name, icon_class, icon_color, severity, description)
- [x] Crear `AuditLogDTO` (id, user_id, action, resource_type, resource_id, old_values, new_values, ip_address, user_agent, created_at)
- [x] Crear `AuthAuditLogDTO` (id, user_id, event_type, success, reason, ip_address, user_agent, device_name, created_at)
- [x] Crear `FavoriteDTO` (user_id, cafe_id, created_at)
- [x] Crear `LoyaltyCardDTO` (id, user_id, stamps, current_tier, visits_count, total_rewards_redeemed, last_stamp_at, created_at, updated_at)
- [x] Crear `LoyaltyRewardDTO` (id, user_id, loyalty_card_id, reward_type, stamps_cost, redeemed_at, used_at, expires_at, status, redemption_code, notes, created_at)
- [x] Crear `LoyaltyRewardCatalogDTO` (id, reward_type, name_es, name_en, stamps_required, tier_required, validity_days, is_active, display_order, icon)
- [x] Crear `MenuCategoryDTO` (id, name, slug, display_order)
- [x] Crear `PermissionDTO` (id, code, name, description, resource, action)
- [x] Crear `ReservationItemDTO` (id, reservation_id, product_id, quantity, unit_price, status, created_at)
- [x] Crear `RoleDTO` (id, code, name, description)
- [x] Crear `SettingDTO` (key, value, type, group_name, description, is_public)
- [x] Crear `TrackerDTO` (id, cafe_id, code, type, status, last_assigned_at, cafe_name)
- [x] Ejecutar `make phpstan` — 0 errores nuevos

### F1.3 — 21 Mappers

- [x] Crear `AllergenMapper`
- [x] Crear `AnimalMapper`
- [x] Crear `AuditLogMapper`
- [x] Crear `AuthAuditLogMapper`
- [x] Crear `CafeMapper`
- [x] Crear `FavoriteMapper`
- [x] Crear `LoyaltyCardMapper`
- [x] Crear `LoyaltyRewardMapper`
- [x] Crear `LoyaltyRewardCatalogMapper`
- [x] Crear `MenuCategoryMapper`
- [x] Crear `PermissionMapper`
- [x] Crear `ProductMapper`
- [x] Crear `ReservationMapper`
- [x] Crear `ReservationItemMapper`
- [x] Crear `ReviewMapper`
- [x] Crear `RoleMapper`
- [x] Crear `SettingMapper`
- [x] Crear `TimeSlotMapper`
- [x] Crear `TrackerMapper`
- [x] Crear `UserMapper`
- [x] Crear `WaitlistMapper`
- [x] `make phpstan` → 0 errores | `make test-unit` → verde

---

## FASE 2 — AbstractRepository: métodos raw protegidos

> Objetivo: añadir métodos `*Raw()` protegidos en AbstractRepository; los repos concretos
> los usan internamente. Los métodos públicos originales quedan hasta FASE 5.

### F2.1

- [x] En `AbstractRepository`: añadir `protected function findByIdRaw(int $id): ?array`
  y `protected function findAllRaw(...): array` — delegando a los métodos SQL existentes
- [x] Los métodos públicos `findById()`, `findAll()`, `findPaginated()` NO se tocan aún
- [x] `make phpstan` → 0 errores

---

## FASE 3 — Repositorios concretos devuelven DTOs

> Por cada uno de los 31 repos: inyectar mapper, sobreescribir `findById()` / `findAll()`
> devolviendo DTOs, actualizar la interfaz correspondiente.
> **Procesar en lotes de 5; ejecutar tests tras cada lote.**

### F3.A — Lote 1 (infra base)

- [x] `AllergenRepository` → `AllergenMapper`, devuelve `AllergenDTO`
- [x] `AnimalRepository` → `AnimalMapper`, devuelve `AnimalDTO`
- [x] `CafeRepository` → `CafeMapper`, devuelve `CafeDTO`
- [x] `MenuCategoryRepository` → `MenuCategoryMapper`, devuelve `MenuCategoryDTO`
- [x] `PermissionRepository` → `PermissionMapper`, devuelve `PermissionDTO` _(repo no existe — N/A)_
- [x] Actualizar interfaces correspondientes en `Contracts/`
- [x] `make phpstan && make test-unit` → verde

### F3.B — Lote 2 (usuarios y roles)

- [x] `UserRepository` → `UserMapper`, devuelve `UserDTO`
- [x] `RoleRepository` → `RoleMapper`, devuelve `RoleDTO`
- [x] `SettingsRepository` → `SettingMapper`, devuelve `SettingDTO`
- [x] `TrackerRepository` → `TrackerMapper`, devuelve `TrackerDTO`
- [x] `FavoriteRepository` → _(sin `findById()` ni método que retorne entidad completa — N/A, igual que PermissionRepository)_
- [x] Actualizar interfaces
- [x] `make phpstan && make test-unit` → verde

### F3.C — Lote 3 (reservas y slots)

- [x] `ReservationRepository` → `ReservationMapper`, devuelve `ReservationDTO`
- [x] `ReservationItemRepository` → `ReservationItemMapper`, devuelve `ReservationItemDTO`
- [x] `TimeSlotRepository` → `TimeSlotMapper`, devuelve `TimeSlotDTO`
- [x] `WaitlistRepository` → `WaitlistMapper`, devuelve `WaitlistEntryDTO`
- [x] Actualizar interfaces
- [x] `make phpstan && make test-unit` → verde

### F3.D — Lote 4 (productos y menú)

- [x] `ProductRepository` → `ProductMapper`, devuelve `ProductDTO`
- [x] `ReviewRepository` → `ReviewMapper`, devuelve `ReviewDTO`
- [x] `LoyaltyRepository` → `LoyaltyCardMapper` + `LoyaltyRewardMapper`, devuelve DTOs
- [x] Actualizar interfaces
- [x] `make phpstan && make test-unit` → verde

### F3.E — Lote 5 (operaciones y staff)

- [x] Repos restantes: `AuditLogRepository`, `AuthAuditLogRepository`, `ShiftRepository`,
  `SupervisorAssignmentRepository`, `AnimalHealthCheckRepository`,
  `AnimalIncidentRepository`, `StockRepository`, etc.
- [x] Actualizar interfaces
- [x] `make phpstan && make test-unit` → verde

---

## FASE 4 — Purgar AR de controladores y modelos con AR

### F4.1 — Controladores con uso directo de modelos AR

- [ ] `Admin/ReservationController`: eliminar `new Reservation()` → inyectar `ReservationService`
- [ ] `Admin/ProductController`: eliminar `$this->productModel` → solo `$this->productRepo`
- [ ] `Admin/MenuController`: eliminar `new Product(...)` → usar `ProductRepository`
- [ ] `make phpstan && make test-unit` → verde

### F4.2 — Modelos con métodos AR (7 archivos)

- [ ] `User.php` — eliminar métodos DB; mantener constantes de rol/estado
- [ ] `Reservation.php` — eliminar `cancel()`, `checkIn()`, etc.; mantener constantes STATUS_*
- [ ] `Product.php` — eliminar métodos DB; mantener constantes
- [ ] `Review.php` — eliminar métodos DB; mantener constantes
- [ ] `Waitlist.php` — eliminar métodos DB; mantener constantes
- [ ] `LoyaltyCard.php` — eliminar métodos DB; mantener constantes TIER_*, STAMPS_*
- [ ] `Animal.php` — eliminar métodos DB; mantener constantes
- [ ] Eliminar `AbstractModel.php`
- [ ] Eliminar las 4 interfaces en `Models/Contracts/`
- [ ] `make phpstan && make test-unit` → verde

---

## FASE 5 — Limpieza final: DTOs, servicios y tests

### F5.1 — DomainTransferObject y fromArray()

- [ ] Eliminar `fromArray()` de `DomainTransferObject` interface
- [ ] Eliminar método `fromArray()` de los 9 DTOs existentes
- [ ] `make phpstan` → verificar que nada usa `fromArray()` ya
- [ ] `make test-unit` → verde

### F5.2 — Servicios (~12 archivos)

- [ ] Eliminar imports de modelos AR de cada servicio
- [ ] Actualizar llamadas que usaban `ModelClass::fromArray($row)` → ya no necesarias
  (repos devuelven DTOs directamente)
- [ ] `AuthService`, `KitchenService`, `WaitlistService`, `ReceptionService`,
  `UserProfileService`, `UserAccountService`, `SettingsService`, `CafeService`,
  `ProductService`, `LoyaltyService`, `ReservationService`, `AdminUserService`
- [ ] `make phpstan && make test-unit` → verde

### F5.3 — Tests unitarios (~30 archivos)

- [ ] Actualizar stubs/mocks de repos: devolver DTOs en lugar de arrays
- [ ] Actualizar tests de servicios que esperaban arrays y ahora reciben DTOs
- [ ] `make test-unit` → 1603+ tests, 0 failures, 0 errors

### F5.4 — Verificación final

- [ ] `make phpstan` → 0 errores
- [ ] `make test-unit` → verde
- [ ] `make cs-check` → sin violaciones PSR-12
- [ ] Cobertura ≥ 40.44% (baseline)
- [ ] Commit: `refactor(domain): complete AR→Repo+Mapper+DTO migration`

---

## Notas especiales

- **`StatisticsRepository`**: devuelve datos agregados multi-tabla. Documentar como
  `array<string, mixed>` (structured) hasta crear DTOs específicos de reporte en iteración futura.
- **`LoyaltyDTO`**: se mantiene temporalmente como DTO de vista; `LoyaltyCardDTO` es el nuevo
  DTO de persistencia. `LoyaltyDTO` se eliminará en una refactorización posterior.
- **Mappers son stateless**: instanciar con `new XxxMapper()` dentro del repositorio;
  no registrar en el container.
- **`fromArray()` en DTOs existentes**: se mantiene funcional hasta FASE 5 para no romper
  servicios que aún lo usan.
