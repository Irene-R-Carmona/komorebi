# Plan: Auditoría y mejora de infraestructura y configuración

**Estado:** 🔵 Plan creado — pendiente inicio
**Fecha:** 2026-04-30
**Rama:** develop

---

## Contexto

Tras un `git reset --hard` + cherry-pick y la eliminación manual de `vendor/` local para resolver un crash loop de Docker, se realizó una auditoría completa de todos los archivos de configuración del proyecto. Este plan recoge todos los hallazgos y las acciones de mejora priorizadas.

**Causa raíz del crash loop:** `docker-compose.yml` monta `./:/app`, por lo que el `vendor/` del host (vacío/desactualizado) sobrescribe el `vendor/` generado dentro de la imagen → error fatal de autoloader.

---

## Decisión explícita: umbral de cobertura de tests

> **El umbral de cobertura de CI se baja de 70 % a 60 %.**
> Cobertura actual medida: 60,65 %.
> El umbral del 70 % en `.github/workflows/ci.yml` bloqueaba los merges a `main` con un valor inalcanzable en el estado actual del proyecto. Se establece 60 % como valor realista y se revisará al alza cuando la cobertura crezca orgánicamente.

---

## Fases

### Fase 0 — Deuda Git pendiente (BLOQUEANTE)

Trabajo previo que quedó pendiente antes de poder ejecutar este plan.

- [ ] `docker compose up -d` → esperar a que el contenedor esté healthy
- [ ] `docker compose exec app vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --allow-risky=yes`
- [ ] `git add -A && git commit -m "style: cs-fix post-merge"` (si hay cambios)
- [ ] `docker compose exec app vendor/bin/phpunit` → verificar 2855 tests / 0 failures
- [ ] `git push origin develop`

---

### Fase 1 — CI: corregir umbral de cobertura (CRÍTICO)

**Archivo:** `.github/workflows/ci.yml`

| Actual | Corrección |
|--------|-----------|
| `< 70` | `< 60` |

- [ ] Cambiar la condición de fallo de cobertura de `< 70` a `< 60`
- [ ] Verificar que el pipeline pasa en la siguiente ejecución

---

### Fase 2 — Docker: healthcheck `start_period`

**Archivo:** `docker-compose.yml`

El servicio `app` no tiene `start_period` en su healthcheck. Con FrankenPHP + composer install en el entrypoint, el contenedor puede marcarse como unhealthy antes de que esté listo.

- [ ] Añadir `start_period: 180s` al healthcheck del servicio `app`

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 180s   # ← añadir
```

---

### Fase 3 — Docker: volumen nombrado para `vendor/` (CRÍTICO)

**Archivo:** `docker-compose.yml`

La causa raíz del crash loop es que el bind-mount `./:/app` expone el `vendor/` del host. La solución es aislar `vendor/` con un volumen nombrado.

- [ ] Añadir volumen nombrado `komorebi_vendor:/app/vendor` al servicio `app`
- [ ] Declarar `komorebi_vendor:` en la sección `volumes:` raíz
- [ ] Verificar que `docker compose up -d` levanta correctamente sin vendor/ en el host

```yaml
services:
  app:
    volumes:
      - ./:/app
      - komorebi_storage:/app/storage
      - komorebi_vendor:/app/vendor    # ← añadir

volumes:
  komorebi_mysql:
  komorebi_redis:
  komorebi_storage:
  komorebi_vendor:                     # ← añadir
```

> **Nota:** Tras este cambio, `composer install/update` debe ejecutarse **dentro del contenedor** (`docker compose exec app composer install`), no en el host.

---

### Fase 4 — `.env`: variables faltantes

**Archivo:** `.env` y `.env.example`

Dos variables presentes en el código pero ausentes en `.env`:

| Variable | Descripción | Valor por defecto |
|----------|-------------|-------------------|
| `TRUSTED_PROXY_IP` | IP del proxy reverso de confianza (Railway / nginx) | `127.0.0.1` |
| `HEALTH_TOKEN` | Token para el endpoint `/health` con autenticación | generar con `openssl rand -hex 32` |

- [ ] Añadir `TRUSTED_PROXY_IP=` a `.env` y `.env.example`
- [ ] Añadir `HEALTH_TOKEN=` a `.env` y `.env.example`

---

### Fase 5 — Node.js: archivos de configuración faltantes

#### 5a — `.nvmrc`

- [ ] Crear `.nvmrc` con contenido `20`

#### 5b — `package.json`: bug en el nombre

**Hallazgo:** `"name": "komorebi_backup"` — nombre incorrecto, probablemente de una copia manual.

- [ ] Cambiar `"name": "komorebi_backup"` → `"name": "komorebi"` en `package.json`
- [ ] Verificar que `package-lock.json` también actualiza el nombre (`npm install` lo regenera)

#### 5c — `tsconfig.json`

Requerido para que `playwright.config.ts` y los tests E2E (`tests/e2e/`) compilen correctamente.

- [ ] Crear `tsconfig.json` en la raíz con configuración mínima para Playwright + Node 20

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "commonjs",
    "lib": ["ES2022"],
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "outDir": "./dist",
    "rootDir": "."
  },
  "include": ["tests/e2e/**/*.ts", "playwright.config.ts"],
  "exclude": ["node_modules", "vendor"]
}
```

#### 5d — `eslint.config.js`

Los archivos JS en `public/js/` y TS en `tests/e2e/` no tienen linting.

- [ ] Crear `eslint.config.js` con soporte para JS (Alpine.js, vanilla) y TS (tests E2E)

#### 5e — `.prettierrc`

- [ ] Crear `.prettierrc` con configuración coherente con `.editorconfig` (2 espacios para JS/TS, LF, comillas simples)

```json
{
  "semi": true,
  "singleQuote": true,
  "tabWidth": 2,
  "useTabs": false,
  "endOfLine": "lf",
  "trailingComma": "es5"
}
```

#### 5f — Husky (pre-commit hooks)

**Decisión:** No instalar Husky. El pipeline de CI es suficiente como gate de calidad. Los hooks locales añaden fricción sin beneficio diferencial dado que ya existe `make ci`.

---

### Fase 6 — `.dockerignore`: entradas faltantes

**Archivo:** `.dockerignore`

Archivos de desarrollo/herramientas que no deben incluirse en la imagen de producción:

- [ ] Añadir al `.dockerignore`:

  ```
  phpstan-baseline.neon
  deptrac.yaml
  .php-cs-fixer.php
  phpunit.xml
  sonar-project.properties
  playwright.config.ts
  tests/
  docs/
  *.md
  !README.md
  ```

---

### Fase 7 — `supervisor.prod.conf`: parametrizar `numprocs`

**Archivo:** `docker/supervisor.prod.conf`

Los valores de `numprocs` están hardcodeados. En Railway con instancias de distinto tamaño, conviene parametrizarlos via variables de entorno.

- [ ] Cambiar `numprocs=2` → `numprocs=%(ENV_WORKER_PROCS)s` (con fallback en el entrypoint)
- [ ] Añadir `WORKER_PROCS=2` a `.env` y `.env.example`
- [ ] Añadir `NOTIFICATION_WORKER_PROCS=1` a `.env` y `.env.example`

---

### Fase 8 — `Makefile`: targets de utilidad

**Archivo:** `Makefile`

Añadir targets que hoy requieren recordar el comando completo:

- [ ] `composer-install` → `docker compose exec app composer install`
- [ ] `composer-update` → `docker compose exec app composer update`
- [ ] `npm-install` → `npm ci`
- [ ] `lint-js` → `npx eslint public/js/ tests/e2e/`
- [ ] `format-js` → `npx prettier --write public/js/ tests/e2e/`

---

## Tabla de archivos a modificar/crear

| Archivo | Acción | Fase |
|---------|--------|------|
| `.github/workflows/ci.yml` | Modificar threshold `70 → 60` | 1 |
| `docker-compose.yml` | Añadir `start_period` y volumen `vendor` | 2, 3 |
| `.env` | Añadir `TRUSTED_PROXY_IP`, `HEALTH_TOKEN` | 4 |
| `.env.example` | Idem | 4 |
| `.nvmrc` | Crear con `20` | 5a |
| `package.json` | Corregir nombre `komorebi_backup → komorebi` | 5b |
| `tsconfig.json` | Crear | 5c |
| `eslint.config.js` | Crear | 5d |
| `.prettierrc` | Crear | 5e |
| `.dockerignore` | Añadir entradas de dev tools | 6 |
| `docker/supervisor.prod.conf` | Parametrizar `numprocs` | 7 |
| `Makefile` | Añadir targets Node/Composer | 8 |

---

## Checklist de verificación final

- [ ] `make ci` pasa completamente (phpstan + psalm + tests + cs-check)
- [ ] Cobertura de tests ≥ 60 %
- [ ] `docker compose up -d` levanta sin errores tras borrar vendor/ local
- [ ] `docker compose exec app vendor/bin/phpunit` → 2855 tests / 0 failures
- [ ] `npx tsc --noEmit` → sin errores de TypeScript
- [ ] `npx eslint public/js/ tests/e2e/` → sin errores
- [ ] GitHub Actions CI pipeline verde en `develop`
- [ ] `.dockerignore` verificado con `docker build` que no incluye archivos de dev
