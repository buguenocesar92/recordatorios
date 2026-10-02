# Scaffold Laravel 13 + Filament 5 + Docker (FrankenPHP)

> ⚠️ **Antes de desplegar a producción**, lee [`docs/POSTMORTEM-DESPLIEGUE.md`](docs/POSTMORTEM-DESPLIEGUE.md):
> el checklist que evita el incidente que tumbó producción (servir como www-data, `.env` legible, shield, workers).

**Generador de proyectos del ecosistema.** Este repositorio no es un sistema: es la
plantilla desde la que nacen los sistemas municipales de Graneros y los de KraftDo, con la
estructura, las prácticas y los candados ya extraídos de los sistemas en producción.

## Generar un proyecto

El generador es un binario PHP, `bin/scaffold` (`./scaffold` es un shim que lo invoca).
Tiene tres modos que llegan al **mismo manifiesto validado** antes de tocar el disco, y un
`--dry-run` que imprime el plan completo sin escribir nada.

**Asistido** (en una terminal): pregunta nombre, entidad, preset, frontend, opcionales,
destino, puerto y qué hacer al terminar; muestra el manifiesto y el resumen de lo que trae,
y deja generar, guardar sólo `scaffold.json` (para pasárselo a un agente) o cancelar.

```bash
./scaffold new
```

**Directo, con flags** (los de siempre siguen valiendo):

```bash
./scaffold new "Mi Sistema" --shared=muni
./scaffold new "Mi Sistema" ~/Dev --shared=kraftdo --port=8101 --frontend=inertia-react --no-up
# -> crea <destino>/mi-sistema con contenedores, volúmenes, red y puertos propios
#    (app/db; sin --port, el primer bloque libre desde 8001) y, salvo --no-up,
#    ejecuta `docker compose up -d --build`.
```

Forma completa:

```
./scaffold new <Nombre> --shared=muni|kraftdo [destino] [--port=8001]
                        [--preset=completo] [--frontend=livewire|inertia-react] [--con=a,b]
                        [--desde=scaffold.json] [--no-up] [--no-git] [--no-assets]
                        [--dry-run] [-v]
```

- **`--shared` es obligatorio y no tiene valor por defecto.** Dice a qué entidad pertenece
  el proyecto y de qué paquete compartido consume RUT, coordenadas y personas. El scaffold
  vive en la cuenta de la Municipalidad y su `composer.json` exige los paquetes
  `muni-graneros/*`: elegir por omisión era meter una entidad dentro de otra.
- `--port=8101` fija el bloque de puertos (app y BD); sin él se busca el primer bloque
  libre, para que varios proyectos del scaffold convivan sin chocar.
- `--no-up` genera sin levantar Docker; `--no-git` no inicializa el repositorio;
  `--no-assets` no corre `npm ci && npm run build`.
- `--preset=` elige el preset base y `--con=a,b` los opcionales; `--dry-run` imprime el plan
  sin escribir y `-v` muestra el comando exacto de cada operación.
- El destino por defecto es el directorio que contiene este repo (`../<slug>`).

Desde el `Makefile`: `make new name="Mi Sistema" shared=muni [frontend=…] [port=…]`.

**Con manifiesto** — la interfaz principal para un agente. `scaffold.json` es la única fuente de
verdad de lo que se genera y queda versionado en la raíz del proyecto nuevo:

```json
{
  "esquema": 1,
  "nombre": "Permisos de Circulación",
  "entidad": "muni",
  "preset": "completo",
  "frontend": "inertia-react",
  "opcionales": [],
  "destino": "/home/cesar/Dev/Graneros",
  "puertos": { "app": 8011 },
  "post": { "docker": true, "git": true, "assets": true, "github": false }
}
```

```bash
./scaffold new --desde=scaffold.json --dry-run   # el plan, operación por operación
./scaffold new --desde=scaffold.json             # genera; un flag suelto pisa el campo y lo dice
```

`nombre` y `entidad` no tienen valor por defecto; `preset` (`completo`), `frontend`
(`livewire`), `opcionales` (`[]`), `destino` (el directorio padre del scaffold), `puertos.app`
(bloque libre) y `post` sí. El JSON Schema está en `presets/esquema.json` (generado desde el
registro con `./scaffold esquema --escribir`; un test comprueba que está al día). Cualquier error
—preset desconocido, dependencia sin satisfacer, incompatibilidad, slug ya existente, puerto
ocupado— se informa en español, con campo y valor, antes de copiar nada; si una operación falla a
mitad de camino, el destino se borra y el comando termina con 1: nunca un proyecto «a medias».

**Qué hay para elegir** sale del registro `presets/`, no de esta página:

```bash
./scaffold catalogo           # presets base, frontends, opcionales, lo que agrega cada uno
./scaffold catalogo --json    # lo mismo para el agente (livewire incluido)
./scaffold explicar inertia-react
```

**Después de generar**: `.scaffold-meta.json` guarda con qué commit del scaffold, entidad,
preset, frontend, opcionales (con versión) y puertos nació el proyecto. Se lee con
`./scaffold info --proyecto=../mi-sistema [--json]`, y `./scaffold add <opcional>
--proyecto=../mi-sistema` aplica un opcional sobre un proyecto ya generado: exige árbol git
limpio, valida las dependencias contra la meta y la actualiza al terminar. (Hasta que lleguen los
opcionales de backend el catálogo no tiene ninguno elegible: el mecanismo está, el contenido no.)

## Las tres capas

Decisión del ecosistema (2026-09-11): todo sistema nuevo se arma en tres capas, cada una con
su tecnología fija.

| Capa | Dónde | Tecnología | Estado en el generador |
|---|---|---|---|
| Landing pública | `/` (`routes/sitio.php`, `resources/views/sitio`) | Blade, sin JavaScript | **se genera siempre** |
| Paneles privados | `/admin` | Filament + Livewire en modo SPA | **se genera siempre** |
| Consola de operador | `/consola` | Inertia + React + TypeScript | **con `--frontend=inertia-react`** |
| App pública (portal del vecino / cliente) | `/portal` | Inertia + React + TypeScript | **Fase 2 — todavía NO la genera el scaffold** |

La landing se edita en `config/sitio.php` (claim, servicios, contacto) y se pinta sola con
la paleta de la entidad. `robots.txt` y `sitemap.xml` se generan y solo se publican en los
dominios de `DOMINIOS_INDEXABLES`.

La cuarta fila es la que falta: el portal público completo en Inertia está decidido pero no
implementado en el generador. Hoy lo único Inertia que sale del scaffold es la consola de
`/consola`, que es para operadores autenticados, no para el vecino.

### Proyectos de un ecosistema: `--shared`

Por defecto el proyecto se lleva su propia copia de `RutHelper`, `RutValido`,
`Coordenadas`, `PersonaDTO` y el resolver de personas, en vez de depender de un
paquete compartido para esas cinco clases.

**Esto NO alcanza para que el proyecto nazca autónomo de la Municipalidad de
Graneros.** `composer.json` exige `muni-graneros/laravel-muni-shared` y
`muni-graneros/laravel-muni-ui` sin importar `--shared`, y la identidad visual (paleta,
escudo, `MuniPanel`, `PanelArcopPlugin`) está escrita directo en `AdminPanelProvider` y en
varias clases de `app/`. Un sistema de KraftDo o de otra municipalidad generado hoy lleva
de fábrica la marca de Graneros —paleta, escudo, plugin del ciclo ARCOP— sin ninguna puerta
para apagarla. Extraer eso a algo neutro —o dejarlo detrás de una configuración que el
generador reemplace— es un cambio de arquitectura pendiente, no algo que resuelva esta
bandera.

Si el proyecto entra a un ecosistema que ya tiene paquete compartido, `--shared` hace que
esas cinco clases consuman el paquete en vez de duplicarlas:

```bash
./scaffold new "Nuevo Portal" --shared=muni      # muni-graneros/laravel-muni-shared
./scaffold new "Nuevo Sistema" --shared=kraftdo  # kraftdo/laravel-kraftdo-shared
```

Reescribe las referencias, borra solo los archivos migrados (nunca la carpeta: p. ej.
`app/Support/Geocoder.php` se conserva), poda del árbol de este README las hojas que dejaron
de existir y declara la dependencia en `composer.json`. Cada ecosistema tiene su paquete y
su propio conjunto de clases: `muni` migra 5, `kraftdo` migra 10 (incluye el módulo de
onboarding). La lógica está en `tools/usar-paquete-compartido.php`.

Los tres paquetes que trae `--shared=muni` (`laravel-muni-shared`, `laravel-muni-ui` y
`laravel-muni-candados`) son **repositorios públicos** de `muni-graneros`, verificado contra
la API de GitHub el 2026-09-14: para un proyecto muni **no hace falta token**. Lo que sí hace
falta siempre es el bloque `repositories` del `composer.json`, porque ninguno está en
Packagist.

**Si aun así `composer install` pide credenciales** —`--shared=kraftdo`, que usa
`kraftdo/laravel-kraftdo-shared`, o alguno de los paquetes del ecosistema que sí son privados
(`laravel-muni-acceso`, `laravel-panel-base`, `laravel-anonimizacion`, `laravel-centinela`,
`laravel-rag`)— hay que dejarlas ANTES del primer `make up`: en desarrollo
el bind mount `.:/app` de `docker-compose.yml` tapa el `vendor/` que ya trae la imagen, así
que el primer `up` corre `composer install` de nuevo DENTRO del contenedor, contra el código
del bind mount — y ahí falla aunque la imagen se haya construido bien. En la raíz del
proyecto generado:

```bash
composer config --auth github-oauth.github.com <token-fine-grained>
```

Composer lee ese `auth.json` solo (un `auth.json` junto a `composer.json` tiene prioridad
sobre el global), así que no hace falta tocar el entrypoint ni exportar nada. Ya está en
`.gitignore`/`.dockerignore`: nunca se versiona ni se hornea en la imagen. En producción el
token lo pasa `desplegar.yml` (`secrets.COMPOSER_GH_TOKEN`).

Más detalle —qué clases migra cada ecosistema, qué reescribe el script y qué NO resuelve
`--shared`— en [`docs/PAQUETE-COMPARTIDO.md`](docs/PAQUETE-COMPARTIDO.md).

### Consola de operador: `--frontend`

```bash
./scaffold new "Sistema" --shared=muni                          # Livewire (por defecto)
./scaffold new "Consola" --shared=muni --frontend=inertia-react # + consola en /consola
./scaffold explicar inertia-react                               # la ficha completa
```

A diferencia de `--shared`, acá **sí hay valor por defecto**: `livewire` produce exactamente
el proyecto de siempre. El stack de la interfaz no le cambia el dueño a nada, así que
elegirlo por omisión no mezcla entidades.

`--frontend=inertia-react` suma una consola de operador en `/consola` y **no toca el panel**:
Filament, Shield, ARCOP, muni-ui, policies, jobs, migraciones y despliegue siguen igual.
Es el preset `presets/inertia-react/` (ficha en `preset.json`, operaciones en `Preset.php`,
archivos en `archivos/`), fuera del árbol Laravel, así que el proyecto que no lo pide no
arrastra React. **Prerrequisito al generar: `npm` y `composer` en el PATH, con red**, para
sincronizar `package-lock.json` y `composer.lock`; sin ellos `new` se niega antes de copiar
nada (un lock desfasado es un proyecto cuyo `npm ci` falla al arrancar).
Lo que agrega el aplicador (`tools/aplicar-frontend-inertia-react.php`):

- **PHP**: `inertiajs/inertia-laravel`, `spatie/laravel-data` y
  `spatie/laravel-typescript-transformer`.
- **JavaScript**: React 19, `@inertiajs/react`, `@tanstack/react-table`, TypeScript y
  Vitest, más los scripts `types:generate`, `types:check` y `test:js`.
- **Los tipos TypeScript se generan desde las Data classes de PHP** (`php artisan
  typescript:transform`), nunca se escriben a mano: `npm run types:check` los regenera y
  corre `tsc --noEmit`.
- La consola hereda el mismo trío de middleware del panel (`auth`, sesión acotada, sin
  caché para autenticados), su propio limitador `throttle:consola` y un spec de
  accesibilidad en `e2e/tests/consola-accesibilidad.spec.ts`.

El criterio para decidir si un sistema **existente** se migra está en
[`docs/FRONTEND-INERTIA-REACT.md`](docs/FRONTEND-INERTIA-REACT.md); las convenciones de la
consola, en `presets/inertia-react/CLAUDE-consola.md`.

## Día a día

`make help` lista todo. Lo que más se usa:

```bash
make init      # construye y levanta todo de cero (migra + siembra vía entrypoint)
make doctor    # diagnostica el entorno (extensiones, BD, Redis, storage…)
make test      # suite Pest contra la BD aislada sistema_test (NO toca la BD real)
make e2e       # Playwright          make typecheck  # tsc --noEmit de los specs E2E
make fresh     # recrea la BD        make shell      # shell en el contenedor app
make pint      # formatea código     make logs       # sigue los logs
make auditar   # auditoría de seguridad/higiene/calidad del proyecto
tools/dev/test-aislado.sh <nombre>   # Pest con base propia, para worktrees y sesiones en paralelo (sin --parallel)
```

Y, menos seguido: `make cobertura` (necesita pcov), `make mutacion`, `make manual`
(capturas + manual con Playwright), `make skills-check` / `make skills-sync`.

## Tests, CI y el hook pre-push

`.github/workflows/ci.yml` corre Pint + Pest (con MariaDB y Redis de servicio) en cada push
y PR, y Playwright E2E en un job aparte. El job que **genera un proyecto muni de cero y corre
adentro PHPStan y Pest** (`generado`) vive en el workflow del generador,
`.github/workflows/generador.yml`, que no viaja al proyecto generado.

Los hooks de `.githooks/` deciden por **lo que se envía**, no por la rama en la que estás:

- Enviar a `main` o `master` se **rechaza siempre**. Estos repositorios son privados en
  cuentas Free, donde no hay protección de rama del lado del servidor, así que el único
  corte posible es local. El flujo es develop → main por PR.
- La suite se corre solo cuando alguna ref publica commits en `develop` (borrar la rama
  remota no cuenta: el sha local viene en ceros). En ramas de trabajo no se cobra: un hook
  que se saltea siempre no protege nada.
- Se invoca con stdin desasociado y `docker compose exec -T`: dentro de un hook stdin no es
  una terminal, y sin `-T` Docker se niega con «cannot attach stdin to a TTY-enabled
  container» y cancelaba **todo** envío a develop.
- Escapes explícitos: `git push --no-verify` (todo) o `SKIP_PREPUSH_TESTS=1` (solo la suite).

Hay candados que vigilan este mismo archivo: `tests/Feature/EstructuraDelReadmeTest.php`
comprueba que cada hoja del árbol de abajo exista en disco, y
`tests/Generador/ElPaqueteCompartidoPodaElReadmeTest.php` que el generador lo pode bien al
migrar clases al paquete compartido. Si tocás el árbol, corré `make test`.

## Videos-tutorial (demo-engine)

El proyecto generado trae cableado el motor genérico
[`demo-engine`](../demo-engine/README.md): graba tutoriales con Chromium real, sintetiza
voz en español (Kokoro + Piper, modelos locales), monta el MP4 offline con ffmpeg y tiene
un portero de privacidad que impide grabar tablas sin filtrar.

```bash
make demo-preparar                # prepara sesiones autenticadas (una sola vez)
make demo-grabar guion=recorrido  # graba un guion → docs/manual/recorrido.mp4
make demo-curso                   # graba y monta el curso completo
make demo-auditar video=recorrido # audita el video por OCR buscando fugas
```

Los guiones viven en `demo/guiones/` y la configuración en `demo.config.mjs` (comentado:
se personaliza ahí). `make demo-engine-update V=v1.12.0` sube el pin del motor.

## Tecnologías principales

1. **Framework:** Laravel 13 sobre PHP 8.4+ (Octane con FrankenPHP).
2. **Panel de administración:** Filament 5, multi-panel y roles con Shield.
3. **Infraestructura:** Docker Compose multi-contenedor (app, worker, MariaDB, Redis).
4. **Calidad:** Pest (unit/feature) y Playwright (E2E), con cobertura y mutación opcionales.
5. **Seguridad:** cifrado de campos sensibles, doble factor, política de contenido estricta
   (`spatie/laravel-csp`) y los candados de `muni-graneros/laravel-muni-candados`.
6. **Onboarding interactivo:** tours guiados (`driver.js`) definidos en base de datos, con
   navegación inteligente y lector por voz.

---

## Estructura del Scaffold

```
scaffold-laravel-filament-pwa/
├── app/
│   ├── Filament/                 # Recursos y configuración de Filament
│   │   └── Resources/
│   │       └── OnboardingTourResource.php # CRUD del sistema de guías
│   ├── Http/
│   │   └── Middleware/
│   │       └── TwoFactorAuthentication.php # Middleware MFA
│   └── Livewire/
│       └── VerificarMfa.php       # Controlador Livewire de doble factor
├── database/
│   └── migrations/
│       └── 2026_06_07_000001_create_onboarding_system_tables.php # Migración del onboarding
├── demo/
│   └── guiones/                  # Guiones de grabación (demo-engine)
│       └── recorrido.mjs          # Guión de ejemplo: recorrido del panel
├── docker/
│   ├── docker-entrypoint.prod.sh # Entrypoint optimizado para producción
│   └── supervisord.conf          # Configuración del Queue Worker y Planificador
├── docs/
│   └── manual/
│       └── MANUAL_USUARIO.md     # Plantilla de manual base Markdown
├── e2e/                          # Entorno de pruebas Playwright
│   ├── manual/
│   │   └── capturas.spec.ts      # Generación automática de capturas de pantalla
│   ├── tests/
│   │   ├── auth.setup.ts
│   │   └── helpers.ts
│   ├── build-manual.mjs          # Compilador a HTML / PDF (A4)
│   ├── manual.config.ts          # Configuración Playwright para fotos
│   └── playwright.config.ts      # Configuración Playwright para E2E
├── public/
│   └── js/
│       └── onboarding.js         # Core JS del tour interactivo (TTS + navegación)
├── resources/
│   └── views/
│       ├── livewire/
│       │   └── verificar-mfa.blade.php # Vista de verificación OTP
│       └── onboarding/
│           └── scripts.blade.php  # Scripts de inicialización del tour
├── tests/
│   └── Feature/
│       └── EsquemaDiagramaTest.php # Test para evitar drifts de BD y esquemas
├── Dockerfile                    # Multi-stage build (Node compilación + FrankenPHP runtime)
├── docker-compose.yml            # Configuración de Docker para Desarrollo
├── docker-compose.prod.yml       # Hardening de Docker para Producción
├── docker-entrypoint.sh          # Script de arranque e inicialización en desarrollo
└── test-all.sh                   # Orquestador de pruebas y compilación de manuales
```

---

## Componentes destacados reutilizables

### 1. Sistema de onboarding guiado (`onboarding.js` + driver.js)
Recorridos guiados configurables desde la base de datos a través de Filament:
* **Navegación inteligente (`pre_action_selector`)**: si un paso requiere cambiar de
  pestaña o abrir un modal, el script lo pulsa y espera a que aparezca el elemento
  resaltado antes de continuar.
* **Lector de voz integrado (TTS)**: usa `SpeechSynthesis` en español para leer las
  instrucciones, con controles de silencio/activación en las ventanas del tour.

### 2. Cifrado resiliente en reposo (`Muni\Shared\Casts\EncryptedSeguro`)
Cast de `muni-graneros/laravel-muni-shared` para datos sensibles. Si hay registros en texto
plano heredados o mal formateados, el cast no lanza al leer: permite una transición suave
hacia bases cifradas.

### 3. Normalización de identidades (`RutHelper.php`)
Clase estática para limpiar, validar (módulo 11), formatear y normalizar RUTs chilenos.
Con `--shared` se reemplaza por la del paquete compartido.

## Experiencia de uso

- **Navegación sin recargas.** El panel corre en modo SPA (`->spa()` de Filament) y las
  vistas propias navegan con `wire:navigate`: se cambia de sección sin volver a cargar
  la página entera.
- **Sistema de diseño compartido** (`laravel-muni-ui`): los mismos componentes, colores y
  tipografía que el resto del ecosistema municipal, con el cinturón institucional.
- **Animación con Motion One**, que respeta `prefers-reduced-motion`: quien pide menos
  movimiento no lo recibe.
- **Tours guiados** para el funcionario que entra por primera vez, en vez de un manual
  que nadie lee.
- **Trazabilidad**: cada cambio queda registrado (activitylog) y se consulta desde el
  propio panel.
- **Permisos por rol** con Shield, y policy en cada recurso: un permiso que se activa
  se aplica de verdad, no solo se ve como casilla.

## Documentación

| Documento | Qué contiene |
|---|---|
| [`docs/POSTMORTEM-DESPLIEGUE.md`](docs/POSTMORTEM-DESPLIEGUE.md) | El checklist de producción, escrito después del incidente que la tumbó. Léelo antes de desplegar. |
| [`README-despliegue.md`](README-despliegue.md) | Cómo se despliega un proyecto generado (no abre puertos: queda detrás del ingress). |
| [`docs/PAQUETE-COMPARTIDO.md`](docs/PAQUETE-COMPARTIDO.md) | Qué migra cada ecosistema con `--shared`, qué reescribe y qué no resuelve. |
| [`docs/FRONTEND-INERTIA-REACT.md`](docs/FRONTEND-INERTIA-REACT.md) | El criterio medido para migrar un sistema **existente** a Inertia + React. |
| [`CHANGELOG.md`](CHANGELOG.md) | Qué cambió en cada versión del scaffold (`VERSION` lleva la actual). |
| [`CLAUDE.md`](CLAUDE.md) · [`AGENTS.md`](AGENTS.md) | Contexto y reglas para agentes que trabajan en este repo. |
