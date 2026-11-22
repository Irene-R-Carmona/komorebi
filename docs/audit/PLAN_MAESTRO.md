# Plan maestro de auditoría, contención y remediación — Komorebi

**Versión:** 1.0  
**Fecha:** 2026-11-22  
**Estado:** propuesta de ejecución por fases; no implementada

## Principios no negociables

1. Completar la auditoría antes de implementar mejoras.
2. Proteger datos reales: no ejecutar seeders en producción ni activar `FORCE_SEED=1`.
3. No modificar migraciones ya aplicadas sin estrategia revisada y backup restaurable.
4. No ejecutar cambios de DB, Railway, variables, workers, jobs, storage o despliegues sin autorización expresa.
5. No considerar una función prescindible solo porque esté incompleta: evaluar corrección, apagado reversible o modo demo.
6. Toda corrección debe tener prueba reproducible, criterios de aceptación y reversión.
7. Separar staging/demo de producción y utilizar datos ficticios en validación.

## Fase 0 — Cerrar inventario y proteger el estado actual

**Objetivo:** conocer exactamente qué existe y poder volver atrás.

- Confirmar branch, commit y estado de cambios.
- Inventariar servicios Railway, variables (sin copiar secretos), dominios y comandos efectivos.
- Confirmar backups de MySQL y prueba real de restauración en entorno aislado.
- Confirmar persistencia y backup de uploads.
- Confirmar Redis, colas, consumer groups, pending, DLQ y retención.
- Confirmar workers y jobs que arrancan realmente.
- Recuperar documentos v1.5/v2.0 y reconciliar IDs/prioridades.

**Salida exigida:** inventario validado, backup restaurado en entorno aislado, topología y estado operativo documentados. No se ejecuta ninguna corrección de producción en esta fase.

## Fase 1 — Contención segura

**Objetivo:** reducir el riesgo mientras se corrige, sin pérdida de capacidades/datos.

- Correo externo y Telegram: política centralizada y apagado reversible hasta probar credenciales, dependencias, consentimiento e idempotencia.
- Recompensas: evitar bypass del interruptor general.
- Seeders: mantener bloqueo en producción.
- Health/logs: limitar exposición de datos internos.
- Rutas sensibles: comprobar autorización por sede antes de mantenerlas expuestas.
- Workers: apagar solo procesos concretos cuya cola/dependencias se hayan identificado y cuyo apagado no pierda trabajos.

**Salida exigida:** matriz de capacidades habilitadas/deshabilitadas, riesgos residuales y evidencia de que el apagado no elimina ni altera datos.

## Fase 2 — Seguridad y autorización

- Autorización por sede y recurso en Keeper, Kitchen, Reception y backoffice.
- Coherencia entre sesión web, Bearer tokens y permisos.
- Invalidación de sesión ante cambios de rol/permisos.
- Rate limiting atómico/global donde proceda.
- Redacción de PII y secretos en logs.
- Health endpoint con separación entre liveness/readiness y diagnóstico autenticado.

**Salida exigida:** pruebas negativas de acceso cruzado y pruebas por rol/sede; revisión de logs y health sin exposición indebida.

## Fase 3 — Integridad de negocio y datos

- Reservas y capacidad: tamaño del grupo, concurrencia y transacciones.
- Waitlist: validación server-side, estados, expiración, locks e idempotencia.
- Pagos, cancelaciones, no-show y reembolsos: estados persistidos y consistentes.
- Carrito: vinculación a sede y validación de productos/precios/duración desde servidor.
- Productos/animales: soft delete, disponibilidad, relaciones y ediciones parciales.
- Reviews, loyalty y gamificación: integridad, reglas y recálculo.

**Salida exigida:** tests de integración y concurrencia para los flujos críticos, invariantes de DB documentadas y ausencia de operaciones parciales no recuperables.

## Fase 4 — Migraciones, workers y scheduler

- Corregir inicialización/registro de `schema_migrations` y manejo de errores.
- Evitar doble ejecución de migraciones entre pre-deploy y entrypoint.
- Lock global de migraciones y verificación de idempotencia.
- Revisar construcción de jobs, ACK, retry, DLQ, pending, delayed jobs y trim.
- Confirmar scheduler y timezone de aplicación/DB.
- Definir límites, backoff, retención y observabilidad.

**Salida exigida:** pruebas de migración desde base vacía y base existente, fallo inducido, reinicio de worker, mensaje poison, recuperación de pending y confirmación de ACK/DLQ.

## Fase 5 — Funcionalidades, servicios externos y experiencia

- Correo, Telegram, Cloudinary, Mercure y Open-Meteo.
- Newsletter, consentimiento de cookies y settings.
- CRUD, turnos, catálogo, estadísticas y funcionalidades de administración.
- Paridad web/API y contratos de frontend.
- Distinguir funcionalidad operativa, experimental, demo y desactivada.

**Salida exigida:** matriz funcional con pruebas reales o sandbox, documentación de dependencias y comportamiento cuando un proveedor no está disponible.

## Fase 6 — Datos demo y calidad

- Dataset ficticio, coherente y aislado.
- Seeders repetibles y no destructivos, separados por entorno.
- PHPUnit, integración, Playwright y pruebas de concurrencia.
- PHPStan sin ocultar deuda crítica; Composer audit como gate efectivo.
- CI alineada con despliegue y branch real.

**Salida exigida:** ejecución reproducible en entorno limpio; ninguna dependencia de datos reales ni de secretos de producción.

## Fase 7 — Validación preproducción y producción

- Restore drill completo.
- Pruebas de seguridad, permisos, datos, colas y persistencia.
- Health, métricas, alertas y runbooks.
- Plan de despliegue gradual, rollback y comprobaciones posteriores.
- Aprobación explícita de cada cambio con impacto en producción.

**Salida exigida:** checklist de release firmado y evidencia reproducible.

## Orden de trabajo recomendado

1. Fase 0: confirmar estado, backup/restore y topología.
2. Fase 1: contención selectiva reversible.
3. Fase 2: seguridad y aislamiento.
4. Fase 3: integridad de negocio.
5. Fase 4: migraciones y procesamiento asíncrono.
6. Fase 5: capacidades y proveedores externos.
7. Fase 6: dataset y calidad.
8. Fase 7: validación y release.

No avanzar por calendario: cada fase depende de sus criterios de salida.

## Criterios de prioridad

- P0: exposición de datos, corrupción/pérdida, ejecución no autorizada o riesgo de caída grave.
- P1: integridad de negocio, autorización, operación asíncrona o funcionalidad principal rota.
- P2: inconsistencia funcional, calidad, rendimiento o deuda técnica no bloqueante.
- P3: mejora opcional.

Las prioridades deben justificarse por escenario, alcance e impacto, no por orden de aparición en el inventario.
