# Diagrama de Arquitectura — C4 Context y Containers

Vista de alto nivel de la arquitectura de Komorebi Café usando la notación C4. El primer diagrama muestra el contexto del sistema y sus actores externos; el segundo descompone el sistema en sus containers de infraestructura y procesos.

---

## Diagrama de Contexto (C4 Level 1)

Muestra quién usa el sistema y con qué sistemas externos se integra.

```mermaid
C4Context
    title Diagrama de Contexto — Komorebi Café

    Person(usuario, "Usuario / Visitante", "Navega el café online, realiza reservas y consulta el menú desde el navegador.")
    Person(staff, "Staff", "Personal del café con roles: admin, manager, recepción, cocina o cuidador de animales.")

    System(app, "Komorebi Café App", "Aplicación web PHP 8.4 con framework MVC personalizado. Gestiona reservas, menú, usuarios, animales y operaciones diarias.")

    System_Ext(telegram, "Telegram Bot API", "API externa de mensajería para notificaciones opcionales vía bot.")
    System_Ext(smtp, "SMTP Server", "Servidor de correo electrónico. Mailpit en desarrollo; SMTP real en producción.")

    Rel(usuario, app, "Usa", "HTTPS / Navegador")
    Rel(staff, app, "Administra y opera", "HTTPS / Navegador")
    Rel(app, telegram, "Envía notificaciones", "HTTPS / Bot API")
    Rel(app, smtp, "Envía correos transaccionales", "SMTP")
```

---

## Diagrama de Containers (C4 Level 2)

Desglosa el sistema en sus containers: procesos ejecutables, bases de datos y servicios auxiliares.

```mermaid
C4Container
    title Diagrama de Containers — Komorebi Café

    Person(browser, "Navegador del Usuario / Staff", "")

    Container(web, "FrankenPHP / Caddy", "HTTP Server · PHP 8.4", "Front controller en :8080. Ejecuta el router, el pipeline de middleware PSR-15, controllers, services y repositories del framework MVC.")

    ContainerDb(mysql, "MySQL 8.4", "Base de datos relacional", "Almacena todos los datos del dominio: usuarios, reservas, menú, roles, eventos y animales.")
    ContainerDb(redis, "Redis 8", "Cache + Colas de trabajo", "Cache de sesiones y colas de jobs asíncronos: 'default', 'emails' y notificaciones.")

    Container(worker, "Queue Worker", "bin/worker.php", "Proceso de larga duración supervisado. Consume la cola 'default' de Redis.")
    Container(emailWorker, "Email Worker", "bin/email-worker.php", "Proceso de larga duración supervisado. Consume la cola 'emails' y despacha correos transaccionales.")
    Container(notifWorker, "Notification Worker", "bin/notification-worker.php", "Proceso de larga duración supervisado. Consume la cola de notificaciones y llama a la API de Telegram.")

    Container(mailpit, "Mailpit", "SMTP trap · Solo desarrollo", "Captura todos los correos salientes en el entorno de desarrollo. UI disponible en :8025.")

    System_Ext(telegram, "Telegram Bot API", "API de mensajería externa")
    System_Ext(smtpProd, "SMTP Server (producción)", "Servidor de correo en producción")

    Rel(browser, web, "Peticiones HTTP/HTTPS", ":8080")
    Rel(web, mysql, "Lee y escribe datos del dominio", "PDO · TCP 3306")
    Rel(web, redis, "Cache y encola jobs asíncronos", "TCP 6379")
    Rel(worker, redis, "Consume cola 'default'", "TCP 6379")
    Rel(emailWorker, redis, "Consume cola 'emails'", "TCP 6379")
    Rel(emailWorker, mailpit, "Envía correos en dev", "SMTP 1025")
    Rel(emailWorker, smtpProd, "Envía correos en prod", "SMTP 587/465")
    Rel(notifWorker, redis, "Consume cola de notificaciones", "TCP 6379")
    Rel(notifWorker, telegram, "Envía mensajes al bot", "HTTPS / Bot API")
```

---

## Leyenda y Notas

| Container          | Puerto | Entorno         |
|--------------------|--------|-----------------|
| FrankenPHP / Caddy | 8080   | Todos           |
| MySQL              | 3306   | Todos           |
| Redis              | 6379   | Todos           |
| Mailpit SMTP       | 1025   | Solo desarrollo |
| Mailpit UI         | 8025   | Solo desarrollo |

- Los tres **workers** son procesos PHP de larga duración supervisados por **Supervisor** (`docker/supervisor.conf`).
- La configuración se inyecta exclusivamente via variables de entorno (**12-Factor III**).
- Los secretos se resuelven con `SecretLoader::require('key')`: primero env var, luego `/run/secrets/<key>`.
- Todo el **estado de sesión** se almacena en Redis para soportar escalado horizontal sin estado compartido en disco.
