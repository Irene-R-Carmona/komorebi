---
mode: agent
description: "Scaffolding de Service + Interface + ServiceProvider siguiendo el Result pattern. Incluye logging con nivel correcto, DTOs si necesario, y registro en el container."
---

# Nuevo Service — Scaffolding con Result Pattern

Voy a crear un nuevo service con su interface y registro en el container.

## Parámetros

**Nombre del service:** ${input:service_name:Ej: ReservationService}
**Descripción breve:** ${input:description:¿Qué responsabilidad tiene este service?}
**¿Necesita DTO?:** ${input:needs_dto:sí/no}

## Lo que voy a generar

### 1. Interface del contrato
Ubicación: `app/Services/Contracts/${input:service_name:}Interface.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ${input:service_name:}Interface
{
    public function execute(/* parámetros */): Result;
}
```

### 2. El Service
Ubicación: `app/Services/${input:service_name:}.php`

Patrones obligatorios:
- `declare(strict_types=1)` — primera línea
- Extiende `BaseService` para heredar helpers de logging
- **Nunca lanza excepciones para fallos esperados** — retorna `Result::fail()`
- **Solo retorna `Result::ok($data)` o `Result::fail($msg, $code)`**
- Logging con nivel correcto:
  - `$this->logInfo()` — operaciones exitosas con side effects
  - `$this->logWarning()` — fallos de negocio / validaciones
  - `$this->logError()` — fallos inesperados (bloques catch)
- Nunca loguea contraseñas, tokens, ni emails completos

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Result;
use App\Services\Contracts\${input:service_name:}Interface;

final class ${input:service_name:} extends BaseService implements ${input:service_name:}Interface
{
    public function __construct(
        private readonly SomeDependencyInterface $dependency,
    ) {}

    public function execute(/* params */): Result
    {
        try {
            // lógica de negocio
            $this->logInfo('[${input:service_name:}] Executed successfully', ['context' => '...']);
            return Result::ok($data);
        } catch (\Throwable $e) {
            $this->logError('[${input:service_name:}] Unexpected failure', ['exception' => $e->getMessage()]);
            return Result::fail('Ha ocurrido un error inesperado.', 'unexpected_error');
        }
    }
}
```

### 3. DTO (si aplica)
Ubicación: `app/Domain/DTO/${input:service_name:}DTO.php`

```php
final readonly class ${input:service_name:}DTO
{
    public static function fromArray(array $data): self { /* ... */ }
    public function toViewArray(): array { return [/* solo escalares */]; }
}
```

### 4. Registro en `bootstrap/container.php`

```php
Container::singleton(
    ${input:service_name:}Interface::class,
    fn() => new ${input:service_name:}(
        Container::make(DependencyInterface::class),
    )
);
```

### 5. Tests unitarios
En `tests/Unit/Services/${input:service_name:}Test.php`:
- Docblock obligatorio (3 preguntas)
- `createStub()` para dependencias — nunca clases reales
- Aserciones sobre `$result->ok`, `$result->data`, `$result->getMessage()`

## Checklist de validación

- [ ] Interface en `app/Services/Contracts/`
- [ ] Service es `final` y extiende `BaseService`
- [ ] Todos los paths retornan `Result`
- [ ] Logging usa nivel correcto
- [ ] Registrado en `bootstrap/container.php`
- [ ] Test con docblock + aserciones sobre Result
- [ ] `make phpstan` pasa sin errores nuevos

---
**Referencia:** `.github/instructions/php-backend.instructions.md` | `app/Core/Result.php`

