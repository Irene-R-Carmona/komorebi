---
mode: agent
description: "Scaffolding completo de un Controller PHP siguiendo todos los patrones del proyecto: declare strict, constructor DI de interfaces, Result pattern, CSRF, Flash, ResponseFactory."
---

# Nuevo Controller — Scaffolding

Voy a crear un nuevo controller siguiendo todos los patrones del proyecto.

## Parámetros

**Nombre del controller:** ${input:controller_name:Ej: ProductController}
**Namespace / rol:** ${input:namespace:Ej: Admin, Public, Manager, Reception, Kitchen, Keeper, Api/V1}
**Descripción breve:** ${input:description:¿Qué gestiona este controller?}

## Lo que voy a generar

### 1. Interface del service (si no existe)
Ubicación: `app/Services/Contracts/${input:controller_name:}ServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ${input:controller_name:}ServiceInterface
{
    // métodos públicos del contrato
}
```

### 2. El Controller
Ubicación: `app/Http/Controllers/${input:namespace:}/${input:controller_name:}.php`

Patrones obligatorios aplicados:
- `declare(strict_types=1)` — primera línea
- Constructor recibe **interface**, no clase concreta
- `ResponseFactory` inyectado para redirects/JSON
- Métodos retornan `?ResponseInterface`
- `Flash::success/error/warning/info` para mensajes
- CSRF en rutas POST/PUT/PATCH/DELETE (configurado en `routes.php`)
- `View::render()` recibe solo escalares/arrays/Raw
- DTOs convertidos con `->toViewArray()` antes de pasar a la vista
- `#[\Override]` donde aplique

### 3. Rutas en `app/routes.php`
Con middleware correcto según el rol:
- `$mw->auth()` para rutas protegidas
- `$mw->role('admin')` según el namespace
- `$mw->csrf()` en todas las rutas mutantes

### 4. Registro en `bootstrap/container.php`
```php
Container::singleton(
    ${input:controller_name:}ServiceInterface::class,
    fn() => new ${input:controller_name:}Service(/* deps */)
);
```

### 5. Tests básicos
En `tests/Unit/Http/Controllers/${input:namespace:}/${input:controller_name:}Test.php` con el docblock obligatorio.

## Checklist de validación

- [ ] `declare(strict_types=1)` presente
- [ ] Constructor inyecta interface, no clase concreta
- [ ] Todos los métodos retornan `?ResponseInterface`
- [ ] `View::render()` no recibe objetos directamente
- [ ] Flash messages usan helpers semánticos
- [ ] Rutas mutantes tienen `$mw->csrf()`
- [ ] `make phpstan` pasa sin errores nuevos

---
**Referencia:** `.github/instructions/php-backend.instructions.md` | `AGENTS.md` Critical Patterns

