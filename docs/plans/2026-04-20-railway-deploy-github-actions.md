# Railway Deploy Completo + GitHub Actions Fix

**Fecha:** 2026-04-20
**Rama destino en Railway:** `main`
**Rama de desarrollo:** `develop`
**Estado:** 🟢 Código completado y verificado — pendiente pasos manuales en Railway/GitHub (Fases 2, 3.1, 3.3, 4)

---

## Contexto

Plan para desplegar Komorebi Café en Railway.app con la rama `main` como rama de producción,
manteniendo `develop` para desarrollo continuo sin romper producción.

Cubre cinco fases:

1. Cambios de código (Railway config + profesionalidad de logs)
2. Configuración del Dashboard de Railway
3. Estrategia de ramas + CI/CD con deploy automático
4. Primer despliegue y verificación
5. Corrección de GitHub Actions que rara vez pasan en verde

---

## FASE 1 — Cambios de código

### 1.1 `railway.json`

- [x] Añadir `"releaseCommand": "php /app/scripts/apply-db.php --force"` — sin esto las migraciones no se aplican al desplegar
- [x] Cambiar `healthcheckTimeout` a `30` si no está ya correcto

**Archivo:** [railway.json](../../railway.json)

---

### 1.2 `railway.toml`

- [x] Añadir `releaseCommand = "php /app/scripts/apply-db.php --force"`

**Archivo:** [railway.toml](../../railway.toml)

---

### 1.3 `public/health.php`

**Bug:** Usa `getenv('DB_HOST')` como única estrategia. El plugin MySQL de Railway puede exponer
solo `MYSQL_URL` si las variables individuales no están mapeadas explícitamente.

- [x] Añadir parser de `MYSQL_URL` como estrategia primaria (igual que `Database::parseDatabaseUrl()`)
- [x] Fallback a variables individuales `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS` si `MYSQL_URL` no existe

**Archivo:** [public/health.php](../../public/health.php)

---

### 1.4 `docker/php/docker-entrypoint.sh`

**Problema:** Usa caracteres Unicode de dibujo de caja (`━━━━━━━━`, `────────────`) que rompen
pipelines de agregación de logs.

- [x] Reemplazar con ASCII plano (`=====`, `-----`)
- [x] Mantener lógica y estructura sin cambios
- [x] `SKIP_MIGRATIONS=1` ya soportado — no tocar

**Archivo:** [docker/php/docker-entrypoint.sh](../../docker/php/docker-entrypoint.sh)

---

### 1.5 `bin/generate-secrets.php`

**Problema:** Usa emojis (`🔐`, `✓`, `⚠`, `✗`, `🚀`) y caracteres de caja (`╔`, `║`, `╚`, `═`)
— inutilizable en CI/CD o terminales sin TTY.

- [x] Reemplazar emojis y caja con `[OK]`, `[WARN]`, `[ERROR]`, cabecera ASCII
- [x] Eliminar `Happy coding! 🚀`
- [x] Mantener colores ANSI (siguen siendo útiles en TTY)

**Archivo:** [bin/generate-secrets.php](../../bin/generate-secrets.php)

---

### 1.6 `.env.railway.example` (archivo nuevo)

- [x] Crear `.env.railway.example` con todas las variables necesarias para Railway
- [x] Documentar cada variable: propósito, de dónde viene (plugin MySQL/Redis o manual), si es secreto generado

Variables a incluir:

```
# === Aplicación ===
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio-railway>.up.railway.app  # asignar tras primer deploy
APP_KEY=<generado con generate-secrets.php>       # secreto

# === Base de datos (plugin MySQL de Railway) ===
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
MYSQL_URL=${{MySQL.MYSQL_URL}}

# === Redis (plugin Redis de Railway) ===
REDIS_HOST=${{Redis.REDISHOST}}
REDIS_PORT=${{Redis.REDISPORT}}
REDIS_PASSWORD=${{Redis.REDISPASSWORD}}
REDIS_URL=${{Redis.REDIS_URL}}

# === Email (Resend SMTP) ===
MAIL_DRIVER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=resend
MAIL_PASSWORD=<API key de Resend>                 # secreto
MAIL_FROM_ADDRESS=noreply@<tu-dominio>
MAIL_FROM_NAME="Komorebi Café"

# === Seguridad ===
DB_SSL_CA=/etc/ssl/certs/ca-certificates.crt     # TLS con MySQL de Railway
APP_HTTPS=true
SESSION_SECURE=true

# === Opcionales ===
SENTRY_DSN=<DSN de Sentry si se usa>
APP_VERSION=1.0.0
SKIP_MIGRATIONS=0                                 # 1 para deshabilitar migraciones en release command
FEATURE_OPS=0
FEATURE_BACKOFFICE=1
FEATURE_KEEPER=0
```

---

## FASE 2 — Configuración Railway Dashboard

Pasos manuales en <https://railway.app/dashboard>:

- [ ] **2.1** Crear proyecto nuevo → "New Project" → "Empty Project"
- [ ] **2.2** Añadir plugin MySQL 8 → "Add a service" → "Database" → "Add MySQL"
- [ ] **2.3** Añadir plugin Redis → "Add a service" → "Database" → "Add Redis"
- [ ] **2.4** Generar secretos localmente: `php bin/generate-secrets.php` (APP_KEY, SESSION_SECRET, CSRF_SECRET, etc.)
- [ ] **2.5** Crear servicio `app`:
  - Source: GitHub repo `Irene-R-Carmona/komorebi`
  - Branch: `main`
  - Dockerfile: `docker/php/Dockerfile.prod`
  - Puerto: `80` (FrankenPHP expone el puerto 80, ver `EXPOSE 80` en Dockerfile.prod)
- [ ] **2.6** Crear servicio `worker-email`:
  - Source: mismo repo, rama `main`
  - Dockerfile: `docker/php/Dockerfile.worker` (imagen `php:8.4-cli-alpine`, distinta de Dockerfile.prod)
  - Start command: `php /app/bin/email-worker.php`
  - Sin puerto expuesto
- [ ] **2.7** Crear servicio `worker-default`:
  - Source: mismo repo, rama `main`
  - Dockerfile: `docker/php/Dockerfile.worker`
  - Start command: `php /app/bin/worker.php default`
  - Sin puerto expuesto
- [ ] **2.8** Configurar variables del servicio `app` (según `.env.railway.example`)
  - Referenciar plugins: `${{MySQL.MYSQLHOST}}`, `${{Redis.REDISHOST}}`, etc.
  - **TELEGRAM_CHAT_ID**: añadir junto a `TELEGRAM_BOT_TOKEN` (ambos necesarios para alertas; si uno está vacío el bot queda silencioso sin error)
  - **CORS_ALLOWED_ORIGINS**: `https://<tu-subdominio>.up.railway.app` (sin esto la API usa `localhost:8080` como origen permitido)
- [ ] **2.9** Copiar variables de `app` a `worker-email` y `worker-default` (mismas vars necesarias)
- [ ] **2.10** En servicio `app`: Settings → Health Check Path → `/health`
- [ ] **2.11** En servicio `app`: Settings → Health Check Timeout → `30s`
- [ ] **2.12** Configurar dominio personalizado o usar el subdominio `.up.railway.app` asignado
- [ ] **2.13** Añadir `APP_URL` con el dominio asignado tras primer deploy
- [ ] **2.14** Verificar en Logs que el entrypoint completa sin errores y las migraciones se aplican

> **Nota:** El notification worker (Telegram) NO se despliega — módulo deshabilitado.

---

## FASE 3 — Estrategia de ramas + deploy automático

### 3.1 Token Railway para GitHub Actions

- [ ] Railway Dashboard → Project Settings → Tokens → "New Token" → nombre: `github-actions`
- [ ] GitHub repo → Settings → Secrets and variables → Actions → "New repository secret"
  - Nombre: `RAILWAY_TOKEN`
  - Valor: el token generado

---

### 3.2 `deploy.yml` — añadir job Railway + actualizar acción

**Archivo:** [.github/workflows/deploy.yml](../../.github/workflows/deploy.yml)

- [x] Añadir job `deploy-railway` que depende de `build`:

```yaml
deploy-railway:
  name: Deploy to Railway
  needs: build
  runs-on: ubuntu-latest
  if: github.ref == 'refs/heads/main' && github.event_name == 'push'
  steps:
    - uses: actions/checkout@v4
    - name: Install Railway CLI
      run: npm install -g @railway/cli@latest
    - name: Deploy to Railway
      run: railway up --service app --detach
      env:
        RAILWAY_TOKEN: ${{ secrets.RAILWAY_TOKEN }}
```

- [x] Actualizar `docker/build-push-action@v5` → `@v6` en el job `build`

---

### 3.3 Protección de rama `main`

Configurar en GitHub → Settings → Branches → "Add branch protection rule" → `main`:

- [ ] "Require a pull request before merging" — activado
- [ ] "Require status checks to pass before merging" — activado
  - Checks requeridos: `quality`, `unit-tests`, `tests` (nombres exactos del `ci.yml`)
- [ ] "Require branches to be up to date before merging" — activado
- [ ] "Do not allow bypassing the above settings" — activado

---

## FASE 4 — Primer despliegue y verificación

- [ ] **4.1** Merge de `develop` → `main` (primer merge tras configurar Railway)
- [ ] **4.2** Verificar en Railway Dashboard que el deploy de `app` llega a estado "Active"
- [ ] **4.3** Verificar logs del release command: `php scripts/apply-db.php --force` aplicó todas las migraciones
- [ ] **4.4** Curl al health endpoint: `curl https://<dominio>/health` — esperar `{"status":"ok"}`
- [ ] **4.5** Verificar workers `worker-email` y `worker-default` en estado "Active"
- [ ] **4.6** Añadir `APP_URL` con el dominio real y redesplegar

---

## FASE 5 — GitHub Actions: corrección de bugs

### 5.1 `ci.yml` — bug crítico: exit code y ruta de cobertura

**Bug 1:** `phpunit ... | tee /tmp/coverage.txt` corre dentro del contenedor Docker.
El step host lee `/tmp/coverage.txt` en la máquina CI → archivo no existe → gate de cobertura siempre se salta silenciosamente.

**Bug 2:** El exit code de la pipe es el de `tee` (siempre 0), no el de phpunit.
Tests fallidos pasan CI como verde.

**Fix:**

```bash
# En lugar de:
docker compose run --rm php-test phpunit ... | tee /tmp/coverage.txt

# Usar (volumen bind-mount ./:/app ya existe, reports/ está en phpunit.xml):
docker compose run --rm php-test sh -c "
  php vendor/bin/phpunit --coverage-text 2>&1 | tee /app/tests/reports/coverage.txt
  PHPUNIT_RC=\${PIPESTATUS[0]}
  cat /app/tests/reports/coverage.txt
  exit \$PHPUNIT_RC
"
```

- [x] Corregir ruta de cobertura a `tests/reports/coverage.txt` (bind-mounted)
- [x] Capturar `${PIPESTATUS[0]}` de phpunit, no de tee
- [x] Eliminar `SKIP_MIGRATIONS=0` (redundante — es el valor por defecto)
- [x] Eliminar emojis `⚠️`, `❌`, `✅` del output

**Archivo:** [.github/workflows/ci.yml](../../.github/workflows/ci.yml)

---

### 5.2 `ci.yml` — job `unit-tests`: reducir extensiones PHP

El job `unit-tests` instala extensiones innecesarias. Solo se necesita `pcntl` para paratest.

- [x] Reducir lista de extensiones a solo `pcntl` en el job `unit-tests`

---

### 5.3 `security-zap.yml` — bug crítico: MySQL nunca arranca

**Bug 1 (crítico):** El `.env` generado en el workflow no incluye `DB_ROOT_PASSWORD` ni `DB_USERNAME`.
MySQL no arranca → la app no arranca → ZAP no tiene target → always fails.

**Fix:** Añadir al bloque de generación de `.env`:

```yaml
DB_ROOT_PASSWORD: 'zap_root_pw'
DB_USERNAME: 'komorebi'
```

**Bug 2 (alto):** Triggers en cada push a `main`/`develop` y cada PR → DAST en cada commit es frágil.
**Fix:** Cambiar a solo `schedule` (domingos 02:00 UTC) + `workflow_dispatch`.

**Bug 3 (alto):** `fail_action: exit` con `.zap/rules.tsv` vacío → bloquea en la primera alerta HIGH sin filtrar falsos positivos.
**Fix:** Cambiar `fail_action: exit` → `fail_action: warn`.

- [x] Añadir `DB_ROOT_PASSWORD` y `DB_USERNAME` al `.env` generado
- [x] Cambiar triggers: solo `schedule` + `workflow_dispatch`
- [x] Cambiar `fail_action: exit` → `fail_action: warn`

**Archivo:** [.github/workflows/security-zap.yml](../../.github/workflows/security-zap.yml)

---

### 5.4 `docker-build.yml` — Trivy falla en CVEs sin patch disponible

**Bug:** `exit-code: "1"` sin `ignore-unfixed: true` → falla en CVEs de la imagen base que no tienen
fix disponible (no es accionable).

- [x] Añadir `ignore-unfixed: true` al step de Trivy

**Archivo:** [.github/workflows/docker-build.yml](../../.github/workflows/docker-build.yml)

---

### 5.5 `sonarqube.yml` — falla sin secretos configurados

**Bug:** Sin condición de guarda → falla si `SONAR_TOKEN`/`SONAR_HOST_URL` no están configurados.

- [x] Añadir `if: secrets.SONAR_TOKEN != ''` al job o step de análisis

**Archivo:** [.github/workflows/sonarqube.yml](../../.github/workflows/sonarqube.yml)

---

### 5.6 `security.yml` — pipe rota en composer audit

**Bug:** `composer audit --format=json | tee` — composer devuelve exit code non-zero cuando hay
vulnerabilidades, rompiendo la pipe antes de que Python lea el JSON.

**Fix:**

```bash
# En lugar de:
composer audit --format=json | tee /tmp/audit.json

# Usar:
composer audit --format=json > /tmp/audit.json 2>&1 || true
# Luego parsear con jq en lugar de Python
```

- [x] Usar `> /tmp/audit.json 2>&1 || true` para capturar output sin romper pipeline
- [x] Reemplazar parsing Python por `jq`
- [x] Eliminar emojis del output

**Archivo:** [.github/workflows/security.yml](../../.github/workflows/security.yml)

---

## Resumen de archivos a modificar

| # | Archivo | Tipo | Cambio principal |
|---|---------|------|-----------------|
| 1 | `railway.json` | Existente | Añadir `releaseCommand`, revisar `healthcheckTimeout` |
| 2 | `railway.toml` | Existente | Añadir `releaseCommand` |
| 3 | `public/health.php` | Existente | Parser `MYSQL_URL` como estrategia primaria |
| 4 | `docker/php/docker-entrypoint.sh` | Existente | Reemplazar Unicode por ASCII |
| 5 | `bin/generate-secrets.php` | Existente | Reemplazar emojis/caja por `[OK]`/`[WARN]`/`[ERROR]` |
| 6 | `.env.railway.example` | **Nuevo** | Todas las variables Railway documentadas |
| 7 | `.github/workflows/ci.yml` | Existente | Fix exit code + ruta cobertura + extensiones + emojis |
| 8 | `.github/workflows/deploy.yml` | Existente | Añadir job `deploy-railway` + actualizar a v6 |
| 9 | `.github/workflows/docker-build.yml` | Existente | Añadir `ignore-unfixed: true` a Trivy |
| 10 | `.github/workflows/security-zap.yml` | Existente | Fix `.env` MySQL + triggers + `fail_action` |
| 11 | `.github/workflows/sonarqube.yml` | Existente | Añadir guard de secretos |
| 12 | `.github/workflows/security.yml` | Existente | Fix pipe composer audit + jq + emojis |

---

## Pasos manuales en GitHub (sin código)

| # | Acción | Dónde |
|---|--------|-------|
| M1 | Generar `RAILWAY_TOKEN` | Railway Dashboard → Project Settings → Tokens |
| M2 | Añadir `RAILWAY_TOKEN` como secret | GitHub repo → Settings → Secrets → Actions |
| M3 | Crear regla de protección para rama `main` | GitHub repo → Settings → Branches |
| M4 | Crear rama `main` si no existe | `git checkout -b main && git push origin main` |

---

## Orden de implementación recomendado

```
1. ci.yml          — mayor impacto: tests falsos-verde eliminados
2. security-zap.yml — más roto: siempre fallaba
3. deploy.yml      — habilita deploy automático a Railway
4. docker-build.yml — simple: una línea
5. sonarqube.yml   — simple: una condición
6. security.yml    — fix pipe + jq
7. railway.json    — releaseCommand
8. railway.toml    — releaseCommand
9. public/health.php — parser MYSQL_URL
10. docker-entrypoint.sh — ASCII
11. generate-secrets.php — ASCII
12. .env.railway.example — nuevo archivo
```
