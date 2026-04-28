# Plan: Implementación de Capas de Testing — Komorebi Café

**Fecha:** 2026-04-23
**Estado:** 🔵 Plan creado — pendiente inicio
**Rama sugerida:** `feat/testing-layers`

---

## Contexto y motivación

El proyecto cuenta con infraestructura de tests parcialmente implementada:

- PHPUnit 13 + paratest configurados (`phpunit.xml`)
- `tests/Support/` con `BaseIntegrationTest`, `DbSeeder`, `ControllerTestCase`
- Playwright 1.58 con specs de accesibilidad en `tests/e2e/`
- CI con 4 jobs (quality, unit-tests, tests, sast)

**Gaps identificados:** sin autenticación persistente en E2E, sin contract tests de la API, sin
Faker para datos dinámicos, sin job E2E en CI, y varios archivos de infraestructura de tests ausentes.

---

## Decisiones de diseño tomadas

| Decisión | Opción elegida | Razón |
|----------|---------------|-------|
| Datos de test | `fakerphp/faker` en `DbSeeder` (solo literales, preservar `SHOW COLUMNS`) | Evita colisiones en paralelo; `SHOW COLUMNS` es intencional |
| Auth E2E | `auth.setup.ts` + `storageState` (patrón Playwright v1.31+) | Patrón oficial; aísla auth setup del test suite |
| OpenAPI validation | `@apidevtools/swagger-parser` + `ajv@8` + `ajv-formats` + `js-yaml` | `ajv` solo no resuelve `$ref` en OAS 3.0 |
| TestDox audit | `make testdox-audit` con grep en pipeline | Más simple que script PHP ad-hoc |
| Cobertura mínima | 70% (ya configurado en CI) | Umbral actual del proyecto |
| Playwright en local | `docker compose exec -e BASE_URL=http://localhost app npx playwright test` | Corrige acceso a puerto correcto (Caddy escucha en `:80` dentro del contenedor; el mapping `8080:80` solo existe en el host) |
| Playwright en CI | `npx playwright test` en runner de ubuntu-latest (no via `docker exec`) | Playwright en host puede usar el stack Docker levantado + webServer idempotente |

---

## Correcciones verificadas contra el código

> Estas correcciones surgen de auditar el código real antes de escribir el plan.

| Item | Valor incorrecto (asumido) | Valor correcto (verificado) |
|------|---------------------------|----------------------------|
| Newsletter HTTP status | 201 Created | **200 OK** (openapi.yaml L618) |
| Path alérgenos | `/api/v1/allergens` | **`/api/v1/menu/alergenos`** (openapi.yaml L229) |
| `.gitignore` | `tests/e2e/.auth/` presente | **Ausente** — hay `auth.json` pero no el directorio |
| OpenAPI validator | `ajv` + plugins | **`@apidevtools/swagger-parser` + `ajv@8` + `ajv-formats`** |
| Auth Playwright | `test.extend` | **`auth.setup.ts`** + `storageState` (patrón oficial v1.31+) |
| `make e2e` target | `docker compose exec app npx playwright test` | **`docker compose exec -e BASE_URL=http://localhost app npx playwright test`** |
| `/api/v1/cafes` en spec | Documentado en openapi.yaml | **NO existe en el spec** — solo en routes.php |
| `POST /reservations/create` (spec) | Coincide con routes.php | **routes.php usa `POST /reservations`** — mismatch spec/impl |

---

## Fase 1 — Fundamentos de datos de test

**Objetivo:** Eliminar literales hardcodeados en `DbSeeder` usando Faker.

### 1.1 Añadir fakerphp/faker

```bash
docker compose exec app composer require --dev fakerphp/faker
```

**Test previo:**

```php
// tests/Unit/Support/DbSeederFakerTest.php
// Verifica que ensureUser() genera email único por llamada
```

### 1.2 Actualizar DbSeeder con Faker

Archivo: `tests/Support/DbSeeder.php`

- Inyectar `\Faker\Generator` via constructor o factory estático
- Reemplazar **únicamente** los literales de string:
  - `'Test Cafe'` → `$faker->company()`
  - `'test-user@example.com'` → `$faker->unique()->safeEmail()`
  - Nombres hardcodeados → `$faker->name()`
- **NO modificar** la lógica `SHOW COLUMNS` — es intencional para introspección dinámica

**Criterio de done:** `make test-unit` verde tras el cambio.

---

## Fase 2 — Infraestructura de autenticación E2E

**Objetivo:** Autenticación persistente en tests E2E con `storageState`.

### 2.1 Corregir `.gitignore`

Añadir al `.gitignore`:

```
tests/e2e/.auth/
```

> **Nota:** `auth.json` ya está en `.gitignore` (L92), pero NO cubre `tests/e2e/.auth/*.json`.

### 2.2 Crear `tests/e2e/auth.setup.ts`

```typescript
import { test as setup } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '.auth/user.json');

setup('authenticate as user', async ({ page }) => {
  await page.goto('/login');
  await page.fill('[name="email"]', process.env.E2E_USER_EMAIL ?? 'test@komorebi.test');
  await page.fill('[name="password"]', process.env.E2E_USER_PASSWORD ?? 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL('/');
  await page.context().storageState({ path: authFile });
});
```

### 2.3 Actualizar `playwright.config.ts`

```typescript
import { defineConfig, devices } from '@playwright/test';
import path from 'path';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['html', { outputFolder: 'tests/reports/playwright' }],
    ['junit', { outputFile: 'tests/reports/junit.xml' }],
  ],

  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    // Setup project: se ejecuta primero, sin dependencia de auth
    {
      name: 'auth-setup',
      testMatch: /auth\.setup\.ts/,
    },
    // Browsers principales — dependen del setup de auth
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: path.join(__dirname, 'tests/e2e/.auth/user.json'),
      },
      dependencies: ['auth-setup'],
    },
    // En CI solo Chromium para mayor velocidad
    ...(process.env.CI ? [] : [
      {
        name: 'firefox',
        use: {
          ...devices['Desktop Firefox'],
          storageState: path.join(__dirname, 'tests/e2e/.auth/user.json'),
        },
        dependencies: ['auth-setup'],
      },
      {
        name: 'webkit',
        use: {
          ...devices['Desktop Safari'],
          storageState: path.join(__dirname, 'tests/e2e/.auth/user.json'),
        },
        dependencies: ['auth-setup'],
      },
    ]),
  ],

  webServer: {
    command: 'docker compose up -d',
    url: 'http://localhost:8080/health.php',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
});
```

> **Nota sobre puertos:** `baseURL: 'http://localhost:8080'` es correcto para el HOST (mapping `8080:80`).
> Cuando se ejecuta Playwright DENTRO del contenedor (local dev), usar `BASE_URL=http://localhost`.

### 2.4 Actualizar spec de accesibilidad

Archivo: `tests/e2e/accessibility/dashboards.spec.ts`

- Eliminar el comentario `// TODO: auth not implemented`
- El `storageState` del proyecto `chromium` provee la sesión automáticamente

### 2.5 Crear directorio `.auth/` con `.gitkeep`

```bash
mkdir tests/e2e/.auth
echo "" > tests/e2e/.auth/.gitkeep
```

---

## Fase 3 — Contract tests de la API

**Objetivo:** Validar que los endpoints de la API cumplen el contrato definido en `docs/openapi.yaml`.

> **Scope:** Solo endpoints documentados en `docs/openapi.yaml`. Los endpoints `/api/v1/cafes` y
> `/api/v1/cafes/{slug}` existen en `routes.php` pero **NO están documentados en el spec** — quedan
> fuera del scope de contract testing hasta que se añadan al spec.

> **Discrepancia detectada:** `docs/openapi.yaml` define `POST /reservations/create` pero
> `routes.php` implementa `POST /api/v1/reservations`. Los contract tests deben usar la ruta real.
> Esta discrepancia debe resolverse actualizando el spec.

### 3.1 Instalar dependencias de validación OpenAPI

```bash
npm install --save-dev @apidevtools/swagger-parser ajv@8 ajv-formats js-yaml
```

### 3.2 Crear `tests/api/contract.test.ts`

```typescript
import SwaggerParser from '@apidevtools/swagger-parser';
import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import * as yaml from 'js-yaml';
import * as fs from 'fs';

// Carga y valida el spec OpenAPI (resuelve $ref automáticamente)
const spec = await SwaggerParser.dereference('docs/openapi.yaml');
const ajv = new Ajv({ allErrors: true });
addFormats(ajv);

describe('API Contract Tests — endpoints documentados en openapi.yaml', () => {
  const BASE = process.env.BASE_URL ?? 'http://localhost:8080';

  test('GET /api/v1/menu/alergenos responde 200 con schema correcto', async () => {
    const res = await fetch(`${BASE}/api/v1/menu/alergenos`);
    expect(res.status).toBe(200);
    const body = await res.json();
    // Valida con schema del spec
    const schema = spec.paths['/menu/alergenos'].get.responses['200'].content['application/json'].schema;
    const valid = ajv.validate(schema, body);
    expect(valid).toBe(true);
  });

  test('POST /api/v1/newsletter/subscribe responde 200', async () => {
    const res = await fetch(`${BASE}/api/v1/newsletter/subscribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: `test-${Date.now()}@example.com` }),
    });
    expect(res.status).toBe(200); // El spec define 200, NO 201
  });

  test('GET /api/v1/time-slots/available responde 200', async () => {
    const res = await fetch(`${BASE}/api/v1/time-slots/available?date=${new Date().toISOString().split('T')[0]}`);
    expect(res.status).toBe(200);
  });

  test('GET /api/v1/holidays responde 200', async () => {
    const res = await fetch(`${BASE}/api/v1/holidays`);
    expect(res.status).toBe(200);
  });
});
```

### 3.3 Añadir script en `package.json`

```json
{
  "scripts": {
    "test:contract": "npx tsx --test tests/api/contract.test.ts"
  }
}
```

---

## Fase 4 — Flujos E2E de usuario

**Objetivo:** Tests E2E de los flujos críticos del negocio.

> **Prerequisito:** Fase 2 completada (auth con `storageState`).

### 4.1 Test de flujo de reserva

Archivo: `tests/e2e/flows/reservation-flow.spec.ts`

```typescript
import { test, expect } from '@playwright/test';

// storageState inyectado via playwright.config.ts (usuario autenticado)
test('usuario puede completar una reserva', async ({ page }) => {
  await page.goto('/reservar');
  // Seleccionar fecha disponible
  await page.click('[data-testid="date-picker"]');
  // Continuar con el flujo...
  await expect(page).toHaveURL(/reservas\/confirmacion/);
});
```

### 4.2 Test de flujo de newsletter

Archivo: `tests/e2e/flows/newsletter-flow.spec.ts`

```typescript
import { test, expect } from '@playwright/test';

test('visitante puede suscribirse al newsletter', async ({ page }) => {
  await page.goto('/');
  await page.fill('[name="email"]', `playwright-${Date.now()}@example.com`);
  await page.click('[data-testid="newsletter-submit"]');
  await expect(page.locator('[data-testid="newsletter-success"]')).toBeVisible();
});
```

### 4.3 Test de flujo de login/logout

Archivo: `tests/e2e/flows/auth-flow.spec.ts`

```typescript
import { test, expect } from '@playwright/test';

// Este test NO usa storageState — verifica el flujo de auth desde cero
test.use({ storageState: undefined });

test('usuario puede hacer login y logout', async ({ page }) => {
  await page.goto('/login');
  await page.fill('[name="email"]', process.env.E2E_USER_EMAIL ?? 'test@komorebi.test');
  await page.fill('[name="password"]', process.env.E2E_USER_PASSWORD ?? 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL('/');
  // Logout
  await page.click('[data-testid="logout-btn"]');
  await expect(page).toHaveURL('/login');
});
```

---

## Fase 5 — Infraestructura de calidad y CI

**Objetivo:** Integrar todos los tests en el pipeline CI y añadir herramientas de auditoría.

### 5.1 Corregir `make e2e` en Makefile

Problema actual: `docker compose exec app npx playwright test` usa `localhost:8080` dentro del
contenedor, pero Caddy escucha en `:80` (el mapping `8080:80` solo existe en el host).

```makefile
# Reemplazar el target existente:
e2e: ## Ejecutar tests end-to-end con Playwright (requiere stack corriendo)
 docker compose exec -e BASE_URL=http://localhost app npx playwright test

e2e-a11y: ## Ejecutar solo tests de accesibilidad (WCAG 2.1 AA)
 docker compose exec -e BASE_URL=http://localhost app npx playwright test tests/e2e/accessibility/
```

### 5.2 Añadir `make testdox-audit`

```makefile
testdox-audit: ## Auditar cobertura de escenarios en TestDox output
 docker compose exec app vendor/bin/phpunit --testdox --no-coverage 2>&1 \
  | grep -E "^\s+(✔|✘|↩)" \
  | sort \
  | uniq -c \
  | sort -rn
```

### 5.3 Añadir job E2E en `.github/workflows/ci.yml`

Añadir después del job `sast`:

```yaml
  # --------------------------------------------------------------------------
  # Job 5: Tests E2E con Playwright (solo Chromium en CI)
  # --------------------------------------------------------------------------
  e2e:
    name: E2E Tests (Playwright)
    runs-on: ubuntu-latest
    timeout-minutes: 20
    needs: [tests]

    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install Node dependencies
        run: npm ci

      - name: Install Playwright browsers
        run: npx playwright install --with-deps chromium

      - name: Start Docker stack
        run: |
          docker compose up -d
          # Esperar health check
          timeout 60 bash -c 'until curl -sf http://localhost:8080/health.php; do sleep 2; done'

      - name: Run E2E tests (Chromium only)
        run: npx playwright test --project=chromium
        env:
          CI: true
          BASE_URL: http://localhost:8080
          E2E_USER_EMAIL: ${{ secrets.E2E_USER_EMAIL }}
          E2E_USER_PASSWORD: ${{ secrets.E2E_USER_PASSWORD }}

      - name: Upload Playwright report
        uses: actions/upload-artifact@v4
        if: always()
        with:
          name: playwright-report
          path: tests/reports/playwright/

      - name: Stop Docker stack
        if: always()
        run: docker compose down -v --remove-orphans
```

> **Nota:** En CI, Playwright corre en el runner ubuntu-latest (no via `docker exec`). El webServer
> de `playwright.config.ts` ejecuta `docker compose up -d` — es idempotente si el stack ya está
> levantado. `reuseExistingServer: !process.env.CI` = `false` en CI, por lo que el webServer
> command se ejecuta siempre (sin problemas porque `docker compose up -d` es idempotente).

### 5.4 Añadir secrets en GitHub

Configurar en `Settings → Secrets and variables → Actions`:

- `E2E_USER_EMAIL` — email de usuario de prueba en la DB de CI
- `E2E_USER_PASSWORD` — contraseña correspondiente

### 5.5 Seeder de usuario E2E en CI

El usuario E2E debe existir antes de que corra `auth.setup.ts`. Añadir al script de migración/seed
de CI o al `DbSeeder`:

```php
// En DbSeeder::ensureE2eUser() (nuevo método)
public function ensureE2eUser(): void
{
    $email = \getenv('E2E_USER_EMAIL') ?: 'test@komorebi.test';
    $password = \getenv('E2E_USER_PASSWORD') ?: 'password123';
    // INSERT INTO users con password_hash($password, PASSWORD_BCRYPT)
    // ...
}
```

---

## Orden de ejecución

```
Fase 1 (Faker en DbSeeder)
    ↓
Fase 2 (Auth E2E + playwright.config.ts)
    ↓
Fase 3 (Contract tests)   ←── independiente de Fase 4
Fase 4 (Flujos E2E)       ←── depende de Fase 2
    ↓
Fase 5 (CI + Makefile)    ←── consolida todo
```

---

## Checklist de verificación antes de marcar completado

- [ ] `make test-unit` verde con DbSeeder usando Faker
- [ ] `make test-integration` verde
- [ ] `make e2e-a11y` ejecuta sin errores con `BASE_URL=http://localhost`
- [ ] `make e2e` ejecuta flujos completos con auth persistente
- [ ] `npx tsx tests/api/contract.test.ts` valida endpoints documentados
- [ ] PR de CI muestra 5 jobs: quality, unit-tests, tests, sast, e2e
- [ ] Cobertura ≥ 70% mantenida
- [ ] PHPStan level 5 sin nuevos errores
- [ ] `tests/e2e/.auth/` añadido a `.gitignore`

---

## Notas de seguimiento

### Deuda técnica identificada (fuera de scope de este plan)

1. **Spec/impl mismatch en reservations:** `docs/openapi.yaml` define `POST /reservations/create`
   pero `routes.php` implementa `POST /api/v1/reservations`. Crear issue para alinear el spec.

2. **`/api/v1/cafes` no documentado:** Las rutas `GET /api/v1/cafes` y `GET /api/v1/cafes/{slug}`
   existen en `routes.php` pero no tienen entrada en `docs/openapi.yaml`. Documentarlas.

3. **`make e2e` en Makefile:** El target original usaba `docker compose exec app npx playwright test`
   sin `BASE_URL`, lo que fallaría porque Caddy escucha en `:80` dentro del contenedor. Corregido
   en este plan con `-e BASE_URL=http://localhost`.
