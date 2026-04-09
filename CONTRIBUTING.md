# Contributing — Komorebi Café

Gracias por contribuir. Este documento describe el flujo de trabajo, las convenciones y los patrones que se aplican en todas las contribuciones al proyecto.

---

## Convención de nombres de rama

| Prefijo                     | Cuándo usarlo                                  |
|-----------------------------|------------------------------------------------|
| `feature/short-description` | Nueva funcionalidad                            |
| `fix/short-description`     | Corrección de bug                              |
| `docs/short-description`    | Solo cambios de documentación                  |
| `chore/short-description`   | Mantenimiento: dependencias, configuración, CI |

Ejemplos válidos: `feature/loyalty-redemption`, `fix/reservation-overlap`, `docs/update-openapi-spec`.

---

## Desarrollo local

El proyecto corre **completamente dentro de Docker**. No se instala PHP ni Composer en el host.

1. **Levantar el stack**
   ```bash
   make dev
   ```
   Arranca los contenedores: PHP 8.4 (FrankenPHP), MySQL 8.4, Redis 8 y Mailpit.

2. **Acceder al contenedor**
   ```bash
   make bash
   ```
   Todos los comandos `make` que invocan PHP deben ejecutarse desde aquí o via `docker compose exec app <cmd>`.

3. **Aplicar migraciones**
   ```bash
   make db-migrate
   ```

4. **URLs locales**
   - Aplicación: `http://localhost:8080`
   - Mailpit (emails de desarrollo): `http://localhost:8025`

5. **Ejecutar tests**
   ```bash
   make test          # unitarios + integración
   make phpstan       # análisis estático PHPStan nivel 5
   make psalm         # análisis estático Psalm nivel 5
   make cs-check      # validación de estilo PSR-12
   make ci            # todos los pasos anteriores en secuencia
   ```

---

## Proceso de Pull Request

1. Crear la rama desde `main` (o `develop` si existe) siguiendo la convención de nombres.
2. Implementar el cambio garantizando que `make ci` pasa **sin errores ni warnings nuevos**.
3. Abrir el PR con:
   - Descripción clara del cambio y su motivación.
   - Checklist de `DEFINITION_OF_DONE.md` con los ítems de la categoría aplicable.
   - Referencia al issue relacionado si existe.
4. Al menos **1 review aprobatorio** antes de hacer merge.
5. No hacer squash de commits que contengan tests: el historial de TDD aporta contexto.

---

## Patrones clave del proyecto

> Para la referencia completa ver [`AGENTS.md`](AGENTS.md).

- **Servicios devuelven `Result`**, nunca lanzan excepciones para fallos de negocio esperados.
  ```php
  return Result::ok($data);
  return Result::fail('Mensaje de error', 'error_code');
  ```
- **Controladores devuelven `?ResponseInterface`**, nunca usan `header()` ni `exit`.
  ```php
  public function store(ServerRequestInterface $request): ResponseInterface
  {
      return $this->response->redirect('/admin/items');
  }
  ```
- **Variables de entorno** accedidas siempre via `Env::get('KEY')`, nunca con `getenv()` directamente.
- **Secretos** gestionados via `SecretLoader::require('nombre')`, nunca hardcodeados.

---

## Estilo de código

- Estándar: **PSR-12**.
- Validar antes de commit: `make cs-check`.
- Corregir automáticamente: `make cs-fix`.
- Obligatorio en **cada fichero PHP**:
  ```php
  <?php

  declare(strict_types=1);
  ```
- Análisis estático: **PHPStan nivel 5** + **Psalm nivel 5** (`make phpstan`, `make psalm`).
