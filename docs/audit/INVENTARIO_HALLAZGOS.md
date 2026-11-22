# Inventario de hallazgos — Komorebi

**Versión:** 2.1 (inventario de trabajo)  
**Fecha:** 2026-11-22  
**Estado:** abierto; reconciliación de IDs y prioridades pendiente

## 1. Convenciones

- **P0:** bloquea exposición/operación segura; requiere contención antes de producción o usuarios no confiables.
- **P1:** corrección prioritaria para integridad, seguridad o funcionamiento fiable.
- **P2:** corrección importante, pero puede planificarse después de los bloqueos.
- **P3:** mejora no bloqueante.
- **Estado:** `PENDIENTE`, salvo que exista evidencia reproducible de cierre. Ningún hallazgo de esta auditoría se considera resuelto por estar documentado.
- **Decisión:** `CORREGIR`, `DESACTIVAR TEMPORALMENTE`, `RESTRINGIR/DEMO` o `RETIRAR` después de análisis y aprobación.

## 2. Integridad del inventario

La consolidación de trabajo previa informó:
- Base heredada: H-001 a H-115.
- Referencias incorporadas después: 77.
- Incorporaciones independientes: 59.
- Ampliaciones o duplicidades fusionadas: 18.
- Total comunicado: 174 hallazgos únicos.

Los números anteriores son **provisionales y no certificados**. El archivo v2.0 que contenía el detalle no está disponible para cotejo íntegro en el entorno actual. Por tanto, este documento no reconstruye títulos o evidencias que no se conservan en el contexto. La reconciliación debe recuperar los documentos originales y comparar ID, título, evidencia, prioridad y estado uno a uno.

## 3. Hallazgos operativos confirmados o fuertemente respaldados

| ID / referencia | Área | Hallazgo | Prioridad provisional | Acción candidata | Estado |
|---|---|---|---|---|---|
| H-167/H-168 | Migraciones | No existe lock global y el fallo al registrar migraciones puede dejar el proceso en éxito aparente. | P0 | Corregir tracking y fallo cerrado | PENDIENTE |
| Railway-DB-01 | Migraciones | `schema_migrations` solo contiene `019_schema_migrations.sql`; logs previos muestran que no existía al registrar migraciones anteriores. | P0 | Verificar esquema y registro antes de tocar datos | PENDIENTE |
| H-169/H-170/H-171 | Seeders | Clase de seeder ausente, intentos no resueltos y errores de limpieza pueden no fallar el proceso. | P1 | Endurecer validación; mantener seeders bloqueados en producción | PENDIENTE |
| H-020/H-189 | Workers | Construcción de jobs fuera del try/catch; excepciones de constructor pueden tumbar el worker. | P1 | Corregir ciclo de vida y manejo de fallos | PENDIENTE |
| H-021/H-187 | Redis Streams | No se recuperan mensajes pending abandonados mediante autoclaim. | P1 | Recuperación y pruebas de consumidores | PENDIENTE |
| H-183/H-184/H-185/H-190 | Redis Streams | Métrica de tamaño incorrecta, crecimiento sin trim, DLQ inconsistente y clear apunta a clave incorrecta. | P1 | Corregir ciclo de vida/retención/DLQ | PENDIENTE |
| H-186/H-188/H-192 | Redis Streams | Reprogramación no atómica, contador de intentos reiniciado y ACK antes de confirmar retry/DLQ. | P0 | Garantizar entrega/ACK y pruebas de fallo | PENDIENTE |
| H-172/H-173 | Observabilidad/SMTP | Falta redacción global de PII y SMTP debug puede revelar credenciales. | P0 | Desactivar debug y centralizar redacción | PENDIENTE |
| H-001… | Email | Servicio de correo puede quedar habilitado por defecto sin SMTP; varios jobs ignoran la política central. | P1 | Desactivar temporalmente envíos externos y unificar política | PENDIENTE |
| H-002… | Telegram | Job de Telegram y dependencias de servicio no están resueltos para instanciación directa. | P1 | Desactivar temporalmente o corregir DI | PENDIENTE |
| H-003… | Recompensas | Job de recompensa usa PHPMailer directo y dependencia PDO; puede evitar interruptores generales. | P1 | Desactivar temporalmente o corregir DI/política | PENDIENTE |
| Railway-OPS-01 | Workers | Supervisor declara workers de email, notificaciones y default; configuración efectiva de Railway pendiente. | P1 | Verificar y apagar selectivamente si no son seguros | PENDIENTE |
| Railway-STORAGE-01 | Persistencia | No está confirmado volumen persistente para `/app/storage/uploads`. | P0 | Verificar volumen y plan de backup/restore | PENDIENTE |

> Las referencias `H-001…` y `H-002…` de esta tabla son agrupaciones descriptivas, no nuevos IDs oficiales. Deben sustituirse por los IDs exactos tras cotejar el inventario fuente.

## 4. Inventario por bloques heredados

### H-001 a H-115 — Base v1.5

El inventario previo agrupó riesgos en:
- Correo, SMTP, Telegram, recompensas y dependencias de jobs.
- CI, pruebas, análisis estático y divergencias de despliegue.
- Rutas activas frente a rutas modulares no cargadas; controladores Keeper y middlewares.
- Health, información de infraestructura y filtración entre sedes.
- Reservas, capacidad, transacciones, check-in/out y pagos.
- Waitlist, carrito, slots, confirmación y promociones.
- Catálogo, productos, cuidado animal, soft delete y filtros de disponibilidad.
- Fidelización, recompensas, sellos e idempotencia.
- Reseñas, newsletter, cookies y configuración funcional.
- Tokens, permisos, sesiones, autenticación Bearer y rate limiting.
- Logs, PII, proxy de confianza y consistencia de timezone.

Los títulos/evidencias exactos de esta franja deben recuperarse del documento v1.5 original; no se deben inferir ni renumerar.

### H-116 en adelante — Ampliaciones incorporadas en la consolidación

Referencias conservadas en el contexto de trabajo:

| ID | Área | Resumen conservado |
|---|---|---|
| H-119–H-124 | Waitlist | Payloads, capacidad por tamaño de grupo, reserva de plazas, bloqueo/transición, publicación pre-commit, expiración y estados notificados/promovidos. |
| H-125–H-129 | Loyalty | Validación y uso de códigos, autorización, comprobación de filas afectadas, reversión de sellos e idempotencia/paginación. |
| H-130 | Frontend waitlist | Fallback al primer slot aunque no coincida con la hora solicitada. |
| H-131 | Frontend | Contrato de popup incompatible. |
| H-132 | Auth | Enumeración de correo electrónico. |
| H-133 | Servicios | Resultado de DB ignorado por servicio. |
| H-134–H-136 | API tokens | Tokens permanentes por defecto, posibilidad de emitir otro token con Bearer y tokens expirados mostrados como activos. |
| H-137–H-138 | Cookies | Contrato de consentimiento incompatible y validación booleana insuficiente. |
| H-139–H-144 | Productos | Activación incorrecta, alérgenos, ediciones parciales destructivas, tipo por defecto, calorías y campos de compatibilidad/stock ausentes. |
| H-145–H-147 | Cafés | Campos no actualizados, validación web/API divergente y resultados ignorados. |
| H-150 | Gamificación | Cuenta reservas que no están completadas. |
| H-151–H-153 | Seguridad | Rate limiting global ausente/no atómico y proxy de confianza no configurado. |
| H-154–H-155 | Waitlist | Capacidad de grupo y slots bloqueados no respetados. |
| H-157–H-158 | API/Permisos | Resultado ignorado por MenuApi y granularidad de permisos no aplicada en rutas activas. |
| H-161 | Validación | Uso de `strlen` donde se requiere longitud multibyte. |
| H-163–H-165 | Staff/AuthZ | Asignación no valida rol, sede no exige activo y cambios de rol/permisos no invalidan sesión. |
| H-166 | Calidad | Baseline PHPStan oculta errores reales. |
| H-167–H-171 | Migraciones/seeders | Lock, registro de migraciones, seeders ausentes, reintentos y limpieza. |
| H-172–H-175 | Seguridad/Logging | Redacción PII, SMTP debug, sesión en Bearer y sanitización no recursiva. |
| H-176–H-180 | Scheduler/Timezone/Weather | Scheduler no verificado, timezone de MySQL y cachés/variables de Open-Meteo. |
| H-181–H-182 | Estadísticas/Carrito | Ingresos incluyen reservas no pagadas; carrito no vinculado a sede. |
| H-183–H-192 | Redis Queue | Tamaño, retención, DLQ, delayed jobs, retries, consumer groups, pending y ACK/retry. |

La lista conserva los IDs y resúmenes conocidos, pero no sustituye la evidencia fuente por archivo/línea.

## 5. Decisión por capacidad que puede requerir apagado temporal

| Capacidad | Motivo | Política provisional | Condición para reactivar |
|---|---|---|---|
| Email saliente | SMTP ausente/no verificado y jobs que pueden ignorar flags | Desactivar envíos externos; no eliminar el código | Configuración SMTP probada, política única, idempotencia, logs sin secretos |
| Telegram | Credenciales/dependencias no verificadas y constructor incompatible | Desactivar job | DI resuelta, configuración validada y test de entrega |
| Reward email | Bypass de política general y dependencia directa | Desactivar envío externo de recompensa | Servicio unificado y prueba de no duplicación |
| Workers | Configuración activa declarada sin confirmar dependencias/runtime | No apagar indiscriminadamente; identificar cola y job antes de cada decisión | Health, retry/DLQ, permisos y dependencias verificados |
| Seeders en producción | Riesgo de sobrescritura/borrado de datos | Mantener bloqueados; no usar `FORCE_SEED=1` | Entorno aislado, backup probado y plan explícito |
| Funciones Keeper/Kitchen/Reception | Riesgo de acceso cruzado entre sedes | Restringir rutas sensibles si la evidencia confirma exposición | Autorización por sede/propiedad probada |

La tabla es una propuesta de contención, no una orden de cambio ejecutada.

## 6. Campos obligatorios para el registro definitivo

Cada hallazgo debe tener: ID estable, título, prioridad y justificación, estado, evidencia (archivo/símbolo/líneas o log), impacto, escenario, alcance de datos, sedes/roles afectados, dependencia, decisión aprobada, plan de corrección/desactivación, pruebas, responsable y fecha de verificación.
