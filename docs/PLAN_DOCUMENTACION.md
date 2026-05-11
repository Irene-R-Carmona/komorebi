# Plan de Ampliación — Documentación TFG Komorebi Café

**Objetivo:** Expandir `documentacion-oficial.tex` de ~70 páginas a ~150-200 páginas.
**Estado actual:** Fase 0 completada (exploración + planificación).
**Fecha de inicio:** Mayo 2026
**Alumna:** Irene Reyes Carmona | **Centro:** I.E.S. Ágora | **Tutor/a:** María Lourdes Calleja Flores

---

## Nueva Tabla de Contenidos (12 capítulos, ~198 páginas)

| Cap | Título | Estado | ~Páginas |
|-----|--------|--------|---------|
| 1 | Introducción General | Expandir (+ requisitos, planificación, casos de uso) | 12 |
| 2 | Arquitectura y Framework MVC | Expandir (+ código real Router/Container/View/Queue/CircuitBreaker) | 20 |
| 3 | Modelo de Datos | NUEVO — 20 migraciones, SQL real, ER en TikZ | 18 |
| 4 | Módulos Funcionales (13 subsecciones) | NUEVO — capítulo más extenso | 30 |
| 5 | Seguridad | NUEVO — OWASP Top 10, CSRF, RBAC, XSS, rate limiting, headers | 12 |
| 6 | Diseño UI/UX y Sistema de Diseño | NUEVO — paleta, tokens CSS, layouts, Alpine.js, a11y | 10 |
| 7 | Paneles de Operación | Expandir (+ supervisor, capturas reales) | 15 |
| 8 | API REST | Promover apéndice a capítulo + ejemplos JSON | 14 |
| 9 | Infraestructura y DevOps | NUEVO — Docker, CI/CD, Railway, Makefile | 12 |
| 10 | Pruebas y Calidad | Expandir (+ tests reales, cobertura, métricas) | 12 |
| 11 | Manual de Usuario | Expandir con capturas reales + módulos faltantes | 20 |
| 12 | Conclusiones | Expandir (+ métricas del proyecto) | 8 |
| — | Apéndices A-D + Referencias + Frontmatter | — | 15 |

### Cap. 4 — Módulos Funcionales (13 subsecciones)

- 4.1 Módulo Público (home, cafés, menú, quiz, historia)
- 4.2 Autenticación y gestión de cuentas
- 4.3 Reservas e idempotencia
- 4.4 Fidelización y gamificación
- 4.5 Adopciones ← NUEVO (no documentado)
- 4.6 Lista de espera ← NUEVO
- 4.7 Carrito de compras ← NUEVO
- 4.8 Newsletter y notificaciones ← NUEVO
- 4.9 Keeper y bienestar animal ← NUEVO
- 4.10 Supervisor y turnos ← NUEVO

---

## Decisiones técnicas

| Decisión | Elección |
|----------|----------|
| Diagramas ER | TikZ (sin dependencia de Inkscape) |
| Capturas de pantalla | Playwright → `docs/screenshots/` |
| Estructura del `.tex` | Archivo único (no `\input` múltiples) |
| Fragmentos de código | Código real del repo, no ejemplos inventados |
| Compilación | `xelatex -shell-escape` → biber → xelatex × 2 |

---

## Fases de Implementación

---

### FASE 0 — Exploración y Planificación ✅ COMPLETADA

**Resultado:** Plan guardado en `docs/PLAN_DOCUMENTACION.md`.

- [x] Leer documento LaTeX actual completo
- [x] Explorar estructura del proyecto (controllers, services, migrations, tests, routes, views)
- [x] Definir estrategia de expansión con la alumna
- [x] Crear plan por fases

---

### FASE 1 — Preparación de Recursos

**Objetivo:** Reunir capturas de pantalla reales y fragmentos de código clave antes de escribir.
**~Páginas generadas:** 0 (preparación pura)

#### 1a — Capturas con Playwright (39 capturas)

Guardar todas en `docs/screenshots/`:

- [ ] `home.png` — página principal pública
- [ ] `cafes-listado.png` — listado de cafés
- [ ] `cafe-detalle.png` — página de un café concreto
- [ ] `menu.png` — carta/menú
- [ ] `quiz.png` — quiz de recomendación de café
- [ ] `historia.png` — página de historia
- [ ] `login.png` — formulario de login
- [ ] `registro.png` — formulario de registro
- [ ] `perfil.png` — perfil de usuario
- [ ] `reservas-nueva.png` — formulario de nueva reserva
- [ ] `reservas-listado.png` — listado de reservas del usuario
- [ ] `fidelizacion.png` — panel de puntos/fidelización
- [ ] `adopciones-listado.png` — listado de animales adoptables
- [ ] `adopciones-detalle.png` — detalle de un animal
- [ ] `lista-espera.png` — formulario/panel de lista de espera
- [ ] `carrito.png` — carrito de compras
- [ ] `newsletter.png` — suscripción newsletter
- [ ] `keeper-dashboard.png` — panel keeper
- [ ] `keeper-health-check.png` — registro de revisión de salud
- [ ] `admin-dashboard.png` — dashboard admin
- [ ] `admin-usuarios.png` — gestión de usuarios
- [ ] `admin-cafes.png` — gestión de cafés
- [ ] `admin-reservas.png` — gestión de reservas
- [ ] `backoffice.png` — backoffice general
- [ ] `manager-dashboard.png` — panel manager
- [ ] `supervisor-dashboard.png` — panel supervisor
- [ ] `supervisor-turnos.png` — gestión de turnos
- [ ] `reception-dashboard.png` — panel recepción
- [ ] `reception-reservas.png` — check-in de reservas
- [ ] `kds.png` — Kitchen Display System
- [ ] `dark-mode.png` — ejemplo dark mode
- [ ] `mobile-home.png` — vista móvil de home
- [ ] `mobile-reservas.png` — vista móvil de reservas
- [ ] `error-404.png` — página de error 404
- [ ] `error-500.png` — página de error 500
- [ ] `docker-logs.png` — logs del stack Docker
- [ ] `phpstan-output.png` — salida de PHPStan nivel 5
- [ ] `phpunit-output.png` — salida de PHPUnit con tests pasando
- [ ] `github-actions.png` — pipeline CI/CD en GitHub Actions

#### 1b — Archivos PHP clave a leer para extraer fragmentos

- [ ] `app/Core/Router.php`
- [ ] `app/Core/Container.php`
- [ ] `app/Core/View.php`
- [ ] `app/Core/Result.php`
- [ ] `app/Core/Http/ResponseFactory.php`
- [ ] `app/Core/Middleware.php`
- [ ] `app/Core/Cache/RedisCache.php`
- [ ] `app/Core/Queue/Queue.php`
- [ ] `app/Core/CircuitBreaker.php` (si existe)
- [ ] `bootstrap/container.php`
- [ ] `app/routes.php` (selección representativa)
- [ ] Un controller representativo de cada rol (Admin, Manager, Reception, Kitchen, Keeper)
- [ ] `app/Services/ReservationService.php` (o equivalente)
- [ ] `app/Services/LoyaltyService.php` (o equivalente)
- [ ] `app/Repositories/AbstractRepository.php`
- [ ] Un test unitario representativo

---

### FASE 2 — Capítulos 1-2: Introducción y Arquitectura

**Objetivo:** Reescribir y expandir los capítulos actuales con estructura formal y código real.
**~Páginas generadas:** 32 (cap. 1: 12 + cap. 2: 20)

#### Cap. 1 — Introducción General

- [ ] 1.1 Contexto y motivación (expandir párrafos actuales)
- [ ] 1.2 Objetivos generales y específicos (lista formal)
- [ ] 1.3 Planificación temporal (diagrama Gantt en TikZ o tabla)
- [ ] 1.4 Requisitos funcionales y no funcionales (tabla completa)
- [ ] 1.5 Casos de uso principales (diagrama UML o tabla)
- [ ] 1.6 Metodología de desarrollo
- [ ] 1.7 Estructura de la memoria (guía de lectura)

#### Cap. 2 — Arquitectura y Framework MVC

- [ ] 2.1 Visión general de la arquitectura (diagrama C4 nivel 1-2 en TikZ)
- [ ] 2.2 Front Controller y ciclo de una petición (diagrama de secuencia)
- [ ] 2.3 Router — código real + ejemplos de rutas
- [ ] 2.4 Container de dependencias — código real + patrón singleton
- [ ] 2.5 Sistema de vistas — View::render(), Raw, XSS escaping
- [ ] 2.6 Result pattern — código real de Result.php
- [ ] 2.7 Sistema de caché Redis
- [ ] 2.8 Cola de trabajos asíncronos (Queue + Workers)
- [ ] 2.9 Circuit Breaker y resiliencia
- [ ] 2.10 Sistema de eventos PSR-14

---

### FASE 3 — Capítulo 3: Modelo de Datos

**Objetivo:** Documentar las 20 migraciones SQL con diagrama ER real.
**~Páginas generadas:** 18

- [ ] 3.1 Introducción al modelo de datos (decisiones de diseño)
- [ ] 3.2 Diagrama Entidad-Relación completo (TikZ)
- [ ] 3.3 Módulo de usuarios y RBAC (tablas: users, roles, permissions)
- [ ] 3.4 Módulo de cafés y menú (tablas: cafes, menu_items, categories)
- [ ] 3.5 Módulo de reservas (tablas: reservations, time_slots, waitlist)
- [ ] 3.6 Módulo de fidelización (tablas: loyalty_points, loyalty_tiers)
- [ ] 3.7 Módulo de adopciones (tablas: animals, adoptions)
- [ ] 3.8 Módulo de keeper (tablas: health_checks, supervisor_assignments)
- [ ] 3.9 Módulo de operaciones (tablas: shifts, staff_shifts)
- [ ] 3.10 Módulo de comunicación (tablas: newsletter, api_tokens)
- [ ] 3.11 Índices, constraints y triggers relevantes
- [ ] 3.12 Estrategia de migraciones y versionado

---

### FASE 4 — Capítulo 4: Módulos Funcionales

**Objetivo:** Documentar los 13 módulos funcionales con código real y capturas.
**~Páginas generadas:** 30 (el capítulo más extenso)

- [ ] 4.1 Módulo Público
  - [ ] Home, cafés, menú, quiz de recomendación, historia
  - [ ] Código: `PublicCafeController`, `PublicMenuController`
  - [ ] Capturas: `home.png`, `cafes-listado.png`, `menu.png`, `quiz.png`
- [ ] 4.2 Autenticación y gestión de cuentas
  - [ ] Login, registro, email verification, recovery
  - [ ] Código: `AuthController`, `AuthService`
  - [ ] Capturas: `login.png`, `registro.png`, `perfil.png`
- [ ] 4.3 Reservas e idempotencia
  - [ ] Flujo completo de reserva, slots, idempotencia
  - [ ] Código: `ReservationService`, idempotency key
  - [ ] Capturas: `reservas-nueva.png`, `reservas-listado.png`
- [ ] 4.4 Fidelización y gamificación
  - [ ] Sistema de puntos, niveles, recompensas
  - [ ] Código: `LoyaltyService`
  - [ ] Captura: `fidelizacion.png`
- [ ] 4.5 Adopciones ← NUEVO
  - [ ] Proceso de adopción, estados, gestión
  - [ ] Código: `AdoptionController`, `AdoptionService`
  - [ ] Capturas: `adopciones-listado.png`, `adopciones-detalle.png`
- [ ] 4.6 Lista de espera ← NUEVO
  - [ ] Notificaciones automáticas, prioridad
  - [ ] Código: `WaitlistService`
  - [ ] Captura: `lista-espera.png`
- [ ] 4.7 Carrito de compras ← NUEVO
  - [ ] Sesión, persistencia, checkout
  - [ ] Código: `CartController`, `CartService`
  - [ ] Captura: `carrito.png`
- [ ] 4.8 Newsletter y notificaciones ← NUEVO
  - [ ] Suscripción, envío asíncrono, workers
  - [ ] Código: `NewsletterService`, `SendEmailJob`
  - [ ] Captura: `newsletter.png`
- [ ] 4.9 Keeper y bienestar animal ← NUEVO
  - [ ] Revisiones de salud, registros, panel keeper
  - [ ] Código: `KeeperController`, `HealthCheckService`
  - [ ] Capturas: `keeper-dashboard.png`, `keeper-health-check.png`
- [ ] 4.10 Supervisor y turnos ← NUEVO
  - [ ] Asignación de turnos, supervisión de staff
  - [ ] Código: `SupervisorController`, `ShiftService`
  - [ ] Capturas: `supervisor-dashboard.png`, `supervisor-turnos.png`

---

### FASE 5 — Capítulos 5-6: Seguridad y UI/UX

**Objetivo:** Documentar la capa de seguridad y el sistema de diseño.
**~Páginas generadas:** 22 (cap. 5: 12 + cap. 6: 10)

#### Cap. 5 — Seguridad

- [ ] 5.1 Modelo de amenazas (OWASP Top 10 aplicado)
- [ ] 5.2 Autenticación — sesiones seguras, tokens, email verification
- [ ] 5.3 Autorización RBAC — roles, permisos, middleware
- [ ] 5.4 Protección CSRF — implementación y tokens
- [ ] 5.5 Prevención XSS — escaping automático, Raw controlado
- [ ] 5.6 Prevención SQL Injection — PDO + prepared statements
- [ ] 5.7 Rate limiting — Redis-backed por bucket
- [ ] 5.8 Headers de seguridad HTTP
- [ ] 5.9 Gestión de secretos — SecretLoader, env vars, `/run/secrets`
- [ ] 5.10 Auditoría de seguridad y SECURITY.md

#### Cap. 6 — Diseño UI/UX y Sistema de Diseño

- [ ] 6.1 Principios de diseño — identidad Komorebi, filosofía visual
- [ ] 6.2 Paleta de colores y tokens CSS
- [ ] 6.3 Tipografía y sistema de espaciado
- [ ] 6.4 Layouts responsivos — mobile-first, breakpoints
- [ ] 6.5 Dark mode — implementación CSS + Alpine.js
- [ ] 6.6 Componentes Alpine.js — interactividad sin bundler
- [ ] 6.7 Accesibilidad WCAG 2.1 AA — aria-labels, contraste, navegación teclado
- [ ] 6.8 Sistema de notificaciones Flash
- [ ] 6.9 Capturas: `dark-mode.png`, `mobile-home.png`, `mobile-reservas.png`

---

### FASE 6 — Capítulos 7-8: Operaciones y API REST

**Objetivo:** Documentar los paneles operativos y la API pública.
**~Páginas generadas:** 29 (cap. 7: 15 + cap. 8: 14)

#### Cap. 7 — Paneles de Operación

- [ ] 7.1 Panel de Administración — usuarios, cafés, reservas, configuración
- [ ] 7.2 Backoffice — gestión avanzada
- [ ] 7.3 Panel de Manager — métricas, informes
- [ ] 7.4 Panel de Supervisor — turnos, asignaciones
- [ ] 7.5 Panel de Recepción — check-in, walk-ins
- [ ] 7.6 Kitchen Display System (KDS) — pedidos en tiempo real
- [ ] 7.7 Panel Keeper — salud animal
- [ ] Capturas: todos los dashboards por rol

#### Cap. 8 — API REST

- [ ] 8.1 Diseño RESTful — principios aplicados, versionado `/api/v1/`
- [ ] 8.2 Autenticación API — tokens, Bearer, scopes
- [ ] 8.3 Endpoints documentados con ejemplos JSON reales
  - [ ] GET /api/v1/menu/alergenos
  - [ ] Reservas, cafés, menú, animales...
- [ ] 8.4 Manejo de errores — códigos HTTP, formato de error estándar
- [ ] 8.5 CORS y middleware de API
- [ ] 8.6 Referencia a `docs/openapi.yaml`

---

### FASE 7 — Capítulos 9-10: DevOps y Pruebas

**Objetivo:** Documentar la infraestructura y la estrategia de calidad.
**~Páginas generadas:** 24 (cap. 9: 12 + cap. 10: 12)

#### Cap. 9 — Infraestructura y DevOps

- [ ] 9.1 Arquitectura 12-Factor — principios aplicados
- [ ] 9.2 Docker Compose — servicios (app, mysql, redis, mailpit)
- [ ] 9.3 FrankenPHP — servidor de producción, Caddyfile
- [ ] 9.4 Variables de entorno y gestión de secretos
- [ ] 9.5 CI/CD con GitHub Actions — pipeline completo
- [ ] 9.6 Despliegue en Railway — producción
- [ ] 9.7 Makefile — comandos de desarrollo y operaciones
- [ ] 9.8 Monitorización — logs stdout, health endpoint
- [ ] Capturas: `docker-logs.png`, `github-actions.png`

#### Cap. 10 — Pruebas y Calidad

- [ ] 10.1 Estrategia de pruebas — pirámide, coverage objetivo
- [ ] 10.2 Tests unitarios — estructura, convenciones, docblock obligatorio
- [ ] 10.3 Tests de integración — base de datos real, fixtures
- [ ] 10.4 Tests E2E con Playwright — flujos críticos
- [ ] 10.5 PHPStan nivel 5 — análisis estático, baseline
- [ ] 10.6 Psalm — análisis adicional
- [ ] 10.7 PHP-CS-Fixer — estilo PSR-12, `native_function_invocation`
- [ ] 10.8 Métricas de cobertura — reporte real
- [ ] 10.9 Definition of Done — checklist por tipo de cambio
- [ ] Capturas: `phpstan-output.png`, `phpunit-output.png`

---

### FASE 8 — Capítulos 11-12: Manual de Usuario y Conclusiones

**Objetivo:** Manual completo con capturas reales y conclusiones expandidas.
**~Páginas generadas:** 28 (cap. 11: 20 + cap. 12: 8)

#### Cap. 11 — Manual de Usuario

- [ ] 11.1 Acceso al sistema — registro, login, recuperación
- [ ] 11.2 Perfil de usuario — edición, seguridad, sesiones
- [ ] 11.3 Explorar cafés y menú — búsqueda, filtros
- [ ] 11.4 Realizar una reserva — paso a paso con capturas
- [ ] 11.5 Lista de espera — cómo apuntarse y recibir notificación
- [ ] 11.6 Carrito y compra — añadir productos, checkout
- [ ] 11.7 Programa de fidelización — ver puntos, canjear recompensas
- [ ] 11.8 Adoptar un animal — proceso completo
- [ ] 11.9 Newsletter — suscripción y gestión
- [ ] 11.10 Panel de Administración — guía para administradores
- [ ] 11.11 Panel de Recepción — guía para staff de recepción
- [ ] 11.12 Kitchen Display System — guía para cocina
- [ ] 11.13 Panel Keeper — guía para cuidadores
- [ ] 11.14 Panel Supervisor — guía para supervisores

#### Cap. 12 — Conclusiones

- [ ] 12.1 Objetivos alcanzados — revisión frente a requisitos iniciales
- [ ] 12.2 Métricas del proyecto — líneas de código, tests, endpoints, vistas
- [ ] 12.3 Dificultades encontradas y soluciones
- [ ] 12.4 Competencias desarrolladas
- [ ] 12.5 Líneas de trabajo futuro
- [ ] 12.6 Reflexión personal

---

### FASE 9 — Revisión Final y Compilación

**Objetivo:** Apéndices, referencias, glosario y compilación limpia del PDF final.
**~Páginas generadas:** 15

- [ ] Apéndice A — Referencia completa de la API REST (tabla de endpoints)
- [ ] Apéndice B — Diagrama ER completo (página completa)
- [ ] Apéndice C — Estructura de directorios del proyecto
- [ ] Apéndice D — Glosario de términos técnicos y de dominio
- [ ] Referencias bibliográficas — completar con nuevas fuentes
- [ ] Portada, resumen (castellano + inglés), índice de contenidos
- [ ] Índice de figuras, índice de tablas, índice de listados de código
- [ ] Revisión ortotipográfica completa
- [ ] Compilación final limpia: `xelatex -shell-escape` → `biber` → `xelatex` × 2
- [ ] Verificar PDF: saltos de página, referencias cruzadas, bibliografía, hipervínculos

---

## Seguimiento de Estado

| Fase | Título | Estado | Inicio | Fin |
|------|--------|--------|--------|-----|
| 0 | Exploración y Planificación | ✅ Completada | — | Mayo 2026 |
| 1 | Preparación de Recursos | ⏳ Pendiente | — | — |
| 2 | Cap. 1-2: Introducción y Arquitectura | ⏳ Pendiente | — | — |
| 3 | Cap. 3: Modelo de Datos | ⏳ Pendiente | — | — |
| 4 | Cap. 4: Módulos Funcionales | ⏳ Pendiente | — | — |
| 5 | Cap. 5-6: Seguridad y UI/UX | ⏳ Pendiente | — | — |
| 6 | Cap. 7-8: Operaciones y API REST | ⏳ Pendiente | — | — |
| 7 | Cap. 9-10: DevOps y Pruebas | ⏳ Pendiente | — | — |
| 8 | Cap. 11-12: Manual y Conclusiones | ⏳ Pendiente | — | — |
| 9 | Revisión Final y Compilación | ⏳ Pendiente | — | — |
