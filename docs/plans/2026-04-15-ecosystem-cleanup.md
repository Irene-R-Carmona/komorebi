# Ecosystem Cleanup — Plan de Implementación

> **Objetivo:** Limpiar dependencias muertas, corregir bugs en Docker, actualizar imágenes de contenedores
> y eliminar redundancias de tooling, preparando el stack para la defensa TFG y producción.

**Fecha creación:** 15 de abril de 2026
**Estado:** 🟡 En implementación
**Contexto:** Post-`composer update` PHPUnit 12→13 / PHPStan 1→2 completado.
PHPUnit 13.1.4 + PHPStan 2.1.47 en `composer.lock`.

---

## Diagnóstico de partida

| Componente | Estado |
|------------|--------|
| PHPUnit | **13.1.4** instalado ✅ |
| PHPStan | **2.1.47** instalado ✅ |
| Tests | pendiente gate F1 |
| PHPStan baseline | pendiente gate F5 |
| Dependencias PHP muertas | 3 paquetes a eliminar |
| Dependencias JS muertas | 1 paquete + 1 script muerto |
| Workers en `make dev` | ❌ NO arrancan (COMPOSE_PROFILES no incluye `workers`) |
| Imágenes Docker desactualizadas | 8 referencias a actualizar |
| Dockerfile.prod Stage 1 | ❌ Código muerto (2-3 min overhead) |
| Dockerfile.worker base image | ❌ FrankenPHP innecesario para CLI |

---

## FASE 1 — Verificación post-upgrade (gates)

**Objetivo:** Confirmar que el upgrade PHPUnit 13 / PHPStan 2 no rompió nada.
**Depende de:** nada

- [ ] F1.1 — Ejecutar `make test-unit` → 0 errors, 0 failures, 0 risky
- [ ] F1.2 — Ejecutar `make phpstan` → `[OK] No errors` (con baseline)

**Entregable:** Suite verde + PHPStan verde antes de tocar dependencias.

---

## FASE 2 — Dependencias muertas

**Objetivo:** Eliminar paquetes que no aportan nada y ensucian `composer.json`/`package.json`.
**Depende de:** FASE 1 completa

### PHP (composer)

- [ ] F2.1 — `composer remove twbs/bootstrap-icons`
  - Motivo: cargado via CDN en layouts, nunca usado en PHP.
- [ ] F2.2 — `composer remove setasign/fpdf`
  - Motivo: solo `setasign/tfpdf` se instancia (`new \tFPDF()`); `fpdf` nunca.
- [ ] F2.3 — `composer remove psr/simple-cache`
  - Motivo: ningún `use Psr\SimpleCache` en el proyecto; llega transitivamente via `symfony/cache`.

### JS (npm)

- [ ] F2.4 — Eliminar `newman` de `package.json` devDependencies
  - Motivo: script `test:api` apunta a `docs/openapi.postman.json` que NO existe en el repo.
- [ ] F2.5 — Eliminar script `test:api` de `package.json`
- [ ] F2.6 — Eliminar target `test-api` de `Makefile` (si existe)

**Entregable:** `composer.json` + `package.json` sin paquetes muertos. Suite sigue verde.

---

## FASE 3 — Docker: Gap crítico — workers no arrancan

**Objetivo:** Los 3 workers (queue, email, notification) deben levantarse con `make dev`.
**Depende de:** nada (independiente)

- [ ] F3.1 — Cambiar `COMPOSE_PROFILES=dev` → `COMPOSE_PROFILES=dev,workers` en `.env.example`
- [ ] F3.2 — Verificar con `docker compose ps` que los 3 workers arrancan
- [ ] F3.3 — (Opcional) Añadir `make dev-full` como alias documentado en `Makefile`

**Impacto:** Sin este fix, emails, notificaciones y procesado de imágenes no funcionan en dev.

---

## FASE 4 — Docker: Actualizar versiones de imágenes

**Objetivo:** Eliminar referencias a versiones específicas desactualizadas.
**Depende de:** nada

| Imagen actual | Imagen objetivo | Archivos afectados |
|---------------|-----------------|-------------------|
| `dunglas/frankenphp:1.11.1-php8.4-alpine` | `dunglas/frankenphp:1.12.2-php8.4-alpine` | Dockerfile.dev, .prod, .worker |
| `composer:2.8` | `composer:2` (floating tag) | Dockerfile.dev, .prod, .worker |
| `# syntax=docker/dockerfile:1.7` | `# syntax=docker/dockerfile:1` | Dockerfile.dev, .prod, .worker |
| `axllent/mailpit:v1.21` | `axllent/mailpit:v1.29.6` | docker-compose.override.yml |
| `phpmyadmin:5.2.1-apache` | `phpmyadmin:latest` | docker-compose.override.yml |
| `rediscommander/redis-commander:0.8.0` | `redis/redisinsight:latest` | docker-compose.override.yml |
| `postgres:16-alpine` (SonarQube DB) | `postgres:17-alpine` | docker-compose.override.yml |
| `alpine:3.20` (telegram placeholder) | mover a `profiles: [telegram]` | docker-compose.override.yml |

- [ ] F4.1 — Actualizar FrankenPHP en Dockerfile.dev, Dockerfile.prod, Dockerfile.worker
- [ ] F4.2 — Actualizar `composer:2.8` → `composer:2` en los 3 Dockerfiles
- [ ] F4.3 — Actualizar `dockerfile syntax:1.7` → `1` en los 3 Dockerfiles
- [ ] F4.4 — Actualizar mailpit, phpmyadmin, redis-commander en docker-compose.override.yml
- [ ] F4.5 — Añadir `profiles: [telegram]` al servicio `telegram_bot`
- [ ] F4.6 — Actualizar postgres:16 → 17 para SonarQube DB

---

## FASE 5 — Docker: Bugs estructurales en Dockerfiles

**Objetivo:** Eliminar código muerto y corregir uso incorrecto de imagen base.
**Depende de:** FASE 4

### Dockerfile.prod — Stage 1 muerto

- [ ] F5.1 — Eliminar Stage 1 (`AS builder`) completo de Dockerfile.prod
  - Stage 3 (final) NUNCA copia desde Stage 1; lo reinstala todo desde cero.
  - Ahorro: 2-3 min de build time sin impacto en el artefacto final.
  - Stage 2 (`AS vendor-build`) + Stage 3 (final) quedan intactos.

### Dockerfile.worker — Base image incorrecta

- [ ] F5.2 — Cambiar runtime de Dockerfile.worker: `dunglas/frankenphp:1.12.2-php8.4-alpine` → `php:8.4-cli-alpine`
  - Workers ejecutan `php bin/worker.php` — nunca inician Caddy.
  - FrankenPHP = Caddy + PHP. Caddy es overhead puro para CLI workers.
  - Patrón correcto ya está en Dockerfile.test: `php:8.4-cli-alpine` + `mlocati/php-extension-installer:2`.

---

## FASE 6 — Docker: Config PHP menor

**Objetivo:** Corregir valores incorrectos en ini files y Caddyfile.
**Depende de:** nada

- [ ] F6.1 — `docker/php/ini/xdebug.ini`: `xdebug.log_level=7` → `xdebug.log_level=3`
  - Level 7 es máximo verboso; 3 (warnings) es suficiente para dev.
- [ ] F6.2 — `docker/php/ini/php.ini` base: extraer `session.cookie_secure = 0` a `error-dev.ini`
  - Añadir `session.cookie_secure = 1` en `error-prod.ini`.
- [ ] F6.3 — `frankenphp.Caddyfile`: `encode gzip zstd` → `encode br gzip zstd`
  - Caddy soporta brotli nativamente; un comentario ya lo menciona pero no está activo.

---

## FASE 7 — Decisión de equipo: Tooling redundante

**Objetivo:** Eliminar redundancias en el pipeline de calidad (decisión irreversible, requiere consenso).
**Depende de:** FASES 1-6 completas

### Opción A: Eliminar Psalm

- PHPStan 2.x con `phpstan-phpunit` cubre los mismos checks.
- Psalm nivel 5 tiene `MixedAssignment`, `MixedArgument`, `PropertyNotSetInConstructor` suprimidos → valor marginal.
- Pasos: `composer remove vimeo/psalm` + borrar `psalm.xml` + eliminar step de `.github/workflows/ci.yml`

- [ ] F7.1 — DECISIÓN: ¿Eliminar Psalm? (Sí / No)
- [ ] F7.2 — Si Sí: ejecutar pasos de eliminación

### Opción B: Eliminar phpcs

- `php-cs-fixer` ya cubre PSR-12 con auto-fix. `phpcs` solo verifica (no corrige).
- Pasos: `composer remove squizlabs/php_codesniffer` + borrar `phpcs.xml` + eliminar step de CI

- [ ] F7.3 — DECISIÓN: ¿Eliminar phpcs? (Sí / No)
- [ ] F7.4 — Si Sí: ejecutar pasos de eliminación

---

## FASE 8 — Opcional: Makefile y documentación

**Objetivo:** Mejorar DX documentando el stack en el Makefile.
**Depende de:** nada

- [ ] F8.1 — (Opcional) Añadir target `make dev-full` con `COMPOSE_PROFILES=dev,workers`
- [ ] F8.2 — (Opcional) Documentar en README el motivo de `profiles: [workers]`

---

## Archivo de referencia rápida

```bash
# Verificar gates (FASE 1)
docker compose exec app make test-unit
docker compose exec app make phpstan

# Eliminar deps muertas (FASE 2)
docker compose exec app composer remove twbs/bootstrap-icons setasign/fpdf psr/simple-cache
npm remove newman

# Levantar workers en dev (FASE 3) — editar .env primero
COMPOSE_PROFILES=dev,workers docker compose up -d
```
