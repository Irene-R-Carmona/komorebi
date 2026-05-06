# Índice Maestro de Planes — Komorebi Café

Todos los planes activos e históricos del proyecto.

## Estados

| Emoji | Significado |
|-------|-------------|
| 🔵 | Plan creado — pendiente inicio |
| 🟡 | En implementación |
| 🟢 | Implementación completa — pendiente verificación |
| ✅ | Verificado y cerrado |

> Los planes completados se eliminan de `docs/plans/` y su fila pasa a `✅ Completado y eliminado (YYYY-MM-DD)`.

---

## Planes activos

| Fecha | Archivo | Descripción | Estado |
|-------|---------|-------------|--------|
| 2026-04-29 | — | Auditoría completa de todas las páginas web pre-entrega TFG y pre-despliegue Railway | ✅ Completado y eliminado (2026-04-30) |
| 2026-04-30 | [2026-04-30-infraestructura-config.md](2026-04-30-infraestructura-config.md) | Auditoría y mejora de infraestructura Docker, CI y archivos de configuración (umbral cobertura → 60 %) | 🔵 Plan creado — pendiente inicio |
| 2026-04-30 | — | Fix bugs visuales acumulados (23+21), alineación brand book, identidad cromática por rol en backoffice | ✅ Completado y eliminado (2026-05-01) |
| 2026-05-01 | — | Rediseño páginas de error (400–503) e intersticial de redirección con brand tokens, kanjis decorativos y accesibilidad WCAG 2.1 | ✅ Completado y eliminado (2026-05-01) |
| 2026-05-01 | — | Auditoría inmersiva de funcionalidades rotas en producción: carrito, alérgenos, reseñas, cookie consent, /reservas (7 bugs corregidos, 0 desactivados) | ✅ Completado y eliminado (2026-05-01) |
| 2026-05-04 | — | Auditoría completa de vistas SSR + CRUD + API mutations (7 GAPs: Fases 1-7 completadas — newsletter admin, loyalty admin, keeper cleanup, quality gate PHPStan+tests+cs-fix) | ✅ Completado y eliminado (2026-05-04) |
| 2026-05-05 | — | Corrección bugs auditoría completa: Flash::set(), XSS vistas, rate limiting rutas API, #[Override], DI interface, 23 DTOs fromArray(), slug UNIQUE, índice payment_status | ✅ Completado y eliminado (2026-05-05) |
| 2026-05-05 | — | Imágenes pases, POS recepción + KDS, checkout con pago + ingresos semanales, badges comanda | ✅ Completado y eliminado (2026-05-05) |
| 2026-05-05 | — | Seeders y tablas vacías: eliminar 3 tablas legacy, poblar 22 tablas con datos de prueba, implementar interaction_sessions en check-in/out | ✅ Completado y eliminado (2026-05-05) |
| 2026-05-06 | — | Remediación completa 47 hallazgos: IDOR Keeper, XSS Flash, race conditions Waitlist+Loyalty, triggers capacidad, document.write quiz, quiz perfiles reales | ✅ Completado y eliminado (2026-05-06) |
| 2026-05-06 | — | Auditoría pre-despliegue: utility classes (S0), loyalty data (S2), WCAG 17 fallos CSS (S1), paginación manager (S3), staff CRUD+calendario (S4), aplicar utilities ~70 archivos (S5), EmailService e() (S6) | ✅ Completado y eliminado (2026-05-06) |

---

## Historial de planes completados

| Fecha cierre | Descripción | Estado |
|--------------|-------------|--------|
| — | — | — |
