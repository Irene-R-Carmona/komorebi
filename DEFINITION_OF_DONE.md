# Definition of Done — Komorebi Café

Un cambio solo se considera **Done** cuando todos los ítems de la categoría correspondiente están marcados. No hay excepciones.

---

## Category 1: Feature (nueva funcionalidad)

- [ ] Tests unitarios escritos y pasando (`make test`)
- [ ] PHPStan sin errores nuevos (`make phpstan`)
- [ ] Psalm sin errores nuevos (`make psalm`)
- [ ] Estilo de código validado (`make cs-check`)
- [ ] Si afecta a rutas: añadida en `app/routes.php` con middleware correcto (`$mw->auth()`, `$mw->role()`, `$mw->csrf()` según corresponda)
- [ ] Si crea un servicio: registrado en `bootstrap/container.php` mediante `Container::singleton()`
- [ ] Si modifica el esquema de BD: migration SQL nueva en `migrations/` con formato `NNN_nombre.sql` e idempotente (`IF NOT EXISTS` / `IF EXISTS`)
- [ ] Si añade un evento: listener registrado en `app/Providers/EventServiceProvider.php` dentro de `boot()`
- [ ] El controlador declara tipo de retorno `?ResponseInterface` — no contiene llamadas a `header()` ni a `exit`
- [ ] Los métodos de servicio afectados devuelven `Result::ok()` o `Result::fail()` — no lanzan excepciones para fallos de negocio
- [ ] Flash messages usan `Flash::success()`, `Flash::error()`, `Flash::warning()` o `Flash::info()` — ninguna llamada a `Flash::set()`
- [ ] Variables de entorno nuevas documentadas en `.env.example` y en la sección correspondiente de `README.md`
- [ ] `make ci` pasa completamente (sin errores ni warnings nuevos) antes del merge

---

## Category 2: Bugfix (corrección de error)

- [ ] Test de regresión añadido en `tests/Unit/` o `tests/Integration/` que reproduce el bug en rojo antes del fix
- [ ] El mismo test pasa en verde tras aplicar el fix
- [ ] `make ci` pasa completamente
- [ ] Si el bug tenía impacto en seguridad: entrada añadida en `CHANGELOG.md` bajo la sección `Security`

---

## Category 3: Migración de BD (cambio de esquema)

- [ ] Archivo SQL nuevo en `migrations/` con nombre `NNN_nombre.sql` (siguiente número de la secuencia)
- [ ] Todas las sentencias son idempotentes: `CREATE TABLE IF NOT EXISTS`, `DROP TABLE IF EXISTS`, `ALTER TABLE … ADD COLUMN IF NOT EXISTS`, etc.
- [ ] Comprobado que `make db-migrate` termina sin errores en una base de datos limpia (volumen recién creado)
- [ ] Comprobado que `make db-migrate` termina sin errores al ejecutarse por segunda vez en la misma base de datos (idempotencia)
- [ ] `migrations/README.md` actualizado: número de migración, fecha, descripción del cambio y tablas afectadas
- [ ] Si la migración elimina o transforma datos existentes: procedimiento de backup documentado en el PR o warning explícito en el body del PR

---

## Category 4: Cambio de seguridad

- [ ] Tests específicos para el vector de ataque mitigado (autenticación, autorización, inyección, CSRF…)
- [ ] No se exponen nuevos puertos en `docker-compose.yml` sin justificación documentada en el PR
- [ ] Secretos nuevos gestionados mediante `SecretLoader::require('nombre_secreto')` — ningún valor sensible hardcodeado en código o configuración versionada
- [ ] Si se añade un endpoint de API: protegido con los middlewares apropiados (`$mw->api()`, `$mw->auth()`, `$mw->csrf()`, `$mw->role()`)
- [ ] Entrada añadida en `CHANGELOG.md` bajo la sección `Security` describiendo la vulnerabilidad mitigada
