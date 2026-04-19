# Configuración de PhpStorm — Komorebi Café

## 1. PHP Interpreter (Remote — Docker)

> ⚠️ **Requisito previo**: Docker Desktop debe estar corriendo y `docker compose up -d` activo.

1. **Settings → PHP → CLI Interpreter** → `+` → **From Docker, Vagrant, VM, WSL, Remote…**
2. Seleccionar **Docker Compose**
3. Server: **Docker** (si no aparece, crearlo primero en Settings → Build → Docker → `+` → Docker for Windows)
4. Configuration files: `./docker-compose.yml` (PhpStorm lo detecta automáticamente)
5. Service: **app**
6. **Environment variables**: dejar vacío (se leen del `.env`)
7. Clic **OK** → PhpStorm conecta al contenedor y detecta PHP 8.4
8. De vuelta en CLI Interpreter, verificar:
    - **PHP executable**: debe mostrar algo como `php` (dentro del contenedor)
    - **PHP version**: `8.4.x` ✅
    - Clic en 🔄 si no aparece la versión
9. En la pantalla **Settings → PHP**:
    - **CLI Interpreter**: el que acabas de crear
    - **Path mappings**: `F:\komorebi_backup` → `/app`
    - ⚠️ Si el mapping no aparece automáticamente, añadirlo manualmente

> **Importante**: Este intérprete es la base para PHPUnit, PHP CS Fixer y PHPStan.
> Si el intérprete no funciona, ninguna Quality Tool funcionará.

### Verificar que el intérprete funciona

1. Settings → PHP → CLI Interpreter → seleccionar el intérprete → clic en `🔄`
2. Debe mostrar la versión de PHP. Si da error:
    - Docker Desktop está corriendo? → `docker info` en terminal
    - Contenedor activo? → `docker compose ps` debe mostrar `komorebi_app` como `running`
    - Rebuild si es necesario: `docker compose build app && docker compose up -d`

## 2. PHP Language Level

- **Settings → PHP** → PHP Language Level: **8.4**
- Include paths: marcar `vendor/` como Library Root (debería detectarse solo con Composer)

## 3. Composer

- **Settings → PHP → Composer**
- Interpreter: el remoto Docker configurado en paso 1
- Path to composer.json: `composer.json` (raíz del proyecto)

## 4. PHP CS Fixer (Estilo de Código)

### Paso 1 — Registrar el binario en Quality Tools

1. **Settings → PHP → Quality Tools**
2. Expandir **PHP CS Fixer** → clic en `…` (configuración)
3. **CLI Interpreter**: seleccionar el intérprete Docker Compose del paso 1
4. **PHP CS Fixer path**: `/app/vendor/bin/php-cs-fixer`
    - ⚠️ Ruta **dentro del contenedor**, NO ruta Windows
5. Clic en **Validate** → debe mostrar la versión del fixer ✅
    - Si falla: verificar que Docker está corriendo y que `composer install` se ejecutó dentro del contenedor

### Paso 2 — Habilitar la inspección

1. **Settings → Editor → Inspections → PHP → Quality tools → PHP CS Fixer validation**
2. Habilitar ✅
3. **Coding standard**: Custom → clic en `…` → seleccionar `/app/.php-cs-fixer.php`
    - ⚠️ De nuevo, ruta **dentro del contenedor**
    - Alternativamente: seleccionar la ruta local `F:\komorebi_backup\.php-cs-fixer.php` si PhpStorm lo permite
4. Severity: **Warning**

### Troubleshooting PHP CS Fixer

| Síntoma                            | Solución                                                                                         |
|------------------------------------|--------------------------------------------------------------------------------------------------|
| "PHP CS Fixer not found"           | Path debe ser `/app/vendor/bin/php-cs-fixer` — no usar ruta Windows                              |
| "Validate" falla con timeout       | Docker Desktop corriendo? Contenedor `komorebi_app` activo? (`docker compose ps`)                |
| "Cannot read configuration"        | Verificar que `.php-cs-fixer.php` existe en raíz y el path mapping `F:\komorebi_backup` → `/app` |
| Inspección no muestra warnings     | Verificar que la inspección está habilitada en Editor → Inspections                              |
| Warnings incorrectos o inesperados | Asegurar que el Coding Standard apunta a `.php-cs-fixer.php` (custom), no a PSR-12 genérico      |

### Reglas clave que aplica el fixer (para entender los warnings):

| Regla                        | Efecto                                                 |
|------------------------------|--------------------------------------------------------|
| `import_classes => true`     | Clases globales con `use PDO;` — nunca `\PDO`          |
| `import_functions => false`  | Funciones nativas con `\time()` — nunca `use function` |
| `declare_strict_types`       | Obliga `declare(strict_types=1);` en cada archivo      |
| `native_function_invocation` | `\array_map()`, `\trim()`, `\count()` etc.             |
| `final_class`                | Añade `final` a clases que no se extienden             |
| `ordered_imports`            | Imports alfabéticos: class → function → const          |
| `no_unused_imports`          | Elimina `use` sin uso                                  |

### Formateo on-save (recomendado):

- **Settings → Tools → Actions on Save**
    - ✅ Reformat code
    - ✅ Run php-cs-fixer (si está disponible como External Tool)

## 5. PHPStan (Análisis Estático)

### Paso 1 — Registrar el binario en Quality Tools

1. **Settings → PHP → Quality Tools**
2. Expandir **PHPStan** → clic en `…` (configuración)
3. **CLI Interpreter**: seleccionar el intérprete Docker Compose del paso 1
4. **PHPStan path**: `/app/vendor/bin/phpstan`
    - ⚠️ Ruta **dentro del contenedor**, NO ruta Windows
5. Clic en **Validate** → debe mostrar la versión ✅

### Paso 2 — Habilitar la inspección

1. **Settings → Editor → Inspections → PHP → Quality tools → PHPStan validation**
2. Habilitar ✅
3. **Configuration file**: `/app/phpstan.neon`
    - ⚠️ Ruta dentro del contenedor
4. **Level**: dejar vacío (ya definido como 5 en `phpstan.neon`)
5. Severity: **Warning**

### Troubleshooting PHPStan

| Síntoma                          | Solución                                                                            |
|----------------------------------|-------------------------------------------------------------------------------------|
| "PHPStan not found"              | Path debe ser `/app/vendor/bin/phpstan` — verificar con `Validate`                  |
| "Validate" falla                 | Mismas verificaciones de Docker que PHP CS Fixer (contenedor activo, path mappings) |
| No detecta errores que sí ve CLI | Verificar que Configuration file apunta a `/app/phpstan.neon`                       |
| Errores de memoria               | PHPStan en IDE usa menos memoria; si falla, usar External Tool con `--memory-limit` |

## 6. PHPUnit (Tests)

### Paso A — Verificar que el intérprete remoto funciona

Antes de configurar PHPUnit, confirma que el intérprete Docker del paso 1 está operativo:

1. **Settings → PHP → CLI Interpreter** → seleccionar el intérprete Docker
2. Clic en el icono `🔄` (refresh) junto a la versión de PHP
3. Debe mostrar **PHP 8.4.x** — si da error, verifica que Docker Desktop esté corriendo y `docker compose up -d` esté
   activo

### Paso B — Configurar PHPUnit

1. **Settings → PHP → Test Frameworks** → `+` → **PHPUnit by Remote Interpreter**
2. **CLI Interpreter**: seleccionar el intérprete Docker Compose del paso 1
3. **PHPUnit library**:
    - Seleccionar: **Use Composer autoloader**
    - Path to `autoload.php`: `/app/vendor/autoload.php`
    - ⚠️ **NO usar** "Path to phpunit.phar" — el proyecto usa Composer, no phar
4. Clic en el icono `🔄` junto al path — debe detectar **PHPUnit 13.x**
5. **Default configuration file**: ✅ marcar checkbox → `/app/phpunit.xml`
6. **Default bootstrap file**: dejar vacío (ya está definido en `phpunit.xml` → `tests/bootstrap.php`)
7. **Path mappings** (crítico):
    - Local path: `F:\komorebi_backup`
    - Remote path: `/app`

### Paso C — Verificar que funciona

1. Abrir cualquier test (e.g. `tests/Unit/` → algún `*Test.php`)
2. Clic en el icono ▶ verde junto a un método `test*()`
3. Debe ejecutarse dentro del contenedor Docker y mostrar resultado

### Troubleshooting común

| Síntoma                                    | Solución                                                                                        |
|--------------------------------------------|-------------------------------------------------------------------------------------------------|
| "Cannot find PHPUnit in include path"      | Verificar que el path es `/app/vendor/autoload.php` (NO ruta Windows)                           |
| "Connection refused" o timeout             | `docker compose up -d` debe estar corriendo; verificar en Services panel                        |
| "Class not found" en tests                 | Path mappings incorrectos — debe ser `F:\komorebi_backup` → `/app`                              |
| PHPUnit version "unknown"                  | Clic en 🔄 refresh; si falla, ejecutar `docker compose exec app composer install` primero       |
| Tests pasan en terminal pero fallan en IDE | Verificar que `phpunit.xml` apunta al correcto y que las env vars (`APP_ENV=testing`) se cargan |
| "Docker container not running"             | Ejecutar `make dev` o `docker compose up -d` antes de lanzar tests desde el IDE                 |

### Run Configurations sugeridas:

| Nombre                | Scope                           | Comando equivalente     |
|-----------------------|---------------------------------|-------------------------|
| **Unit Tests**        | Test suite: `Unit Tests`        | `make test-unit`        |
| **Integration Tests** | Test suite: `Integration Tests` | `make test-integration` |
| **Current File**      | Scope: current file             | —                       |

## 7. Code Style

- **Settings → Editor → Code Style → PHP**
    - Set from: **PSR-12**
    - Tabs and Indents: Spaces, 4
    - Blank lines before `return`: 1
    - Blank lines before `try`: 1

> PhpStorm no tiene soporte nativo para todas las reglas de php-cs-fixer.
> Configurar PSR-12 como base y dejar que `php-cs-fixer` corrija el resto on-save.

## 8. File Encodings

- **Settings → Editor → File Encodings**
    - Global: UTF-8
    - Project: UTF-8
    - Default encoding for properties files: UTF-8
    - Line separator: **LF (Unix)**

> Crítico en Windows: `end_of_line = lf` en `.editorconfig`. PhpStorm lo respeta automáticamente.

## 9. Inspections recomendadas

### Habilitar:

- ✅ PHP CS Fixer validation
- ✅ PHPStan validation
- ✅ Unused imports
- ✅ Missing `declare(strict_types=1)`
- ✅ Type compatibility
- ✅ Missing `#[Override]` attribute

### Deshabilitar (falsos positivos en este proyecto):

- ❌ "Method can be made `static`" — muchos métodos acceden a `$this` indirectamente
- ❌ "Class could be declared final" — php-cs-fixer lo gestiona
- ❌ Psalm inspections — Psalm eliminado del proyecto

## 10. Docker Integration

- **Settings → Build, Execution, Deployment → Docker**
    - Docker engine: Docker Desktop
    - Connection successful: ✅

- **Services panel** (View → Tool Windows → Services):
    - Añadir Docker Compose con `docker-compose.yml`
    - Se ven todos los servicios: app, db, cache, mailpit, etc.

## 11. Database (DataGrip integrado)

- **View → Tool Windows → Database** → `+` → Data Source → MySQL
    - Host: `localhost`, Port: `3306`
    - User: (valor de `DB_USERNAME` en `.env`)
    - Password: (valor de `DB_PASSWORD` en `.env`)
    - Database: `komorebi_db`
    - Schema: `komorebi_db`

## 12. Plugins recomendados

| Plugin                 | Para qué                                          |
|------------------------|---------------------------------------------------|
| **.env files support** | Autocompletado en `.env`                          |
| **Alpine.js**          | Autocompletado `x-data`, `x-bind`, etc. en vistas |
| **EditorConfig**       | Ya incluido, verificar que está habilitado        |
| **Docker**             | Ya incluido en PhpStorm                           |
| **Makefile Language**  | Syntax highlight + run targets desde gutter       |

## 13. Directories (Project Structure)

- **Settings → Directories** (o Project panel → right-click → Mark as):

| Directorio         | Marcar como                          |
|--------------------|--------------------------------------|
| `app/`             | Sources                              |
| `tests/`           | Tests                                |
| `vendor/`          | Excluded (Library Root vía Composer) |
| `storage/`         | Excluded                             |
| `node_modules/`    | Excluded                             |
| `public/build/`    | Excluded                             |
| `bootstrap/cache/` | Excluded                             |
| `.phpunit.cache/`  | Excluded                             |

## 14. Keyboard shortcuts útiles

| Acción                   | Shortcut (default)             |
|--------------------------|--------------------------------|
| Run tests (current file) | `Ctrl+Shift+F10`               |
| Run last test            | `Shift+F10`                    |
| Reformat code            | `Ctrl+Alt+L`                   |
| Go to test               | `Ctrl+Shift+T`                 |
| Find usages              | `Alt+F7`                       |
| PHPStan: analyze file    | (configurar en External Tools) |

## 15. External Tools (opcional)

Configurar en **Settings → Tools → External Tools**:

### make cs-fix

- Program: `docker`
- Arguments: `compose exec app composer run fix:cs`
- Working directory: `$ProjectFileDir$`

### make phpstan

- Program: `docker`
- Arguments: `compose exec app vendor/bin/phpstan analyse --memory-limit=1G`
- Working directory: `$ProjectFileDir$`

### make test-unit

- Program: `docker`
- Arguments:
  `compose exec app vendor/bin/paratest --runner=WrapperRunner --processes=4 --testsuite "Unit Tests" --testdox`
- Working directory: `$ProjectFileDir$`

## 16. SQL Dialect (para migraciones)

- **Settings → Languages & Frameworks → SQL Dialects**
    - Project SQL Dialect: **MySQL**
    - Directorio `migrations/`: MySQL
- Esto habilita syntax highlighting y autocompletado en los archivos `.sql`

## 17. PHP → Type Inference (strict)

- **Settings → Editor → Inspections → PHP → Type compatibility**
    - Habilitar todas las sub-inspecciones ✅
- **Settings → Editor → Inspections → PHP → Strict type checking**
    - Habilitar ✅ (coherente con `declare(strict_types=1)`)

## 18. Live Templates recomendados

Crear en **Settings → Editor → Live Templates → PHP**:

| Abreviación | Expansión                                                                                  | Uso                |
|-------------|--------------------------------------------------------------------------------------------|--------------------|
| `res_ok`    | `return Result::ok($DATA$);`                                                               | Result pattern     |
| `res_fail`  | `return Result::fail('$MSG$', '$CODE$');`                                                  | Result pattern     |
| `loginfo`   | `Logger::info('[$CLASS$] $MSG$', [$CONTEXT$]);`                                            | Logging estándar   |
| `logerr`    | `Logger::error('[$CLASS$] $MSG$', ['exception' => $$VAR$->getMessage()]);`                 | Logging de error   |
| `dtoclass`  | `final readonly class $NAME$ { public static function fromArray(array \$data): self { } }` | Scaffolding DTO    |
| `ovr`       | `#[Override]`                                                                              | Override attribute |
| `strict`    | `declare(strict_types=1);`                                                                 | Header obligatorio |

## 19. Performance del IDE

- **Settings → Editor → General → Code Folding** → Desactivar fold de imports (mejor visibilidad de `use`)
- **Help → Change Memory Settings** → Mínimo **2048 MB** (proyecto con PHPStan + Docker)
- **Settings → Appearance & Behavior → System Settings**
    - ❌ Synchronize files on frame activation → desactivar si el proyecto está en volumen Docker lento
    - ✅ Save files if IDE is idle for 15 sec

## 20. Version Control

- **Settings → Version Control → Git**
    - ✅ Enable staging area (Git add)
    - ✅ Warn uncommitted changes on checkout
- **Settings → Version Control → Commit**
    - ✅ Reformat code (before commit)
    - ✅ Run php-cs-fixer (External Tool, before commit)
    - ❌ Optimize imports (php-cs-fixer lo hace)

## 21. Terminal integrado

- **Settings → Tools → Terminal**
    - Shell: `pwsh.exe` (Windows) o `bash` (WSL)
    - Para ejecutar comandos Docker directamente desde el terminal del IDE:
      ```
      docker compose exec app vendor/bin/phpunit --testsuite "Unit Tests" --testdox
      ```

## 22. File Watchers (alternativa a on-save)

Si Actions on Save no funciona bien con Docker remoto:

- **Settings → Tools → File Watchers** → `+` → Custom
    - Name: `php-cs-fixer`
    - File type: PHP
    - Program: `docker`
    - Arguments: `compose exec -T app vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php $FilePath$`
    - Working directory: `$ProjectFileDir$`
    - Auto-save edited files: ✅
