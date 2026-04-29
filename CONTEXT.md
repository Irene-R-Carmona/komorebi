# Komorebi Café

Sistema de gestión para un café con animales. Los clientes hacen **Reservas** para visitar el café en **Slots** de tiempo, con **Aforo** limitado. Los **Keepers** cuidan a los animales, los **Supervisores** coordinan operaciones, y el personal de **Recepción** gestiona llegadas. Los pedidos se muestran en **KDS** al personal de cocina.

## Language

**Reserva** (Reservation):
Una reserva de cliente para visitar el café en un Slot concreto. Puede estar en estado `pending`, `confirmed`, `cancelled`, o `completed`. Tiene asociado un código QR para check-in.
_Avoid_: booking, appointment, cita

**Slot**:
Ventana temporal disponible para hacer Reservas (ej. "11:00–12:00 el lunes"). Tiene un Aforo máximo y puede estar activo o inactivo.
_Avoid_: franja horaria, time window, bloque

**Aforo**:
Número máximo de clientes que pueden ocupar un Slot simultáneamente. Es un atributo del Slot.
_Avoid_: capacidad, capacity, límite

**Waitlist** (Lista de espera):
Cola de clientes que esperan a que se libere una plaza en un Slot lleno. Cuando se cancela una Reserva, el primer cliente de la Waitlist recibe notificación automática.
_Avoid_: waiting list, cola de espera

**Ficha**:
Perfil de un animal del café: nombre, especie, descripción, fotos, estado de salud. Es la entidad central del módulo de animales.
_Avoid_: animal profile, perfil de animal, animal record

**Check de salud** (Health check):
Inspección periódica registrada por un Keeper sobre el estado de una Ficha. Incluye peso, observaciones y fecha.
_Avoid_: health record, revisión, revisión veterinaria

**Incidente**:
Evento adverso relacionado con una Ficha (herida, comportamiento anómalo, etc.). Tiene estado (`open`, `resolved`) y puede escalar a supervisión.
_Avoid_: incident report, reporte, evento adverso

**Turno** (Shift):
Período de trabajo de un empleado (inicio, fin, rol). Los Turnos se gestionan desde el módulo de operaciones (`FEATURE_OPS`).
_Avoid_: jornada, horario, work period

**Supervisor**:
Rol de usuario con permiso para supervisar operaciones: asignar Turnos, revisar Incidentes y coordinar Keepers y Recepción.
_Avoid_: manager (usar para el rol RBAC `manager`), encargado

**Keeper**:
Rol de usuario responsable del bienestar animal: registrar Checks de salud, gestionar Fichas, y reportar Incidentes.
_Avoid_: cuidador, animal caretaker, zookeeper

**Recepción** (Reception):
Módulo y rol de usuario que gestiona la llegada de clientes: validar código QR de Reserva, marcar check-in, gestionar walk-ins.
_Avoid_: front desk, entrada, arrival desk

**KDS** (Kitchen Display System):
Pantalla en cocina que muestra los pedidos en tiempo real. Es un módulo de vista especializado para el personal de cocina.
_Avoid_: kitchen screen, pantalla de cocina, order display

**Backoffice**:
Panel de administración protegido por el rol `admin` y `manager`. Gestiona usuarios, Fichas, Slots, configuraciones del sistema y métricas. Activado por `FEATURE_BACKOFFICE`.
_Avoid_: admin panel, panel de administración, dashboard (genérico)

**Loyalty**:
Sistema de puntos de fidelización. Los clientes acumulan puntos por Reservas completadas y los canjean por recompensas. Tiene una columna generada en BD para el total de puntos.
_Avoid_: rewards program, puntos, points system

**Stock**:
Inventario de productos del café (bebidas, merchandise). Se gestiona desde el Backoffice y puede disparar alertas de bajo stock.
_Avoid_: inventario, inventory, producto disponible

**Review**:
Valoración de un cliente sobre su visita. Un cliente solo puede dejar una Review por visita completada (restricción única por reserva).
_Avoid_: reseña, rating, valoración

**Token API** (API Token):
Credencial de autenticación para el acceso programático a `/api/v1/`. Tiene scopes y fecha de expiración.
_Avoid_: API key, credencial, access token

**Newsletter**:
Lista de suscriptores de email para comunicaciones del café. La suscripción/baja es independiente del registro de usuario.
_Avoid_: mailing list, lista de correo, email list

**Rol** (RBAC Role):
Nivel de acceso de un usuario en el sistema. Valores: `admin`, `manager`, `supervisor`, `reception`, `kitchen`, `keeper`, `user`.
_Avoid_: permiso, permission, nivel de acceso

**Result**:
Tipo de retorno de todos los servicios. `Result::ok($data)` para éxito, `Result::fail($msg, $code)` para fallo esperado. Nunca se lanzan excepciones para fallos de negocio.
_Avoid_: response object, return value, DTO de resultado

## Relationships

- Un **Slot** tiene un **Aforo** y puede tener muchas **Reservas**
- Una **Reserva** pertenece a un **Slot** y a un usuario con **Rol** `user`
- Un **Slot** lleno genera una **Waitlist**; cancelar una **Reserva** activa la Waitlist
- Una **Ficha** puede tener muchos **Checks de salud** e **Incidentes**
- Un **Keeper** gestiona **Fichas** y registra **Checks de salud** e **Incidentes**
- Un **Supervisor** supervisa **Turnos** y escala **Incidentes**
- La **Recepción** valida **Reservas** en la llegada del cliente
- Un usuario puede dejar una **Review** por **Reserva** completada
- El **KDS** muestra pedidos en tiempo real al personal de cocina (rol `kitchen`)
- El **Backoffice** gestiona **Fichas**, **Slots**, **Stock** y configuración global
- El **Loyalty** acumula puntos por **Reservas** completadas

## Flagged ambiguities

- "manager" es ambiguo: puede referirse al rol RBAC `manager` (acceso a Backoffice) o coloquialmente al responsable del café. Usar **Supervisor** para el rol operativo y `manager` solo para el rol RBAC.
- "dashboard" se usa coloquialmente para cualquier panel, pero en el código es el controlador `DashboardController` bajo `/admin`. Usar **Backoffice** para el módulo completo.
