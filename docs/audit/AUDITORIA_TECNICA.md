# Auditoría técnica de Komorebi

**Versión documental:** 2.1 (consolidación de trabajo; recuentos pendientes de reconciliación)  
**Fecha:** 2026-11-22  
**Repositorio:** `Irene-R-Carmona/komorebi`  
**Rama inspeccionada:** `develop`  
**Commit inspeccionado:** `a2e24936de3706c0709f08e3b8fe2cef2a24fa0d`

## 1. Objetivo y restricción de alcance

Identificar fallos, riesgos de seguridad, integridad de negocio, datos, infraestructura, operación y funcionalidades incompletas antes de decidir qué corregir, qué desactivar temporalmente y qué mantener como capacidad futura.

**No se ha implementado ninguna corrección en esta entrega.** Tampoco se han cambiado Railway, variables, DB, migraciones, jobs, workers, despliegues ni datos.

## 2. Alcance de la evidencia disponible

### 2.1 Repositorio

Se confirmó que `develop` apunta a `a2e24936de3706c0709f08e3b8fe2cef2a24fa0d` (commit `Update README.md`, 2026-06-13). El árbol consultado contiene 1.501 blobs. En la carpeta `docs/` se localizaron inicialmente documentos de arquitectura y despliegue, pero no un directorio de auditoría previo. El commit y la rama deben volver a comprobarse antes de cualquier implementación.

La revisión estática previa alcanzó controladores, servicios, repositorios, DTOs, colas, workers, configuración Docker/Supervisor, migraciones, CI y frontend. Los hallazgos heredados H-001 a H-115 se conservan como referencias históricas de esa revisión; no se ha vuelto a inspeccionar cada línea del repositorio en esta actualización documental.

### 2.2 Railway y base de datos

Evidencia operativa aportada:
- `APP_ENV=production`; MySQL accesible desde el servicio.
- El proceso `apply-db.php` informó `OK: Migraciones SQL aplicadas`.
- El log informó que los seeders se desactivan en producción para proteger datos reales y que `FORCE_SEED=1` permite forzarlos.
- El usuario comprobó que las tablas consultadas estaban vacías.
- `schema_migrations` contenía únicamente `019_schema_migrations.sql`, con fecha 2026-11-19 16:45:24 y 53 ms.
- Se observaron avisos de tabla `schema_migrations` inexistente al intentar registrar migraciones anteriores a la 019.

Interpretación: hay indicios sólidos de que las migraciones se ejecutan sin quedar correctamente registradas hasta que se crea la tabla de tracking. Esto puede provocar reejecuciones. Debe confirmarse el comportamiento exacto del script y el estado completo del esquema antes de cualquier cambio.

### 2.3 Configuración y runtime pendientes

No se ha confirmado desde el panel actual:
- Comando efectivo de build/start/pre-deploy de Railway.
- Si Railway ejecuta tanto el `preDeployCommand` como las migraciones del entrypoint.
- Número real de réplicas/procesos de workers y colas activas.
- Estado, permisos y política de reinicio del Supervisor en runtime.
- Existencia y montaje de volumen persistente para `/app/storage/uploads`.
- Estado de Redis, backlog, pending entries, DLQ y retención.
- Estado efectivo de SMTP, Telegram, Cloudinary, Mercure y Open-Meteo.
- Salud pública/privada de la aplicación mediante pruebas controladas.

No se ha podido realizar una auditoría dinámica completa del servicio público.

## 3. Conclusión ejecutiva

El repositorio presenta riesgos que afectan a seguridad, aislamiento entre sedes, integridad de reservas y pagos, autorización, colas, migraciones, notificaciones, catálogo, fidelización, reseñas, API y observabilidad. Además, hay capacidades configuradas o expuestas cuya operatividad no está demostrada.

La prioridad inmediata no es añadir funcionalidades: es completar la auditoría, proteger los datos existentes, decidir qué procesos deben quedar temporalmente desactivados y establecer una base verificable para corregir.

No se debe asumir que todo lo incompleto deba eliminarse. Para cada capacidad se decidirá entre:
- corregirla y mantenerla;
- desactivarla temporalmente de forma segura;
- restringirla a un entorno demo controlado;
- retirarla solo si existe decisión de producto y análisis de dependencias.

## 4. Riesgos principales identificados

### 4.1 Migraciones, seeders y persistencia

- La tabla de tracking `schema_migrations` se crea en una migración ordenada después de las migraciones que intentan registrarse.
- El script puede advertir fallos de registro y continuar con éxito aparente.
- Hay riesgo de doble ejecución entre pre-deploy y entrypoint.
- Los seeders se bloquean en producción por defecto; no activar `FORCE_SEED=1` para resolver tablas vacías.
- La persistencia de uploads no está confirmada.

### 4.2 Jobs, workers y notificaciones

- El envío de correo no tiene una política centralizada fiable: existen caminos que pueden intentar SMTP aunque no esté configurado.
- `RewardUnlockedJob` puede usar PHPMailer directamente y saltarse el interruptor general.
- Los jobs de Telegram y recompensa presentan problemas de construcción/inyección de dependencias con workers que instancian clases sin DI.
- Supervisor declara workers de emails, notifications y default; falta verificar que deban estar activos y que sus dependencias estén completas.
- La política de ACK/retry/DLQ de Redis Streams tiene riesgos de pérdida, duplicación, mensajes pendientes y crecimiento no acotado.

**Medida cautelar propuesta, pendiente de autorización:** impedir envíos externos de correo/Telegram hasta verificar configuración, dependencias, consentimiento, idempotencia y observabilidad. La desactivación debe ser explícita, centralizada y reversible; no basta con confiar en que falten variables.

### 4.3 Seguridad y aislamiento

- Endpoints y servicios de Keeper/Kitchen/Reception requieren revisión de autorización por sede y propiedad del recurso.
- Se detectaron rutas con potencial acceso cruzado entre sedes.
- Health y logs pueden exponer información de infraestructura o datos de otras sedes.
- La identidad Bearer y la sesión web no siempre siguen el mismo contexto.
- Faltan límites globales o robustos de rate limiting y redacción global de PII.
- La gestión de tokens, sesiones y permisos necesita invalidación y comprobaciones consistentes.

### 4.4 Reservas, capacidad, waitlist y pagos

- La capacidad puede comprobarse sin contemplar correctamente el tamaño del grupo o sin protección frente a concurrencia.
- La confirmación de waitlist confía en datos de producto/precio/duración enviados por cliente.
- Promociones de waitlist carecen de bloqueo/transición condicional suficiente y pueden publicar eventos antes del commit.
- Hay incoherencias de estado, expiración y consultas de entradas notificadas/promovidas.
- Cancelaciones, no-show, pago y reembolso tienen caminos no transaccionales o sin persistencia efectiva del reembolso.
- El carrito de invitado puede devolver un placeholder vacío y el carrito no está suficientemente vinculado a sede.

### 4.5 Catálogo, cuidado animal y fidelización

- Consultas de productos/animales no filtran de forma uniforme registros soft-deleted o inactivos.
- Ediciones parciales de productos pueden sobrescribir datos no enviados.
- La relación de alérgenos y ciertos atributos no se sincronizan/persisten correctamente.
- Operaciones de cuidado animal requieren validar resultados y autoría.
- Fidelización puede marcar recompensas o reservas como procesadas aunque la operación subyacente falle; falta idempotencia.

### 4.6 Reseñas, newsletter y cookies

- El modelo/servicio de reseñas tiene incompatibilidades con la reserva obligatoria, límites y recálculo de rating.
- Verificación y baja de newsletter usan GET para mutar estado y presentan riesgo con escáneres de correo.
- El frontend y backend no comparten el mismo contrato de consentimiento de cookies; validación booleana insuficiente.

### 4.7 Calidad, CI y configuración

- No hay cobertura de pruebas automatizadas suficiente para PHPUnit/Playwright y flujos críticos.
- La auditoría Composer puede quedar neutralizada por `|| true`.
- PHPStan baseline puede ocultar errores reales.
- Documentación de CI/CD y despliegue no está totalmente alineada.
- Hay variables de servicios externos ausentes o aparentemente no utilizadas.
- Algunas métricas pueden contar reservas no pagadas como ingresos.

## 5. Registro detallado y trazabilidad

El inventario asociado mantiene las referencias heredadas H-001 a H-115 y las incorporaciones H-116 en adelante. Los identificadores no son una prueba de que cada entrada se haya vuelto a validar en este commit.

La consolidación anterior comunicó 174 hallazgos únicos (59 nuevos independientes y 18 ampliaciones/duplicidades fusionadas respecto de una base de 115). **Estos totales son provisionales**: el documento v2.0 original no está disponible en el entorno para reconciliar todos los encabezados, fusiones y prioridades. No usar el reparto P0/P1/P2 previo como métrica cerrada hasta completar esa reconciliación.

## 6. Qué falta para cerrar la auditoría

1. Recuperar y cotejar el documento fuente v1.5 y la consolidación v2.0.
2. Verificar cada hallazgo contra la versión actual del repositorio y anotar archivo, símbolo y evidencia reproducible.
3. Reconciliar duplicados, referencias fusionadas y prioridad.
4. Completar evidencia de Railway: logs de arranque, migraciones y workers; configuración efectiva de servicios; volumen persistente.
5. Identificar cada job por estado: operativo, defectuoso, sin dependencias, no configurado o no requerido.
6. Confirmar qué capacidades deben permanecer disponibles en producción y cuáles pueden quedar temporalmente apagadas.
7. Definir criterios de cierre por hallazgo y pruebas necesarias.

**Criterio de cierre:** no se declarará completa la auditoría hasta que cada hallazgo tenga evidencia, prioridad justificada, decisión (corregir/desactivar/restringir/retirar), dependencias, riesgo de datos y prueba de aceptación.
