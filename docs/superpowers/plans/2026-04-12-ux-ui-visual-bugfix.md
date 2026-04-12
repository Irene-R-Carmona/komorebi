# UX/UI Visual Bugfix — Komorebi Café

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir los 19 bugs visuales y funcionales documentados en `docs/UX_UI_VISUAL_REPORT.md` v2.0, diseñando cada componente visual en Figma siguiendo las Laws of UX antes de implementarlo en código.

**Architecture:** Cada tarea con cambios de UI sigue el ciclo **Figma → Laws of UX review → código**. Las tareas puramente funcionales (JS, PHP) van directo a TDD. El CSS público usa `design-tokens.css` con variables `--color-primario: #5C3D2E`, `--color-acento: #C9A959`, `--color-fondo: #F7F3EB`. El CSS de backoffice usa `backoffice.css` con variables Bootstrap 5 extendidas.

**Tech Stack:** PHP 8.4 MVC custom · Bootstrap 5 · Bootstrap Icons · Alpine.js v3 · Docker · PHPUnit · PHPStan L5 · Figma MCP (`mcp_figma_*`) · Context7 Alpine MCP (`mcp_io_github_ups_*`)

---

## Bugs Excluidos por Diseño Intencional

| Bug | Motivo |
|-----|--------|
| BUG-8 (¥ moneda) | Intencional. `tools/data-viewer.php` documenta "Yen japonés (¥)". Diseño japonés del café. |
| BUG-9 (KPI sin datos) | Datos de seed insuficientes, no error de display. Resolver cuando haya datos reales. |
| BUG-12 (sidebar slate) | P3 backlog. Sin impacto funcional. |
| BUG-14 (botones Bootstrap) | P3 backlog. Cosmético sin accesibilidad comprometida. |

---

## Mapa de Archivos

| Archivo | Modificación |
|---------|-------------|
| `public/css/sections/loyalty.css` | Reemplazar paleta violeta/índigo por tokens de marca |
| `resources/views/public/loyalty/card.php` | Verificar iconos (posible falso positivo) |
| `resources/views/manager/reservations/index.php` | Eliminar bloque CSS huérfano al final |
| `resources/views/reception/index.php` | Añadir carga de `reception.js` |
| `app/routes.php` | Reescribir `setNotFoundHandler` con layout real |
| `resources/views/errors/404.php` | Verificar que el template parcial es correcto |
| `app/Services/NavigationService.php` | Ampliar `getKeeperMenu()` de 1 a 4 items |
| `app/Http/Controllers/Keeper/AnimalIncidentController.php` | Pasar lista de animales a la vista `create` |
| `resources/views/backoffice/keeper/incidents/create.php` | Reemplazar `input[number]` por `<select>` |
| `resources/views/public/cafes/index.php` | Añadir `onerror` placeholder en imágenes |
| `public/images/ui/placeholder-cafe.svg` | Crear (nuevo) |
| `resources/views/backoffice/keeper/dashboard.php` | Añadir `onerror` + css overflow fix |
| `resources/views/backoffice/keeper/animals/index.php` | Fix nombre duplicado en thumbnail |
| `resources/views/backoffice/keeper/animals/show.php` | Añadir rama `else` con placeholder |
| `public/images/ui/placeholder-animal.svg` | Crear (nuevo) |
| `resources/views/backoffice/keeper/health-checks/create.php` | Unificar color del header "Notas Adicionales" |
| `resources/views/manager/reservations/index.php` | Verificar aplicación de `$statusLabels` |
| `public/js/components/newsletterPopup.js` | Guard localStorage + delay 15s |
| Seeds con `lh3.googleusercontent.com` | Reemplazar URLs externas |

---

## FASE 1 — P0 Crítico (Ejecución prioritaria)

---

### T1: loyalty.css — Paleta violeta → tokens de marca [BUG-1, BUG-13]

> **Law of UX aplicable:** *Aesthetic-Usability Effect* — interfaces visualmente coherentes con la marca se perciben como más usables y confiables. La paleta violeta rompe la consistencia y daña la confianza subconsciente del usuario.
> **Workflow:** Diseñar en Figma → validar Laws of UX → implementar.

**Archivos:**

- Modificar: `public/css/sections/loyalty.css` (L41, L276, L306, L313, L493, L548 + sombras)

**Paleta de reemplazo:**

- Gradiente principal: `#5C3D2E` → `#2D1F14` (café oscuro profundo)
- Gradiente énfasis: `#5C3D2E` → `#C9A959` (café a ámbar)
- Texto acento: `var(--color-acento, #C9A959)`
- Sombras: `rgba(92, 61, 46, ...)` (en lugar de `rgba(102, 126, 234, ...)`)

- [ ] **Paso 1: Abrir Figma y diseñar la loyalty card con la paleta correcta**

  Usando la skill `figma-generate-design` + `figma-use`, crear en FigJam/Figma el componente `LoyaltyCard` con:
  - Fondo: gradiente `#5C3D2E → #2D1F14`
  - Texto primario: `#F7F3EB` (sobre fondo oscuro — ratio WCAG AA ≥ 4.5:1)
  - Sello/rewards: gradiente `#5C3D2E → #C9A959`
  - Sombra: `0 8px 32px rgba(92, 61, 46, 0.4)`

  Validar contra Laws of UX en Figma antes de continuar:
  - ✓ Aesthetic-Usability Effect: ¿la paleta "huele" a café japonés?
  - ✓ Contrast WCAG AA: texto claro sobre fondo oscuro
  - ✓ Jakob's Law: gradiente de izquierda a derecha, dirección habitual

- [ ] **Paso 2: Capturar screenshot de la tarjeta diseñada en Figma**

  Usar `mcp_figma_get_screenshot` para obtener la vista previa y confirmar visualmente.

- [ ] **Paso 3: Leer el estado actual del CSS**

  ```bash
  docker compose exec app grep -n "667eea\|764ba2\|6366f1\|8b5cf6\|rgba(102" public/css/sections/loyalty.css
  ```

  Salida esperada: 6+ líneas con los colores violeta.

- [ ] **Paso 4: Reemplazar paleta con `multi_replace_string_in_file`**

  Hacer todos los reemplazos de una vez:

  **L41** — `.loyalty-card` fondo:

  ```css
  /* antes */
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  /* después */
  background: linear-gradient(135deg, #5C3D2E 0%, #2D1F14 100%);
  ```

  **L276** — `.reward-card__cost` color texto:

  ```css
  /* antes */
  color: #667eea;
  /* después */
  color: var(--color-acento, #C9A959);
  ```

  **L306** — gradiente énfasis variante A:

  ```css
  /* antes */
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  /* después */
  background: linear-gradient(135deg, #5C3D2E, #C9A959);
  ```

  **L313** — gradiente énfasis variante B:

  ```css
  /* antes */
  background: linear-gradient(135deg, #667eea, #764ba2);
  /* después */
  background: linear-gradient(135deg, #5C3D2E, #C9A959);
  ```

  **L493** — fondo secundario:

  ```css
  /* antes */
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  /* después */
  background: linear-gradient(135deg, #5C3D2E 0%, #2D1F14 100%);
  ```

  **L548** — fondo terciario:

  ```css
  /* antes */
  background: linear-gradient(135deg, #667eea, #764ba2);
  /* después */
  background: linear-gradient(135deg, #5C3D2E, #C9A959);
  ```

  **Sombras** — reemplazar `rgba(102, 126, 234,` → `rgba(92, 61, 46,`:

  ```css
  /* antes */
  box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4);
  /* después */
  box-shadow: 0 8px 32px rgba(92, 61, 46, 0.4);
  ```

- [ ] **Paso 5: Verificar que no queda rastro de la paleta violeta**

  ```bash
  docker compose exec app grep -n "667eea\|764ba2\|6366f1\|8b5cf6" public/css/sections/loyalty.css
  ```

  Salida esperada: ninguna línea.

- [ ] **Paso 6: Capturar screenshot de la vista real y comparar con Figma**

  Navegar a `http://localhost:8080/loyalty` y capturar screenshot.
  Comparar visualmente con el diseño Figma del Paso 1.

- [ ] **Paso 7: Commit**

  ```bash
  git add public/css/sections/loyalty.css
  git commit -m "fix(css): reemplazar paleta violeta/índigo por tokens de marca en loyalty card [BUG-1, BUG-13]"
  ```

---

### T2: loyalty — Verificar iconos Font Awesome [BUG-2]

**Archivos:**

- Leer: `resources/views/public/loyalty/card.php`
- Leer: seeders con tabla `rewards` o `loyalty_rewards`

- [ ] **Paso 1: Buscar clases FA en la vista de loyalty**

  ```bash
  docker compose exec app grep -n "fa-\|fas \|far " resources/views/public/loyalty/card.php
  ```

- [ ] **Paso 2: Buscar campo `icon` en seeders**

  ```bash
  docker compose exec app grep -rn "fa-\|fas \|far " migrations/ | grep -i "icon\|reward"
  ```

- [ ] **Paso 3: evaluar resultado**

  - Si no hay clases `fa-`: BUG-2 es falso positivo. Documentar en `docs/UX_UI_VISUAL_REPORT.md` como "Cerrado — Falso Positivo". No hay cambio de código.
  - Si hay clases `fa-` en seeders: los iconos de rewards almacenados en BD usan FA. Añadir `<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">` en `resources/views/layouts/main.php` dentro del bloque `<head>`. Antes de añadir, verificar política CSP en `bootstrap/container.php`.

- [ ] **Paso 4: Commit (solo si hubo cambio)**

  ```bash
  git add resources/views/layouts/main.php
  git commit -m "fix(layout): añadir Font Awesome para iconos de loyalty rewards [BUG-2]"
  ```

---

### T3: manager/reservations — Eliminar bloque CSS huérfano [BUG-3]

**Archivos:**

- Modificar: `resources/views/manager/reservations/index.php` (últimas ~4 líneas)

- [ ] **Paso 1: Verificar el bloque huérfano exacto**

  ```bash
  docker compose exec app tail -10 resources/views/manager/reservations/index.php
  ```

  Salida esperada (aproximada):

  ```
  </div>
  .btn--primary:hover {
      background: var(--primary-700, #1d4ed8);
  }
  </style>
  ```

- [ ] **Paso 2: Eliminar el bloque con `replace_string_in_file`**

  Eliminar todo desde `.btn--primary:hover {` hasta el `</style>` que cierra ese bloque sin apertura correspondiente.

- [ ] **Paso 3: Verificar que el archivo cierra correctamente**

  ```bash
  docker compose exec app tail -5 resources/views/manager/reservations/index.php
  ```

  El archivo debe terminar con `</div>` o equivalente de cierre de plantilla PHP, sin CSS flotante.

- [ ] **Paso 4: Cargar la página y verificar que el CSS no aparece como texto**

  Navegar a `http://localhost:8080/manager/reservations` y confirmar que la página se ve correctamente sin texto CSS visible.

- [ ] **Paso 5: Commit**

  ```bash
  git add resources/views/manager/reservations/index.php
  git commit -m "fix(view): eliminar bloque CSS huérfano en manager/reservations [BUG-3]"
  ```

---

### T4: reception/index.php — Cargar alpine component de recepción [BUG-15]

> **Causa raíz:** `public/js/init/alpine-components.js` registra `receptionApp: emptyComponent` como fallback. Si `public/js/sections/reception.js` no se carga en la página, Alpine usa el componente vacío y el modal de check-in no funciona.

**Archivos:**

- Leer: `resources/views/layouts/backoffice.php` (verificar mecanismo de slot de scripts)
- Modificar: `resources/views/reception/index.php`

- [ ] **Paso 1: Verificar mecanismo de scripts extra en el layout backoffice**

  ```bash
  docker compose exec app grep -n "extraScripts\|scripts\|yield\|section" resources/views/layouts/backoffice.php
  ```

  Si el layout tiene un slot tipo `<?= $extraScripts ?? '' ?>`, usarlo.
  Si no, añadir el `<script>` directamente en la vista antes de cerrar el body.

- [ ] **Paso 2: Leer las primeras y últimas líneas de reception/index.php**

  ```bash
  docker compose exec app head -10 resources/views/reception/index.php
  docker compose exec app tail -10 resources/views/reception/index.php
  ```

- [ ] **Paso 3: Añadir carga de reception.js en la vista**

  Si el layout tiene slot `extraScripts`, añadir al inicio de `reception/index.php`:

  ```php
  <?php $extraScripts = '<script src="/js/sections/reception.js"></script>'; ?>
  ```

  Si el layout NO tiene slot, añadir antes del cierre justo del bloque principal:

  ```html
  <script src="/js/sections/reception.js"></script>
  ```

  El script debe cargarse ANTES de que Alpine inicie (el `defer` de `alpine.min.js` en el layout garantiza el orden si el script no lleva `defer`).

- [ ] **Paso 4: Verificar que Alpine inicializa correctamente**

  Navegar a `http://localhost:8080/reception`.
  Abrir DevTools → Console: no debe haber errores `TypeError: ... is not a function` o `receptionApp is not defined`.
  Hacer click en un botón de check-in y confirmar que el modal se abre (`checkinOpen` cambia a `true`).

- [ ] **Paso 5: Commit**

  ```bash
  git add resources/views/reception/index.php
  git commit -m "fix(reception): cargar reception.js para Alpine receptionApp modal check-in [BUG-15]"
  ```

---

## FASE 2 — P1 Alto

---

### T5: 404 — Usar layout real con CSS [BUG-4]

> **Law of UX aplicable:** *Law of Uniform Connectedness* — la página de error debe pertenecer visualmente al mismo sistema que el resto del sitio. Una página de error sin estilos rompe la percepción de coherencia y puede aumentar la tasa de abandono.
> **Workflow:** Diseñar en Figma → validar Laws of UX → implementar.

**Archivos:**

- Modificar: `app/routes.php` (método `setNotFoundHandler`, ~L468)
- Leer: `resources/views/errors/404.php` (verificar contenido del partial)
- Leer: `app/Core/View.php` (verificar firma de `View::render`)

- [ ] **Paso 1: Diseñar la página 404 en Figma**

  Usando `figma-generate-design` skill + `figma-use`, crear en Figma el componente `Error404`:
  - Mantener el header/nav del layout `main` (Law of Uniform Connectedness)
  - Mensaje claro y accionable: "Página no encontrada" + botón "Volver al inicio"
  - Ilustración minimalista opcional (gato o café ☕ temático)
  - Validar contra Laws of UX:
    - ✓ Fitts's Law: botón CTA grande y centrado (fácil de clicar)
    - ✓ Law of Uniform Connectedness: misma paleta y tipografía que el sitio
    - ✓ Von Restorff Effect: el mensaje de error destacado visualmente

- [ ] **Paso 2: Verificar el partial actual**

  ```bash
  docker compose exec app cat resources/views/errors/404.php
  ```

- [ ] **Paso 3: Verificar la firma de `View::render`**

  ```bash
  docker compose exec app grep -n "static function render\|public static" app/Core/View.php | head -5
  ```

- [ ] **Paso 4: Leer el handler actual en routes.php**

  ```bash
  docker compose exec app grep -n "setNotFoundHandler\|NotFound\|404" app/routes.php
  ```

- [ ] **Paso 5: Reescribir el handler con layout real**

  En `app/routes.php`, reemplazar el handler actual (que retorna HTML crudo) por:

  ```php
  $router->setNotFoundHandler(function () use ($responseFactory): \Psr\Http\Message\ResponseInterface {
      ob_start();
      \App\Core\View::render('errors/404', [
          'title'   => 'Página no encontrada',
          'message' => 'Lo sentimos, la página que buscas no existe.',
      ], [], 'main');
      $html = ob_get_clean();
      return $responseFactory->html($html ?? '', 404);
  });
  ```

- [ ] **Paso 6: Verificar que el partial 404 usa los parámetros correctos**

  El partial `resources/views/errors/404.php` debe hacer referencia a `$title` y `$message`.
  Si no los tiene, actualizar el partial para usarlos.

- [ ] **Paso 7: Probar el 404**

  Navegar a `http://localhost:8080/esta-pagina-no-existe`.
  Debe mostrar la página con header/nav/footer del layout `main` y el mensaje de error.

- [ ] **Paso 8: Commit**

  ```bash
  git add app/routes.php resources/views/errors/404.php
  git commit -m "fix(routing): página 404 con layout main y CSS del sitio [BUG-4]"
  ```

---

### T6: NavigationService — Navegación lateral keeper completa [BUG-16]

**Archivos:**

- Modificar: `app/Services/NavigationService.php` (método `getKeeperMenu()`)

- [ ] **Paso 1: Leer el método actual y la firma de `self::item()`**

  ```bash
  docker compose exec app grep -n "getKeeperMenu\|function item\|self::item" app/Services/NavigationService.php | head -20
  ```

- [ ] **Paso 2: Verificar las rutas keeper existentes**

  ```bash
  docker compose exec app grep -n "keeper/" app/routes.php | grep "get\b"
  ```

  Confirmar que existen: `/keeper/dashboard`, `/keeper/animals`, `/keeper/health-checks`, `/keeper/incidents`.

- [ ] **Paso 3: Verificar disponibilidad de iconos Bootstrap Icons usados en otros menús**

  ```bash
  docker compose exec app grep -n "bi-" app/Services/NavigationService.php | head -10
  ```

- [ ] **Paso 4: Ampliar `getKeeperMenu()`**

  Reemplazar el cuerpo actual del método (1 item) por:

  ```php
  private static function getKeeperMenu(): array
  {
      return [
          self::item('bi-speedometer2',    'Estado Diario',      '/keeper/dashboard'),
          self::item('bi-heart-pulse',     'Animales',           '/keeper/animals'),
          self::item('bi-clipboard-pulse', 'Chequeos de Salud',  '/keeper/health-checks'),
          self::item('bi-exclamation-triangle', 'Incidentes',    '/keeper/incidents'),
      ];
  }
  ```

- [ ] **Paso 5: Verificar la navegación visual**

  Navegar a `http://localhost:8080/keeper/dashboard`.
  La barra lateral debe mostrar los 4 items con sus iconos.

- [ ] **Paso 6: Commit**

  ```bash
  git add app/Services/NavigationService.php
  git commit -m "fix(nav): ampliar keeper sidebar con Animales, Chequeos e Incidentes [BUG-16]"
  ```

---

### T7: Keeper incidents — Select de animales [BUG-17]

> **Law of UX aplicable:** *Recognition over Recall* (Nielsen) — el usuario no debe recordar un ID numérico. Un `<select>` con nombres reales reduce la carga cognitiva y los errores de entrada.

**Archivos:**

- Modificar: `app/Http/Controllers/Keeper/AnimalIncidentController.php` (método `create()`)
- Modificar: `resources/views/backoffice/keeper/incidents/create.php`
- Leer: `app/Repositories/AnimalRepository.php` (confirmar método `findActiveByCafe`)

- [ ] **Paso 1: Diseñar el formulario de incidente en Figma**

  Usando `figma-generate-design` + `figma-use`, crear el componente `IncidentForm`:
  - Campo `<select>` con nombre del animal visible (no ID)
  - Label claro: "Animal afectado"
  - Placeholder deshabilitado: "— Selecciona un animal —"
  - Validar Laws of UX:
    - ✓ Recognition over Recall: nombres, no IDs
    - ✓ Law of Proximity: label junto al control
    - ✓ Aesthetic-Usability Effect: coherente con el resto del formulario

- [ ] **Paso 2: Verificar la firma de `findActiveByCafe`**

  ```bash
  docker compose exec app grep -n "findActiveByCafe\|function findActive" app/Repositories/AnimalRepository.php
  ```

  Esperado: `public function findActiveByCafe(int $cafeId): array`

- [ ] **Paso 3: Verificar cómo se obtiene `$cafeId` en otros controllers del keeper**

  ```bash
  docker compose exec app grep -n "cafeId\|cafe_id\|SESSION.*cafe\|session.*cafe" app/Http/Controllers/Keeper/ -r | head -10
  ```

- [ ] **Paso 4: Actualizar el controller**

  En `app/Http/Controllers/Keeper/AnimalIncidentController.php`, inyectar `AnimalRepository` y modificar `create()`:

  ```php
  public function create(ServerRequestInterface $request): ?ResponseInterface
  {
      $cafeId = (int) ($_SESSION['cafe_id'] ?? 0);
      $animals = $this->animalRepository->findActiveByCafe($cafeId);

      View::render('backoffice/keeper/incidents/create', [
          'animals' => $animals,
      ], [], 'backoffice');
      return null;
  }
  ```

  Verificar que `AnimalRepository` se inyecta en el constructor y añadirlo si no está:

  ```php
  public function __construct(
      private readonly ResponseFactory $response,
      private readonly AnimalRepository $animalRepository,
  ) {}
  ```

  Registrar la dependencia en `bootstrap/container.php` si no existe el binding.

- [ ] **Paso 5: Actualizar la vista — reemplazar `input[number]` por `<select>`**

  En `resources/views/backoffice/keeper/incidents/create.php`, reemplazar:

  ```html
  <input type="number" name="animal_id" placeholder="Ej: 3">
  ```

  por:

  ```html
  <select name="animal_id" id="animal_id" class="form-select" required>
      <option value="" disabled selected>— Selecciona un animal —</option>
      <?php foreach ($animals as $animal): ?>
          <option value="<?= e((string) $animal['id']) ?>">
              <?= e($animal['name']) ?>
          </option>
      <?php endforeach; ?>
  </select>
  ```

- [ ] **Paso 6: Probar el formulario**

  Navegar a `http://localhost:8080/keeper/incidents/create`.
  El campo debe mostrar un `<select>` con los nombres de los animales activos del café.

- [ ] **Paso 7: Ejecutar tests unitarios del keeper**

  ```bash
  make test-unit
  ```

  Salida esperada: todos los tests del keeper pasan.

- [ ] **Paso 8: PHPStan**

  ```bash
  make phpstan
  ```

  Salida esperada: 0 errores nuevos.

- [ ] **Paso 9: Commit**

  ```bash
  git add app/Http/Controllers/Keeper/AnimalIncidentController.php \
          resources/views/backoffice/keeper/incidents/create.php \
          bootstrap/container.php
  git commit -m "fix(keeper): reemplazar input number por select de animales en formulario de incidentes [BUG-17]"
  ```

---

### T8: Catálogo de cafés — Placeholder en imágenes rotas [BUG-5]

> **Law of UX aplicable:** *Aesthetic-Usability Effect* — imágenes rotas destruyen la estética percibida. Un placeholder consistente mantiene el layout y la confianza.

**Archivos:**

- Modificar: `resources/views/public/cafes/index.php`
- Crear: `public/images/ui/placeholder-cafe.svg`

- [ ] **Paso 1: Diseñar el placeholder en Figma**

  Crear un SVG simple con icono de café (☕ o taza) en tonos `#C9A959` sobre fondo `#F7F3EB`.
  Exportar como SVG optimizado.
  Validar Laws of UX:
  - ✓ Aesthetic-Usability Effect: placeholder coherente con la marca
  - ✓ Error Prevention: el usuario sabe que hay imagen pendiente

- [ ] **Paso 2: Crear el SVG placeholder**

  Crear `public/images/ui/placeholder-cafe.svg` con contenido SVG simple de taza de café.

- [ ] **Paso 3: Localizar el img tag en la vista de cafés**

  ```bash
  docker compose exec app grep -n "<img" resources/views/public/cafes/index.php
  ```

- [ ] **Paso 4: Añadir onerror al img**

  ```html
  <!-- antes -->
  <img src="<?= e($cafe['image_url']) ?>" alt="<?= e($cafe['name']) ?>">
  <!-- después -->
  <img src="<?= e($cafe['image_url']) ?>"
       alt="<?= e($cafe['name']) ?>"
       onerror="this.onerror=null; this.src='/images/ui/placeholder-cafe.svg'">
  ```

- [ ] **Paso 5: Probar con imagen rota simulada**

  Temporalmente cambiar `src` a una URL inválida en el inspector del navegador y confirmar que el placeholder aparece.

- [ ] **Paso 6: Commit**

  ```bash
  git add resources/views/public/cafes/index.php public/images/ui/placeholder-cafe.svg
  git commit -m "fix(cafes): placeholder SVG para imágenes rotas en catálogo [BUG-5]"
  ```

---

### T9: Keeper dashboard — Overflow y placeholders de animales [BUG-6, BUG-7]

> **Law of UX aplicable:** *Law of Common Region* — las tarjetas de animales deben tener límites visuales claros. El overflow horizontal rompe la percepción de región y confunde el scroll.

**Archivos:**

- Modificar: `resources/views/backoffice/keeper/dashboard.php`

- [ ] **Paso 1: Diseñar las tarjetas de animales en Figma**

  Crear componente `AnimalCard` en Figma:
  - Foto del animal con `object-fit: cover`, `aspect-ratio: 1`
  - Nombre debajo de la foto (solo una vez — sin duplicado)
  - Placeholder emoji 🐾 si no hay imagen
  - Límites claros de la tarjeta (Law of Common Region)
  - Responsive: `col-xl-3 col-lg-4 col-md-6 col-sm-12`
  - Validar:
    - ✓ Law of Common Region: bordes de tarjeta claros
    - ✓ Aesthetic-Usability Effect: foto uniforme, sin overflow
    - ✓ Gestalt Proximity: nombre cerca de la foto

- [ ] **Paso 2: Localizar el grid de animales en el dashboard**

  ```bash
  docker compose exec app grep -n "animal\|col-xl\|card\|img" resources/views/backoffice/keeper/dashboard.php | head -20
  ```

- [ ] **Paso 3: Añadir onerror y fix de overflow**

  En cada `<img>` de las tarjetas de animales:

  ```html
  <img src="<?= e($animal['image_url'] ?? '') ?>"
       alt="<?= e($animal['name']) ?>"
       class="card-img-top"
       style="width:100%; height:180px; object-fit:cover;"
       onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
  <div class="d-none align-items-center justify-content-center bg-light"
       style="height:180px; font-size:2.5rem;">🐾</div>
  ```

  En el contenedor de la tarjeta añadir `overflow: hidden`:

  ```html
  <div class="card h-100" style="overflow:hidden;">
  ```

- [ ] **Paso 4: Verificar visual en dashboard**

  Navegar a `http://localhost:8080/keeper/dashboard`.
  Las tarjetas deben estar alineadas sin desbordamiento horizontal.

- [ ] **Paso 5: Commit**

  ```bash
  git add resources/views/backoffice/keeper/dashboard.php
  git commit -m "fix(keeper): overflow y placeholder emoji en tarjetas de animales del dashboard [BUG-6, BUG-7]"
  ```

---

## FASE 3 — P2 Medio

---

### T10: keeper/animals/index — Nombre duplicado en thumbnail [BUG-18]

**Archivos:**

- Modificar: `resources/views/backoffice/keeper/animals/index.php` (L71-82)

- [ ] **Paso 1: Leer el bloque de imagen + nombre en la tabla**

  ```bash
  docker compose exec app sed -n '60,90p' resources/views/backoffice/keeper/animals/index.php
  ```

- [ ] **Paso 2: Entender la duplicación**

  El img tiene `alt="Alvin"` y la celda también muestra `Alvin` como texto. Si la imagen falla, el alt se muestra más el texto → "Alvin Alvin".

- [ ] **Paso 3: Corregir — quitar alt text del img o hacerlo vacío (decorativo)**

  ```html
  <!-- antes -->
  <img src="..." alt="<?= e($animal['name']) ?>"> <?= e($animal['name']) ?>
  <!-- después -->
  <img src="..."
       alt=""
       aria-hidden="true"
       onerror="this.style.display='none'"
       style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
  <?= e($animal['name']) ?>
  ```

  La foto es decorativa porque el nombre ya es el contenido textual de la celda.

- [ ] **Paso 4: Verificar en tabla de animales**

  Navegar a `http://localhost:8080/keeper/animals`.
  Cada fila debe mostrar la foto (o nada si falla) + el nombre solo una vez.

- [ ] **Paso 5: Commit**

  ```bash
  git add resources/views/backoffice/keeper/animals/index.php
  git commit -m "fix(keeper): eliminar duplicación de nombre en thumbnail de tabla animales [BUG-18]"
  ```

---

### T11: keeper/animals/show — Placeholder de foto faltante [BUG-19]

**Archivos:**

- Modificar: `resources/views/backoffice/keeper/animals/show.php` (L57-62)
- Crear: `public/images/ui/placeholder-animal.svg`

- [ ] **Paso 1: Diseñar el placeholder de animal en Figma**

  Crear SVG con silueta de gato/animal en tonos de marca.
  Exportar como `placeholder-animal.svg`.

- [ ] **Paso 2: Crear `public/images/ui/placeholder-animal.svg`**

  SVG de silueta de animal con colores de marca `#C9A959` / `#F7F3EB`.

- [ ] **Paso 3: Leer el bloque condicional de imagen**

  ```bash
  docker compose exec app sed -n '50,70p' resources/views/backoffice/keeper/animals/show.php
  ```

- [ ] **Paso 4: Añadir rama else con placeholder**

  ```php
  <?php if (!empty($animal['image_url'])): ?>
      <img src="<?= e($animal['image_url']) ?>"
           alt="<?= e($animal['name']) ?>"
           class="img-fluid rounded"
           style="max-height:300px; object-fit:cover; width:100%;"
           onerror="this.onerror=null; this.src='/images/ui/placeholder-animal.svg'">
  <?php else: ?>
      <img src="/images/ui/placeholder-animal.svg"
           alt="Sin foto de <?= e($animal['name']) ?>"
           class="img-fluid rounded"
           style="max-height:300px; object-fit:cover; width:100%;">
  <?php endif; ?>
  ```

- [ ] **Paso 5: Verificar con animal sin imagen**

  Navegar a un animal sin `image_url` en BD y confirmar que aparece el placeholder SVG.

- [ ] **Paso 6: Commit**

  ```bash
  git add resources/views/backoffice/keeper/animals/show.php public/images/ui/placeholder-animal.svg
  git commit -m "fix(keeper): placeholder SVG para animales sin foto en vista show [BUG-19]"
  ```

---

### T12: Health-check create — Header "Notas Adicionales" inconsistente [BUG-20]

**Archivos:**

- Modificar: `resources/views/backoffice/keeper/health-checks/create.php`

- [ ] **Paso 1: Leer el formulario completo y comparar headers de sección**

  ```bash
  docker compose exec app grep -n "card-header\|bg-info\|bg-primary\|bg-secondary\|section-header" \
      resources/views/backoffice/keeper/health-checks/create.php
  ```

- [ ] **Paso 2: Identificar la clase cyan incorrecta**

  El header "Notas Adicionales" usa probablemente `bg-info` o `bg-cyan` / `text-white`.
  Los demás headers del formulario usan una clase diferente (verificar cuál).

- [ ] **Paso 3: Unificar la clase del header**

  Reemplazar la clase dispareja del header "Notas Adicionales" por la misma clase que usan los demás headers del formulario.

  ```html
  <!-- antes (ejemplo) -->
  <div class="card-header bg-info text-white">
      <h6 class="mb-0">Notas Adicionales</h6>
  </div>
  <!-- después — misma clase que los otros headers -->
  <div class="card-header bg-light">
      <h6 class="mb-0">Notas Adicionales</h6>
  </div>
  ```

  *(Usar la clase exacta encontrada en el Paso 1)*

- [ ] **Paso 4: Verificar visual en formulario**

  Navegar a `http://localhost:8080/keeper/health-checks/create` y confirmar que todos los headers de sección son visualmente consistentes.

- [ ] **Paso 5: Commit**

  ```bash
  git add resources/views/backoffice/keeper/health-checks/create.php
  git commit -m "fix(keeper): unificar clase de header en sección Notas Adicionales de health-check [BUG-20]"
  ```

---

### T13: Status translations — Manager reservaciones [BUG-10]

**Archivos:**

- Leer + modificar: `resources/views/manager/reservations/index.php`
- Leer + modificar: `resources/views/manager/reports/index.php` (si aplica)

- [ ] **Paso 1: Buscar dónde se renderiza el status y si se usa $statusLabels**

  ```bash
  docker compose exec app grep -n "status\|statusLabel\|pending\|confirmed\|cancelled" \
      resources/views/manager/reservations/index.php | head -20
  ```

- [ ] **Paso 2: Verificar si $statusLabels se define y si se aplica en todos los puntos**

  Si `$statusLabels` está definido en el controller pero solo se usa en algunos `echo`, añadir el mapeo donde falte:

  ```php
  <?php
  $statusLabels = [
      'pending'   => 'Pendiente',
      'confirmed' => 'Confirmada',
      'cancelled' => 'Cancelada',
      'completed' => 'Completada',
      'no_show'   => 'No presentado',
  ];
  ?>
  ...
  <?= e($statusLabels[$reservation['status']] ?? ucfirst($reservation['status'])) ?>
  ```

- [ ] **Paso 3: Repetir para reports/index.php si aplica**

  ```bash
  docker compose exec app grep -n "status\|pending\|confirmed" resources/views/manager/reports/index.php | head -10
  ```

- [ ] **Paso 4: Verificar visualmente en página de reservaciones del manager**

  Navegar a `http://localhost:8080/manager/reservations`.
  Los estados deben estar en español: "Pendiente", "Confirmada", etc.

- [ ] **Paso 5: Commit**

  ```bash
  git add resources/views/manager/reservations/index.php resources/views/manager/reports/index.php
  git commit -m "fix(manager): traducir estados de reservaciones al español [BUG-10]"
  ```

---

### T14: Newsletter popup — 1 vez por sesión con delay [BUG-11]

> **Law of UX aplicable:** *Doherty Threshold* + *Zeigarnik Effect* — un popup inmediato interrumpe antes de que el usuario vea valor. Con 15s de delay, el usuario ya tiene contexto y el popup es menos intrusivo.
> **Law of UX:** *Peak-End Rule* — un popup invasivo que aparece en cada página daña la percepción final del sitio.

**Archivos:**

- Modificar: `public/js/components/newsletterPopup.js`

- [ ] **Paso 1: Leer el método `checkShouldShow()` actual**

  ```bash
  docker compose exec app cat public/js/components/newsletterPopup.js
  ```

- [ ] **Paso 2: Añadir guard localStorage + delay 15s**

  Modificar `checkShouldShow()`:

  ```javascript
  checkShouldShow() {
      // Guard: solo mostrar una vez hasta que el usuario lo cierre o se suscriba
      if (localStorage.getItem('newsletter_prompted') === '1') {
          return;
      }

      // Delay de 15 segundos para no interrumpir la entrada del usuario
      setTimeout(() => {
          // Doble-check por si el usuario navigó a otra página y volvió
          if (localStorage.getItem('newsletter_prompted') === '1') {
              return;
          }
          this.shouldShow = true;
          localStorage.setItem('newsletter_prompted', '1');
      }, 15000);
  },
  ```

  En el método de cierre del popup (cuando el usuario hace click en X):

  ```javascript
  close() {
      this.shouldShow = false;
      localStorage.setItem('newsletter_prompted', '1');
  },
  ```

- [ ] **Paso 3: Verificar en navegador**

  1. Abrir `http://localhost:8080` en modo incógnito (sin localStorage).
  2. El popup NO debe aparecer inmediatamente.
  3. Esperar 15 segundos — el popup debe aparecer.
  4. Recargar la página — el popup NO debe aparecer de nuevo.

- [ ] **Paso 4: Commit**

  ```bash
  git add public/js/components/newsletterPopup.js
  git commit -m "fix(js): newsletter popup aparece solo una vez con delay de 15s [BUG-11]"
  ```

---

### T15: CSP violations — Imágenes externas y scripts inline [BUG-21, BUG-22, BUG-23]

**Archivos:**

- Leer: `migrations/` y `scripts/` (seeders con URLs de Google)
- Leer: `resources/views/backoffice/keeper/incidents/create.php` (scripts inline)
- Leer: `bootstrap/container.php` o `app/Core/Middleware.php` (política CSP actual)

- [ ] **Paso 1: Auditar URLs externas de Google en seeders**

  ```bash
  docker compose exec app grep -rn "lh3.googleusercontent.com\|googleusercontent" migrations/ scripts/ | head -20
  ```

- [ ] **Paso 2: Para cada URL externa encontrada, reemplazar por placeholder local**

  Opciones por orden de prioridad:
  1. Imagen real del proyecto en `public/images/`
  2. Placeholder SVG ya creado (T8 o T11)
  3. Cadena vacía `''` (dejará mostrar el placeholder `onerror`)

  No modificar datos de producción asumidos — solo las semillas de desarrollo.

- [ ] **Paso 3: Auditar scripts inline en incidents/create.php**

  ```bash
  docker compose exec app grep -n "<script\b" resources/views/backoffice/keeper/incidents/create.php
  ```

  Si hay scripts inline, verificar si el CSP actual tiene directiva `nonce`. Si no, mover el JS inline a `public/js/sections/keeper-incidents.js` y cargar con `<script src="...">`.

- [ ] **Paso 4: Verificar en DevTools**

  Abrir `http://localhost:8080/keeper/incidents/create`.
  DevTools → Console: no deben aparecer errores `Content Security Policy`.

- [ ] **Paso 5: Verificar que los cambios en seeders se pueden re-aplicar**

  ```bash
  make db-seed
  ```

  Debe ejecutar sin errores.

- [ ] **Paso 6: Commit**

  ```bash
  git add migrations/ scripts/ resources/views/backoffice/keeper/incidents/create.php
  git commit -m "fix(csp): reemplazar imágenes externas de Google y mover scripts inline [BUG-21, BUG-22, BUG-23]"
  ```

---

## Verificación Final

- [ ] **Tests unitarios limpios**

  ```bash
  make test-unit
  ```

  Esperado: 0 fallos.

- [ ] **PHPStan limpio**

  ```bash
  make phpstan
  ```

  Esperado: 0 errores nuevos respecto a la baseline.

- [ ] **CS limpio**

  ```bash
  make cs-check
  ```

  Esperado: 0 violaciones PSR-12.

- [ ] **Screenshots de todas las páginas corregidas**

  Usando `mcp_figma_get_screenshot` o Playwright MCP, capturar:
  - `/loyalty` → paleta de marca correcta
  - `/manager/reservations` → sin CSS visible, estados en español
  - `/reception` → modal de check-in funcional
  - `/keeper/dashboard` → tarjetas sin overflow
  - `/keeper/incidents/create` → select de animales
  - `/esta-pagina-no-existe` → 404 con layout

- [ ] **Actualizar el informe de auditoría**

  ```bash
  # Actualizar docs/UX_UI_VISUAL_REPORT.md → versión v3.0
  # Marcar BUG-1 a BUG-23 como Resuelto/Excluido según corresponda
  ```

- [ ] **Commit final**

  ```bash
  git add docs/UX_UI_VISUAL_REPORT.md
  git commit -m "docs: actualizar UX_UI_VISUAL_REPORT v3.0 con bugs corregidos"
  ```

---

## Resumen de Commits Esperados

| # | Commit | Bugs |
|---|--------|------|
| 1 | `fix(css): paleta violeta→marca loyalty card` | BUG-1, BUG-13 |
| 2 | `fix(layout): Font Awesome loyalty rewards` | BUG-2 (si aplica) |
| 3 | `fix(view): eliminar CSS huérfano reservaciones` | BUG-3 |
| 4 | `fix(reception): cargar reception.js Alpine` | BUG-15 |
| 5 | `fix(routing): 404 con layout main` | BUG-4 |
| 6 | `fix(nav): keeper sidebar 4 items` | BUG-16 |
| 7 | `fix(keeper): select animales en incidentes` | BUG-17 |
| 8 | `fix(cafes): placeholder imágenes rotas` | BUG-5 |
| 9 | `fix(keeper): overflow + placeholders dashboard` | BUG-6, BUG-7 |
| 10 | `fix(keeper): nombre duplicado thumbnail` | BUG-18 |
| 11 | `fix(keeper): placeholder foto animal show` | BUG-19 |
| 12 | `fix(keeper): header Notas Adicionales consistente` | BUG-20 |
| 13 | `fix(manager): estados reservación en español` | BUG-10 |
| 14 | `fix(js): newsletter popup una vez + delay` | BUG-11 |
| 15 | `fix(csp): imágenes externas y scripts inline` | BUG-21, BUG-22, BUG-23 |
| 16 | `docs: UX_UI_VISUAL_REPORT v3.0` | — |
