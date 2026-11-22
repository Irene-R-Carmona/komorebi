# Registro de ejecución — Auditoría Komorebi

**Versión:** 1.0  
**Última actualización:** 2026-11-22  
**Estado global:** auditoría abierta; implementación no autorizada

## Registro cronológico

| Fecha | Actividad | Resultado | Estado |
|---|---|---|---|
| 2026-11-19 | Revisión de logs de Railway aportados por el usuario | Arranque de MySQL/app, migraciones, seeders desactivados y workers identificados en logs | Evidencia recibida |
| 2026-11-19 | Consulta de estado de tablas y tracking de migraciones | Tablas consultadas vacías; `schema_migrations` solo contenía la migración 019 | Evidencia recibida; falta cotejo completo |
| 2026-11-19 a 2026-11-21 | Auditoría estática del repositorio | Hallazgos sobre negocio, seguridad, colas, jobs, configuración, CI y frontend | En curso |
| 2026-11-21 | Consolidación documental v2.0 fuera del repositorio | Se informó total provisional de 174 hallazgos únicos | Pendiente de reconciliar |
| 2026-11-22 | Comprobación de rama `develop` | HEAD `a2e24936de3706c0709f08e3b8fe2cef2a24fa0d`; commit `Update README.md` | Verificado en GitHub |
| 2026-11-22 | Creación de documentación de auditoría en `docs/audit/` | Auditoría, inventario, plan y registro | Documentación únicamente |

## Evidencia pendiente del panel Railway

Solicitar extractos, no logs completos:
- **A — Arranque:** errores, excepciones, warnings y estado de proceso.
- **B — Migraciones:** creación de tracking, orden, warnings y resultado de cada migración.
- **C — Workers/colas:** procesos, colas, Redis, pending, retry, ACK, DLQ.
- **D — Email/notificaciones:** SMTP, PHPMailer, SendEmailJob, RewardUnlockedJob, Telegram.
- **E — Seeders:** confirmación de bloqueo y ausencia de ejecución forzada.
- **F — Servicios externos/storage:** Cloudinary, Open-Meteo, Mercure, uploads, permisos y volumen.

Prioridad de recopilación: A, B y C. Compartir 10–20 líneas de contexto por error y eliminar secretos antes de enviar.

## Preguntas operativas sin resolver

- ¿Qué comando efectivo usa Railway para build/start/pre-deploy?
- ¿Se ejecutan migraciones desde dos puntos distintos?
- ¿Qué procesos Supervisor están realmente vivos?
- ¿Hay volumen persistente montado en `/app/storage/uploads`?
- ¿Qué jobs están encolados y qué colas tienen consumidores?
- ¿Están configurados SMTP/Telegram/Cloudinary/Mercure?
- ¿Existe backup de DB y uploads y se ha restaurado en un entorno aislado?
- ¿La aplicación pública está accesible y qué endpoints de health expone?

## Decisiones pendientes

- Política de desactivación temporal de correo y Telegram.
- Política de workers y colas no operativas.
- Corrección del tracking de migraciones.
- Política de storage persistente y backup.
- Reconciliación del inventario y prioridades.
- Autorización para iniciar implementación, que debe ser explícita y por alcance.

## Regla de actualización

Cada sesión debe añadir fecha, evidencia examinada, archivos/símbolos afectados, decisiones, tareas abiertas y criterios de aceptación. No borrar entradas anteriores; corregirlas mediante una nueva anotación con trazabilidad.
