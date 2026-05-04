# Plan de Refactorización In-Place de Migraciones SQL

**Fecha:** 2025
**Alcance:** Refactorización completa in-situ de 26 archivos de migración — sin añadir nuevos archivos.
**Objetivo:** Convenciones uniformes, integridad referencial completa, absorción de migraciones parche.

---

## Convenciones acordadas

| Tipo           | Patrón                                        |
|----------------|-----------------------------------------------|
| FK             | `fk_{tabla_hijo}_{tabla_padre}[_{disambig}]`  |
| UK             | `uk_{tabla}_{columnas}`                       |
| Índice regular | `idx_{tabla}_{columnas}`                      |
| CHECK          | `chk_{tabla}_{regla}`                         |

---

## Cambios por stream

### Stream 1 — 001_infrastructure.sql

- `cafes_slug_unique` → `uk_cafes_slug`
- `tracker_code_cafe` → `uk_trackers_cafe_code`
- `fk_zones_cafe` → `fk_cafe_zones_cafes`
- `fk_trackers_cafe` → `fk_trackers_cafes`

### Stream 2 — 002_users_rbac.sql

- `users_email_unique` → `uk_users_email`
- `users_uuid_unique` → `uk_users_uuid`
- `fk_rp_role` → `fk_role_permissions_roles`
- `fk_rp_perm` → `fk_role_permissions_permissions`
- `idx_role` (role_permissions) → `idx_role_permissions_role`
- `idx_permission` → `idx_role_permissions_permission`
- `fk_ur_user` → `fk_user_roles_users`
- `fk_ur_role` → `fk_user_roles_roles`
- `fk_ur_assigner` → `fk_user_roles_assigner`
- `idx_user` (user_roles) → `idx_user_roles_user`
- `idx_role` (user_roles) → `idx_user_roles_role`

### Stream 3 — 003_reviews.sql

- `unique_user_cafe` → `uk_reviews_user_cafe`
- `idx_user` (reviews) → `idx_reviews_user`
- `idx_reservation` → `idx_reviews_reservation`
- `fk_review_cafe` → `fk_reviews_cafes`
- `fk_review_user` → `fk_reviews_users`
- `idx_user` (audit_logs) → `idx_audit_logs_user`
- `idx_action` → `idx_audit_logs_action`
- `fk_audit_user` → `fk_audit_logs_users`
- `idx_user` (auth_audit_logs) → `idx_auth_audit_logs_user`
- `idx_event` → `idx_auth_audit_logs_event`
- `fk_aal_user` → `fk_auth_audit_logs_users`

### Stream 4 — 004_reservations.sql

- Absorber 017: columna `stock_quantity` inline en `products`
- Absorber 021 parcial: índices `idx_cafe_date_status`, `idx_reservations_user_status`
- Absorber 025 parcial: índices `idx_products_active`, `idx_reservations_user_status`, `idx_reservation_items_timeline`
- Eliminar bloque duplicado de `fk_review_reservation` (línea ~195); conservar sólo el de Sección 5
- Renombrar en Sección 5: `fk_review_reservation` → `fk_reviews_reservations`
- Añadir FK dinámica `fk_trackers_reservations` en Sección 5
- `fk_prod_cat` → `fk_products_menu_categories`
- `fk_res_user` → `fk_reservations_users`
- `fk_res_cafe` → `fk_reservations_cafes`
- `fk_res_pass` → `fk_reservations_products`
- `fk_res_tracker` → `fk_reservations_trackers`
- `fk_res_zone` → `fk_reservations_cafe_zones`
- `fk_items_res` → `fk_reservation_items_reservations`
- `fk_items_prod` → `fk_reservation_items_products`
- `fk_fav_user` → `fk_favorites_users`
- `fk_fav_cafe` → `fk_favorites_cafes`
- `fk_pa_product` → `fk_product_allergens_products`
- `fk_pa_allergen` → `fk_product_allergens_allergens`

### Stream 5 — 005_email_auth.sql

- `fk_evt_user` → `fk_email_verification_tokens_users`
- `fk_prt_user` → `fk_password_reset_tokens_users`
- `fk_as_user` → `fk_active_sessions_users`
- `fk_as_revoked_by` → `fk_active_sessions_revoked_by`
- `idx_user` (email_verification_tokens) → `idx_email_verification_tokens_user`
- `idx_user` (password_reset_tokens) → `idx_password_reset_tokens_user`
- `idx_user` (active_sessions) → `idx_active_sessions_user`
- Eliminar `INDEX idx_session (session_id)` (redundante con UNIQUE KEY)
- `unique_action_identifier` → `uk_rate_limits_action_identifier`
- `idx_action` (rate_limits) → `idx_rate_limits_action`

### Stream 6 — 006_telegram_bot.sql

- Renombrar tabla `telegram_message_log` → `telegram_message_logs`
- Actualizar evento: `DELETE FROM telegram_message_log` → `DELETE FROM telegram_message_logs`
- `fk_tu_user` → `fk_telegram_users_users`
- `fk_tml_user` → `fk_telegram_message_logs_telegram_users`

### Stream 7 — 007_external_cache.sql

- Renombrar tabla `api_audit_log` → `api_audit_logs`
- `idx_user_id` → `idx_api_audit_logs_user`
- `fk_apiaudit_user` → `fk_api_audit_logs_users`

### Stream 8 — 008_animals.sql

- Eliminar columna `last_check_at TIMESTAMP NULL` (deprecated)
- Absorber 019: añadir `status ENUM('open','resolved','monitoring','archived')` y `resolved_at` inline en `animal_incidents`
- Absorber 021 parcial: índice compuesto `idx_animal_incidents_status (animal_id, status)` (sustituye el simple de 019)
- Absorber 025 parcial: (ya cubierto con el compuesto)
- `species_key_unique` → `uk_species_rules_species_key`
- Añadir `fk_animals_species_rules` (animals.species_type → species_rules.species_key)
- `fk_ai_animal` → `fk_animal_incidents_animals`
- `fk_ai_user` → `fk_animal_incidents_reporter`
- `fk_ai_resolver` → `fk_animal_incidents_resolver`
- `fk_rel_a` → `fk_animal_relationships_a`
- `fk_rel_b` → `fk_animal_relationships_b`
- `fk_asl_animal` → `fk_animal_status_log_animals`
- `fk_asl_user` → `fk_animal_status_log_users`
- `fk_sess_animal` → `fk_interaction_sessions_animals`
- `fk_sess_res` → `fk_interaction_sessions_reservations`

### Stream 9 — 009_system_settings.sql

Sin cambios (tabla `settings`, sin FKs, convenciones correctas).

### Stream 10 — 010_newsletter.sql

- Absorber 026: añadir `expires_at TIMESTAMP NULL DEFAULT NULL` inline
- `unique_email` → `uk_newsletter_subscriptions_email`
- `unique_token` → `uk_newsletter_subscriptions_token`
- Añadir header `SET NAMES utf8mb4; SET FOREIGN_KEY_CHECKS = 0;` y footer

### Stream 11 — 011_time_slots_waitlist.sql

- `uk_time_slots_unique` → `uk_time_slots_cafe_date_time`

### Stream 12 — 012 / 012b

Sin cambios necesarios.

### Stream 13 — 013_loyalty_system.sql

- Absorber 022: añadir evento `evt_expire_loyalty_rewards` (renombrado desde `expire_loyalty_rewards`)
- Absorber 023: añadir CHECK `chk_user_animal_visits_rating` en `user_animal_visits`
- Absorber 024: `current_tier` → `VARCHAR(20) GENERATED ALWAYS AS (...) STORED`
- Absorber 021 parcial: añadir `idx_loyalty_rewards_user_status` e `idx_loyalty_rewards_status_expires`
- Absorber 025 parcial: añadir `idx_loyalty_last_stamp (last_stamp_at DESC)` a loyalty_cards
- `idx_user_id` (loyalty_cards UNIQUE) → `uk_loyalty_cards_user`
- `idx_tier` → `idx_loyalty_cards_tier`
- `idx_stamps` → `idx_loyalty_cards_stamps`
- Añadir `catalog_id BIGINT UNSIGNED NULL` a loyalty_rewards
- Añadir `fk_loyalty_rewards_catalog` (loyalty_rewards.catalog_id → loyalty_reward_catalog.id)
- `idx_user_id` (loyalty_rewards) → `idx_loyalty_rewards_user`
- `idx_loyalty_card_id` → `idx_loyalty_rewards_card`
- `idx_status` → `idx_loyalty_rewards_status`
- `idx_redemption_code` → `idx_loyalty_rewards_code`
- `idx_expires_at` → `idx_loyalty_rewards_expires`
- `fk_loyalty_reward_user` → `fk_loyalty_rewards_users`
- `fk_loyalty_reward_card` → `fk_loyalty_rewards_cards`
- En user_animal_visits: `idx_reservation` → `idx_user_animal_visits_reservation`; `idx_visited_at` → `idx_user_animal_visits_visited_at`
- `idx_reward_type` (UNIQUE, catalog) → `uk_loyalty_reward_catalog_reward_type`
- Reordenar: `loyalty_reward_catalog` CREATE TABLE debe ir ANTES que `loyalty_rewards`

### Stream 14 — 014_staff_shifts.sql

- Añadir nombre a los 3 FK anónimos:
  - `fk_staff_shifts_users` (user_id)
  - `fk_staff_shifts_cafes` (cafe_id)
  - `fk_staff_shifts_created_by` (created_by)
- `idx_staff_date` → `idx_staff_shifts_user_date`
- `idx_cafe_date` → `idx_staff_shifts_cafe_date`
- `idx_date_range` → `idx_staff_shifts_date_range`

### Stream 15 — 015_animal_health_checks.sql

- Añadir nombre a los 2 FK anónimos:
  - `fk_animal_health_checks_animals` (animal_id)
  - `fk_animal_health_checks_users` (checked_by)
- Absorber 025 parcial: añadir `idx_health_check_date (check_date DESC, animal_id)`

### Stream 16 — 016_supervisor_assignments.sql

- Añadir nombre a los 3 FK anónimos:
  - `fk_supervisor_assignments_supervisor` (supervisor_id)
  - `fk_supervisor_assignments_reservations` (reservation_id)
  - `fk_supervisor_assignments_cafes` (cafe_id)

### Stream 17 — 018_api_tokens.sql

- `CREATE TABLE api_tokens` → `CREATE TABLE IF NOT EXISTS api_tokens`
- `uq_token_hash` → `uk_api_tokens_token_hash`
- `idx_user_id` → `idx_api_tokens_user`
- `fk_at_user` → `fk_api_tokens_users`

### Stream 18 — Eliminar 9 migraciones absorbidas

| Archivo                          | Absorbido en      |
|----------------------------------|-------------------|
| 017_product_stock.sql            | 004               |
| 019_animal_incidents_status.sql  | 008               |
| 020_review_unique_constraint.sql | 003               |
| 021_integrity_indexes.sql        | 004, 008, 013     |
| 022_event_scheduler.sql          | 013               |
| 023_check_constraints.sql        | 013               |
| 024_loyalty_generated_column.sql | 013               |
| 025_performance_indexes.sql      | 004, 008, 013, 015|
| 026_newsletter_expires_at.sql    | 010               |

### Stream 19 — scripts/apply-db.php

- Eliminar `use TelegramSeeder` y entrada `Telegram` de `$seeders`
- Reducir `$migrations` a 001–016 + 018 (eliminar 017, 019–026)
- Ampliar `$tablesToClean` a 45 tablas completas
- Actualizar `$expectedEvents` a 11 eventos

### Stream 20 — app/Core/DatabaseSeeder.php

- Eliminar `use TelegramSeeder`, eliminar `TelegramSeeder::class` de SEEDERS
- Ampliar TABLES a 45 tablas (incluyendo todas las nuevas)
- Corregir `telegram_message_log` → `telegram_message_logs`

---

## Bugs corregidos

| # | Bug                                                      | Corrección                                             |
|---|----------------------------------------------------------|--------------------------------------------------------|
| 1 | `reviews`: UK duplicada (`unique_user_cafe` + `uq_user_cafe_review`) | Consolidar como `uk_reviews_user_cafe` en 003, eliminar 020 |
| 2 | `animal_incidents`: índice simple `(status)` redundante con compuesto | Solo el compuesto `(animal_id, status)` en 008         |
| 3 | `active_sessions`: `idx_session` redundante con UNIQUE KEY | Eliminar el índice simple en 005                       |
| 4 | `004`: dos bloques dinámicos para `fk_review_reservation` | Eliminar el primero; conservar sólo el de Sección 5   |

## Gaps de integridad resueltos

| Tabla hija          | Columna                       | Tabla padre              | FK añadida                    |
|---------------------|-------------------------------|--------------------------|-------------------------------|
| animals             | species_type                  | species_rules(species_key) | fk_animals_species_rules    |
| trackers            | last_assigned_reservation_id  | reservations(id)         | fk_trackers_reservations      |
| loyalty_rewards     | catalog_id (nueva)            | loyalty_reward_catalog(id) | fk_loyalty_rewards_catalog  |
| staff_shifts        | user_id / cafe_id / created_by | users / cafes            | FKs anónimas → nombradas      |
| supervisor_assignments | supervisor_id / reservation_id / cafe_id | users / reservations / cafes | FKs anónimas → nombradas |
| animal_health_checks | animal_id / checked_by       | animals / users          | FKs anónimas → nombradas      |
