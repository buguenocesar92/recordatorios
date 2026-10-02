<!-- USO DE HERRAMIENTAS (no lo borres: va ANTES del bloque de boost para que
     `boost:install` no lo pise) -->

# Sesiones en paralelo (Claude Code / Gemini Antigravity / terminal manual)

Varias sesiones de Claude Code y Gemini Antigravity trabajan sobre las mismas
copias de trabajo de `~/Dev`, esta incluida. Antes de tocar nada:
`/home/cesar/Dev/scripts/sesion estado .`. Al empezar: `sesion tomar . "qué vas a
hacer"`. Al terminar: `sesion soltar .`. No bloquea — es un aviso — pero si el
marcador es ajeno, mirá `git log --oneline -5` y `git status` antes de cualquier
`reset`/checkout, y commiteá siempre con `git commit --only -- <rutas>` (el índice
es compartido). Detalle y motivo en `~/Dev/CLAUDE.md`.

# Usa SIEMPRE las herramientas disponibles en este repo

Antes de trabajar de memoria, aprovecha lo que este proyecto ya trae. En orden:

- **Skills** (`.claude/skills/**`): actívalas en cuanto entres a su dominio (Laravel,
  Octane, Pest, Tailwind, Echo…), no cuando te atasques. Están versionadas según los
  paquetes instalados; su guía manda sobre tu training data.
- **MCP de Laravel Boost** (`.mcp.json`): prefiere sus tools a hacer las cosas a mano —
  `database-query` y `database-schema` para leer la BD real (no inventes esquema),
  `search-docs` **antes** de escribir código (docs de la versión exacta instalada),
  `get-absolute-url`, `read-log-entries`/`last-error` para depurar. En proyectos Docker
  el server corre dentro del contenedor (`docker compose exec ... boost:mcp`).
- **filament-mcp** (si está instalado): opera los Resources de Filament. **Solo dev**,
  apagado por defecto (`FILAMENT_MCP_ENABLED=false`); nunca encenderlo en producción con
  datos personales (Ley 21.719).
- **Context7** (MCP global): documentación real y actualizada de librerías externas.
  Úsalo en vez de asumir APIs de memoria.
- **Plugins y agentes disponibles**: si hay uno que cubre la tarea (revisión de código,
  diseño, etc.), úsalo en vez de reimplementarlo.

Regla corta: **primero la herramienta del repo, después la memoria.** Si una tool puede
darte el dato exacto (esquema, docs de versión, estado de la BD), no lo adivines.

---

# Permisos del panel (Filament + Shield): OBLIGATORIO al agregar páginas/widgets

Shield genera el permiso y lo muestra como checkbox en la pantalla de Roles, **pero no
lo aplica solo**. Si falta el trait, el permiso se togglea y no hace nada: la auditoría
del ecosistema (2026-07) encontró esta falla propagada en 5 sistemas.

| Al crear… | Obligatorio | Si falta |
|---|---|---|
| **Page** | `use HasPageShield;` | Aparece en el menú aunque no tengas el permiso (un `canAccess()` propio da 403 pero **no oculta el ítem**: eso lo hace `shouldRegisterNavigation`) |
| **Widget** | `use HasWidgetShield;` | **Se muestra siempre**, ignorando el permiso |
| **Resource** | Policy en `app/Policies/` | **Filament permite TODO** cuando el recurso no tiene policy |

```php
use BezhanSalleh\FilamentShield\Traits\HasPageShield;   // páginas
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield; // widgets

class MiPagina extends Page
{
    use HasPageShield; // controla acceso Y visibilidad en la navegación
}
```

Reglas complementarias:

- **Lógica de acceso propia** (no un permiso Shield): conserva tu `canAccess()` y añade
  `shouldRegisterNavigation()` que lo respete, o el ítem seguirá en el menú.
- **Widget decorativo** (subclase de `AccountWidget`, banner de bienvenida): no lo
  gatees — **exclúyelo** en `config/filament-shield.php`, o generará un permiso que se
  togglea sin efecto.
- **`super_admin.name` debe ser un rol que EXISTA** en el seeder. Si no coincide, el
  administrador pierde el `Gate::before` y deja de ver recursos y widgets (pasó en
  feria: quedó en `'super_admin'` cuando el rol real era `'administrador'`).
- **Nombres de permiso**: este scaffold usa la nomenclatura v4 (`view_mi_pagina`).
  Algunos sistemas municipales conservan la v3 (`page_MiPagina`) vía
  `FilamentShield::buildPermissionKeyUsing()`. Verifica con
  `FilamentShield::getPages()` antes de escribir el nombre a mano.
- **Dueño del permiso**: `RolesSeeder` da a `administrator` los permisos de TODAS las
  páginas y widgets, salvo los listados en `RolesSeeder::SOLO_SUPER_ADMIN` (hoy Respaldos y
  Componentes, cada uno con su razón). Una página o widget que exponga datos en bloque,
  secretos o herramientas de desarrollo se agrega a esa lista; `NingunPermisoHuerfanoTest`
  falla si un permiso queda sin ningún rol dueño.
- **Test candado**: al agregar página/widget, cubre «sin permiso NO se ve (ni en el
  menú)» y «con permiso sí». Un test que solo comprueba el 403 no detecta este bug.

---
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel-octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

When working on Octane-specific features (concurrency, shared tables, memory, driver configuration, testing), invoke `octane-development` for detailed rules.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

## Con qué herramientas se trabaja este proyecto

Vienen configuradas en `.mcp.json`, no hay que instalar nada:

| Herramienta | Para qué | Detalle que importa |
|---|---|---|
| **laravel-boost** | Leer la base REAL (`database-query`, `database-schema`), buscar en la documentación de la versión exacta instalada (`search-docs`), leer los registros (`browser-logs`, `last-error`) | Corre **dentro del contenedor**. Configurado de la forma ingenua —`php artisan boost:mcp`— arranca igual desde el equipo pero NO alcanza la base de datos, y sus herramientas más útiles fallan sin decir por qué |
| **context7** | Documentación al día de Laravel, Filament, Livewire y Tailwind | Las versiones están mezcladas entre proyectos: una API que no verificaste en esta sesión, se busca antes de escribirla |
| **playwright** | Pruebas de extremo a extremo y regresión visual | Es lo que usan las pruebas de accesibilidad, rendimiento, móvil y animación |
| **chrome-devtools** | Comprobar en vivo lo que se dibuja y medir rendimiento | Al tocar interfaz: escribir → captura → corregir → captura. No describir cómo quedó: mirarlo |

Van **en el repositorio** y no en la configuración personal de nadie, porque un
sistema nacido de este scaffold tiene que funcionar igual en otro equipo, donde
no existe ningún `~/.claude`. Ninguna lleva credenciales: si alguna las
necesitara, van por entorno, nunca versionadas (lo vigila una prueba).

Regla corta: **primero la herramienta, después la memoria.** Si una tool puede
darte el dato exacto —el esquema, la documentación de esta versión, el estado de
la base— no lo adivines.

### Skills: viajan DENTRO del repo

Están en `.claude/skills/` y se copian a cada proyecto generado a propósito, para
que un sistema nacido de este scaffold funcione igual en **otro equipo** —otro
municipio, otra persona— donde no exista `~/.claude/skills`. Sin ellas, quien
trabaje ahí no tiene las convenciones de diseño ni el procedimiento de auditoría:
los improvisa, y cada uno distinto.

| Skill | Cuándo |
|---|---|
| `blade-livewire-design` | Antes de tocar cualquier vista, componente Livewire o panel Filament |
| `auditoria-seguridad` | Antes de desplegar, al tocar autenticación, permisos, subidas o endpoints públicos |
| `seguridad-web` | 67 procedimientos: XSS, inyección de cabeceras, OAuth, inyección de prompts en RAG |
| `web-perf` | Al investigar carga lenta o Core Web Vitals |
| `mapa-funcional` · `catalogo-funcional` | Antes de guionizar videos o escribir manuales |
| `laravel-best-practices` · `octane-development` · `pest-testing` · `tailwindcss-development` | Las genera Boost contra las versiones instaladas |

**El precio de que viajen son dos copias, y dos copias divergen.** Por eso:

- `make skills-check` avisa si la del repo se separó de la del equipo.
- `make skills-sync` la actualiza, **siempre en ese sentido**: la del equipo
  manda. Editar la copia del repo es perder el cambio en la próxima
  sincronización.
- Las cuatro de Boost se regeneran con `boost:install --guidelines --skills`
  **juntos** tras cada actualización grande.

La comprobación de divergencia NO está en la batería de pruebas: esta corre
dentro del contenedor, donde `~/.claude/skills` no existe, y ahí la comparación
se saltaba sola pasando en verde aunque las copias difirieran.

## Cómo se comprueba este proyecto

Cada comando tiene su propia base de datos, y no es un detalle: compartirlas hace
que dos corridas simultáneas se borren las tablas entre sí, y el síntoma son
decenas de fallos que no existen y que cambian de una corrida a otra.

| Comando | Qué hace | Cuándo |
|---|---|---|
| `make test` | La batería completa | En cada cambio |
| `make cobertura` | Qué líneas tocan las pruebas | Al terminar algo grande |
| `make mutacion` | Si las pruebas DETECTAN cambios en el código | Bajo demanda: es lenta |
| `cd e2e && npx playwright test accesibilidad` | WCAG 2.2 AA en dos modos y por teclado | Al tocar UI |
| `cd e2e && npx playwright test rendimiento` | LCP y CLS reales | Al tocar el panel |

**Tests desde un worktree o con otra sesión activa: `tools/dev/test-aislado.sh <nombre>`.**
`make test` usa `sistema_test` y el contenedor `app`, que monta el checkout PRINCIPAL: dos
suites a la vez se borran la base mutuamente y, desde un worktree, se prueba código ajeno.
El script (`tools/dev/test-aislado.sh <nombre> [args de pest]`, también `--pint` y `--js`)
corre Pest en un `docker run --rm` con base propia `sistema_def_<nombre>_test`, deduce
contenedor MariaDB e imagen del `docker-compose.yml` (sirve igual en un sistema generado) y
no deja nada en el repo. Dos límites: **`--parallel` no funciona** (el usuario de la BD no
puede crear una base por worker) y **`docker run` debe llevar `--user "$(id -u):$(id -g)"`**
(el script ya lo hace; si lo armás a mano, sin eso escribe `storage/` como root).
| `cd e2e && npx playwright test interaccion` | INP: cuánto tarda el panel en responder | Al tocar el panel o añadir movimiento |
| `cd e2e && npx playwright test movil` | Desbordes y área táctil en teléfono | Al tocar UI |

Sobre `make mutacion`: **su score se queda corto, no se pasa**. Comprobado el
2026-08-29 en `App\Support\Coordenadas`: informó 86,96 % con seis mutantes
«sin probar», y al aplicarlos a mano las pruebas SÍ fallaban con al menos uno de
ellos —invertir la comprobación de cadena vacía tumba cuatro casos—. Los otros
cinco son equivalentes: quitar un `return` que la línea siguiente vuelve a
producir, o un `(float)` que PHP aplica igual porque el archivo no declara
`strict_types`. Conclusión práctica: un mutante «sin probar» es una pista para
revisar a mano, nunca por sí solo la prueba de que falta un test.

Dos cosas que conviene saber antes de perder tiempo:

- **La suite se niega a correr contra una base que no termine en `_test`.** Es lo
  que impide que una corrida distraída arrase la base de trabajo.
- **La mutación deja procesos vivos** dentro del contenedor si el comando que la
  lanzó se corta, y siguen compitiendo por la base. Si aparecen fallos raros:
  `docker compose exec app pkill -f "pest --mutate"`.

### Cómo se genera un proyecto (para agentes)

Sólo desde el checkout del scaffold (`bin/scaffold` no viaja al proyecto generado). El
generador es un binario PHP gobernado por un manifiesto; el asistente interactivo es para
personas. Cuando César pide «generame X», el camino es:

1. **Leer el catálogo antes de armar nada**: `php bin/scaffold catalogo --json`. Lo que
   se puede elegir (`preset`, `frontend`, `opcionales`) sale del registro `presets/`, no de
   memoria: un nombre que no está ahí es un error del manifiesto.
2. **Escribir `scaffold.json`** en un directorio temporal (nunca en la raíz del scaffold:
   ahí no se versiona), con `nombre` y `entidad` (`muni` | `kraftdo`) obligatorios; el
   esquema está en `presets/esquema.json`. Para generar sin Docker, git ni npm —tests,
   CI—: `"post": {"docker": false, "git": false, "assets": false, "github": false}`.
   Con `"frontend": "inertia-react"` hacen falta `npm` y `composer` en el PATH (y red) al
   generar, aun con `assets: false`: sincronizan los locks, y sin ellos `new` sale con 1.
3. **Primero el plan**: `php bin/scaffold new --desde=/ruta/scaffold.json --dry-run`
   imprime cada operación, en orden, sin escribir nada. Leerlo entero.
4. **Después generar**: el mismo comando sin `--dry-run`. Código 0 = proyecto completo y
   verificado, con `scaffold.json` y `.scaffold-meta.json` en su raíz; código 1 = nada
   quedó a medias (el destino se borra) y el mensaje dice preset, operación y ruta.
5. **Sobre un proyecto ya generado**: `php bin/scaffold info --proyecto=<ruta> --json` y
   `php bin/scaffold add <opcional> --proyecto=<ruta>` (exige árbol git limpio).

Los tests del generador viven en `tests/Generador/` (no viajan) y corren también dentro del
contenedor; `ContratosDePresetsTest` exige a todo preset ficha completa e idempotencia.

## Apagar una feature en producción sin redeploy (Pennant)

Las features son **interruptores del sistema** declarados en `config/features.php`
con el valor con el que nacen (`'asistente-ia' => false`). Encender o apagar en
producción **no toca ni el código ni el `.env`**: escribe una fila global en la tabla
`features` y Octane lo ve en la petición siguiente.

```bash
docker compose exec app php artisan feature asistente-ia apagada      # para todo el sistema
docker compose exec app php artisan feature asistente-ia encendida
docker compose exec app php artisan feature asistente-ia por-defecto  # borra la fila; vuelve a config/features.php
```

En el código: `Feature::active('asistente-ia')` o `@feature('asistente-ia')`. Una feature
nueva se declara en `config/features.php`; el comando rechaza nombres que no estén ahí.

Gotcha de Octane, ya cubierto y con test: Pennant guarda en memoria lo que resuelve, y un
worker seguiría sirviendo el valor viejo si el propio paquete no vaciara esa memoria en
cada `RequestReceived`. Por eso el cambio se ve en la petición siguiente **sin reiniciar**;
si alguna vez no se ve, lo primero que hay que mirar es que ese listener siga registrado
(`InterruptoresDeFeaturesTest`). El scope por defecto es el global (una fila por feature):
con el scope por usuario de fábrica, «apagar para todos» no apagaba nada para los usuarios
nuevos. Quien quiera despliegue gradual usa `Feature::for($usuario)` explícito.

## Qué NO se puede subir sin migrar datos

`bezhansalleh/filament-shield` está fijado en `~4.2.0`. La 4.3 renombra todos los
permisos, y los permisos no son código: son filas ya asignadas a roles y personas
en sistemas en producción. El motivo y los pasos para subir están en
`docs/superpowers/auditoria/2026-08-26-shield-4.3-bloqueado.md`.

## Diseño, interactividad y animación

**Las convenciones completas viven en la skill global `blade-livewire-design`
(`~/.claude/skills/blade-livewire-design/SKILL.md`) — fuente única.** Tokens,
reglas de dashboard, accesibilidad, patrones Livewire 4 y gotchas del ecosistema.
No duplicar ese contenido acá: si una convención cambia, se cambia en la skill.

### Versiones de ESTE repo (verificado en composer.lock)

| Paquete | Versión |
|---|---|
| laravel/framework | `v13.20.0` |
| livewire/livewire | `v4.4.2` |
| filament/filament | `v5.7.6` |
| muni-graneros/laravel-muni-ui | `v0.16.0` |
| muni-graneros/laravel-muni-shared | `v1.16.0` |
| bezhansalleh/filament-shield | `4.2.0` — **fijado a propósito**, ver `docs/superpowers/auditoria/2026-08-26-shield-4.3-bloqueado.md` |
| spatie/laravel-csp | `3.29.0` — escribe la política de contenido; presets en `app/Support/Csp`, ver `.ai/rules/cabeceras-y-csp.md` |
| motion (npm) | `^13.1.0` — en v13 la opción es `ease`, NO `easing` (v10, se ignora en silencio) |

### Dónde vive la animación

| Entrypoint | Pantalla | Qué hace |
|---|---|---|
| `resources/js/panel.js` | El panel Filament | Entrada escalonada de tarjetas y conteo de indicadores |

`app.js` **no** anima nada: antes importaba Motion, lo colgaba de `window` sin
que nadie lo usara y encima ninguna vista cargaba ese paquete.

Hubo un `acceso.js` con un fondo WebGL (`three`, 189 kB) para las pantallas de
acceso propias (`/login` y la recuperación de clave). Se fue con ellas: el
ingreso y la recuperación viven solo en el panel (`routes/web.php` cuenta por
qué). Las reglas de abajo se aprendieron ahí y siguen valiendo para `panel.js`.

### Reglas que costaron una tarde y están medidas

1. **Nunca animar una cadena `transform` hacia `none`.** Motion interpola a
   `matrix(0,0,0,0,0,0)`: el elemento queda de 0×0 y la pantalla de acceso se ve
   vacía, sin un solo error en consola. Van propiedades sueltas: `y`, `scale`.
2. **No animar `opacity` desde 0 en el elemento más grande de la pantalla.** Lo
   que empieza invisible no cuenta como pintado, y el LCP se va con la
   animación: medido en el escritorio, 1424 ms con desvanecido contra 848 ms sin
   animar. Desplazando en vez de desvanecer queda en 1088 ms con el mismo
   efecto a la vista. Los elementos chicos sí pueden desvanecerse.
3. **Todo efecto pesado es mejora progresiva, nunca la base.** Cuando había
   fondo WebGL, debajo había siempre un degradado CSS equivalente y la librería
   ni siquiera se descargaba si se pidió menos movimiento, si el equipo tenía
   poca memoria o pocos núcleos, o si la conexión estaba en ahorro de datos. Si
   vuelve algo así a una pantalla pública, vuelve con esas cuatro guardas y con
   su proyecto WebKit acotado en `e2e/playwright.config.ts`.

Livewire **4**: usar `@island`, `wire:show` / `wire:text` / `wire:bind`
(optimistic UI), `wire:sort` para drag & drop, `#[Transition]`.
Componentes de **clase**, no single-file → `@placeholder` NO aplica acá;
el estado de carga va con `placeholder()` en la clase o `wire:loading`.

### Restricciones que no se negocian

- Landing (`/`): Blade puro, **sin JavaScript, sin Livewire, sin sesión**; manda
  `.ai/rules/sitio-publico.md`. Paneles: Blade + Livewire + Alpine + Filament
  **por defecto**. **No Vue, no Svelte, no jQuery**: esos siguen vetados sin excepción.
- **Inertia + React sólo en la consola de operador** (`/consola`) de un proyecto generado
  con `--frontend=inertia-react`, y sólo si el sistema cumple el criterio medido de
  [`docs/FRONTEND-INERTIA-REACT.md`](docs/FRONTEND-INERTIA-REACT.md) §1. Si ninguna de las
  cuatro señales se cumple, se arregla el dolor real en Livewire. Nunca React incrustado
  dentro de una página Livewire o Filament: si hace falta React, la página es de Inertia
  completa. Convenciones: skill `inertia-react-design`.
- Alpine viene dentro de Livewire: nunca instalarlo ni importarlo aparte en el
  bundle del panel. (Un entrypoint público aislado que no carga Livewire es la
  única excepción, y se declara como `input` propio en `vite.config.js`.)
- Tailwind para todo el estilado; nada de CSS suelto salvo `@layer` en `app.css`.
- Animación: **Motion One** (`motion` ^13). No usar GSAP, anime.js ni AOS.
- Todo componente debe funcionar sin JS antes de animarse.
- CSS moderno (anchor positioning, scroll-driven, container queries) solo como
  mejora progresiva, dentro de `@supports`.
