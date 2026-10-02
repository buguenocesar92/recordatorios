# Historial del scaffold

Qué cambió, cuándo y por qué. Sirve para dos cosas: saber qué versión del
scaffold generó un sistema, y decidir si conviene traerse algo a un sistema que
ya nació.

El formato es deliberadamente humano: lo que cambió y qué problema resolvía. Los
detalles finos están en los mensajes de commit y en `docs/superpowers/`.

## Sin publicar

### Generador

- **El proyecto generado ya no nace con un `docker build` roto en CI**: las carpetas de caché
  de Laravel (`storage/framework/{views,sessions,cache/data}`) y `storage/logs` viajan con su
  `.gitkeep` en vez de excluirse enteras y recrearse vacías —git no versiona una carpeta
  vacía, así que el checkout limpio del CI no las tenía y `package:discover` moría con
  «Please provide a valid cache path» (dispositivos-graneros, run 36892413754)—. Además el
  Dockerfile las crea él mismo antes de `composer dump-autoload` y en la etapa final, para no
  depender de lo que traiga el checkout. Candado: `tests/Feature/CarpetasDeCacheEnElBuildTest`
  (viaja) y `tests/Generador/CarpetasDeCacheViajanConSuGitkeepTest`. Un sistema ya generado
  se arregla copiando el Dockerfile y agregando los cinco `.gitkeep` con `git add -f`.
- **Tras hacer pull, `./scaffold new` funciona aunque `vendor/` sea viejo**: si el autoload de
  Composer todavía no conoce `Generador\` (falta `composer dump-autoload`), `bin/scaffold`
  registra un autoloader de respaldo sobre `generador/`. Conviene igual correr
  `composer dump-autoload` (o `make up`) después del merge.
- **El generador es un binario PHP con manifiesto** (`bin/scaffold`; `./scaffold` queda como
  shim): `scaffold.json` es la única fuente de verdad de lo que se genera, llega validado
  antes de tocar el disco, y `--dry-run` imprime el plan completo sin escribir. Tres modos que
  producen el mismo objeto: asistente (laravel/prompts, en terminal), flags de siempre
  (`--shared`, `--frontend`, `--port`, `--no-up`, `--no-git`; nuevos `--preset`, `--con`,
  `--no-assets`, `--desde`) y `--desde=scaffold.json`. Un flag suelto pisa el campo y lo dice.
  Spec: `docs/superpowers/specs/2026-09-14-cli-scaffold-nucleo-design.md`.
- **Registro de presets que describe operaciones, no escrituras**: cada preset es
  `presets/<nombre>/{preset.json,Preset.php,archivos/}` y devuelve operaciones con
  `aplicar`/`verificar`/`describir`. Los aplicadores actuales (`usar-paquete-compartido.php`,
  `aplicar-frontend-inertia-react.php`, `parametrizar-identidad.php`,
  `quitar-lo-del-generador.php`) se envuelven, no se reescriben. Si una operación falla, el
  destino se borra: nunca un proyecto a medias con código 0.
- **Catálogo que no miente**: `scaffold catalogo [--json]` y `scaffold explicar <nombre>`
  salen del registro (incluido `livewire`, que no es un preset). `presets/esquema.json` es
  el JSON Schema del manifiesto, generado desde el registro y comparado por test.
- **Metadatos y `add`**: cada proyecto nace con `.scaffold-meta.json` (commit y rama del
  scaffold, entidad, preset, frontend, opcionales con versión, puertos, PHP y Node del
  equipo); `scaffold info` lo lee y `scaffold add <opcional>` aplica un opcional sobre un
  proyecto ya generado con árbol git limpio. El catálogo todavía no tiene opcionales
  elegibles (spec 3): el mecanismo se prueba con un preset de fixtures.
- **Contratos por preset y CI que genera de verdad**: `ContratosDePresetsTest` exige a
  todo preset descripción completa, versión semver, operaciones puras e idempotencia (los
  que envuelven una herramienta lo declaran con `"idempotente": false`); el job `generado`
  de CI genera un proyecto muni con la consola y corre adentro PHPStan, migraciones y Pest.
- **Los puertos del proyecto nuevo se deducen del compose también en el CLI**: el preset
  `completo` llama a `tools/parametrizar-puertos.php` (que trajo develop para el bash) en
  vez de los `ReemplazarTexto` con los literales `8001:8000"`/`3308:3306"`, comprueba que
  `docker-compose.yml` publique los puertos pedidos, y `localhost:<puerto>` se busca con el
  puerto que publica el compose del origen, no con uno fijo.
- **Los candados del generador ya no leen el bash**: los tests que vigilaban `scaffold` como
  texto preguntan ahora a `Exclusiones::delArbolBase()` y a las operaciones del preset
  `completo`; los que vivían en `tests/Feature` (y viajaban al proyecto nuevo) pasaron a
  `tests/Generador`.

### Arquitectura

- **Toda app nueva tiene tres capas** (decisión del 2026-09-11): landing pública
  en Blade para buscadores, app pública en Inertia + React (Fase 2, rama aparte)
  y paneles Filament en modo SPA. Este scaffold ya trae la primera: `/` es una
  portada sin JavaScript ni sesión, con `<head>` completo (description,
  canonical desde `app.url`, Open Graph, JSON-LD de la entidad), `robots.txt` y
  `sitemap.xml` generados solo para el dominio publicado, y caché con ETag. Se
  pinta con la paleta de la entidad —`tools/paletas-de-entidad.php` es ahora la
  única fuente— que `usar-paquete-compartido.php` escribe al generar. Vigilada
  por ocho archivos de tests en `tests/Feature/Sitio` y por axe en claro y
  oscuro. Spec: `docs/superpowers/specs/2026-09-11-arquitectura-tres-capas-landing-design.md`.
  Decisiones del camino: el grupo `sitio` vence una cookie vieja de «Recordarme»
  sin abrir sesión y esa única respuesta con cookie sale `no-store`; el ETag
  ignora el nonce; los enlaces internos son relativos.

### Consola de operador (--frontend=inertia-react)

- **Vuelve a generar sobre develop**: el aplicador parcheaba `SecurityHeaders.php`, que la
  política de spatie retiró; la consola hereda ahora `PoliticaDelSitio`.
- **Los tipos del front salen del PHP**: Data classes en `app/Data/Consola`, `generated.d.ts`
  generado por spatie/laravel-typescript-transformer v3 y un test que falla si quedó
  desincronizado. Ningún modelo Eloquent llega a Inertia.
- **Árbol en `pages/`, `components/ui/`, `lib/`, `types/` con alias `@/`**, y scripts `dev` /
  `types:generate` / `types:check`.

### Seguridad

- **La política de contenido la declara `spatie/laravel-csp`**, no un middleware
  artesanal. `SecurityHeaders.php` era una de 16 copias divergentes del
  ecosistema; ahora dos presets tipados dicen la política completa del sitio y
  del panel, el nonce lo reparte el contenedor y llega igual a la cabecera, a
  `@cspNonce`, a `@vite` y a Livewire. Un nonce vive exactamente una petición,
  aunque el proceso no muera (Octane): hay un test que exige dos nonces
  distintos en dos visitas seguidas. El panel sigue sin nonce en `script-src`
  a propósito —Filament 5.7.6 escribe guiones en línea sin etiquetar y un nonce
  apagaría `'unsafe-inline'`—; `worker-src` y `media-src` con `blob:` para
  FilePond vienen de fábrica. Las demás cabeceras viven en
  `CabecerasDeEndurecimiento`, con sus valores fijados por test.
- **El segundo factor entrega el código.** Antes se generaba, se guardaba y no
  se enviaba a ninguna parte: en producción, con el código oculto —lo correcto—,
  el panel quedaba cerrado para todos, incluido quien lo instaló.
- **Lo que sube alguien ya no se puede ejecutar.** Un `.php` dejado en
  `storage/app/public` se ejecutaba al pedirlo por `/storage`: comprobado en
  vivo. Cerrado en dos capas —lista de tipos permitidos en los campos, y el
  servidor negándose a servir extensiones ejecutables bajo `/storage` y
  `/uploads`—.
- **La política de contenido frena inyecciones** en vez de solo existir: nonce
  por petición fuera del panel, y la excepción que Filament necesita acotada a
  `/admin`.
- **La política la escribe `spatie/laravel-csp`, no 174 líneas propias.** Dos
  presets tipados —`PoliticaDelSitio` y `PoliticaDelPanel`— en vez de una cadena
  y una lista de prefijos escrita a mano: la del panel se cuelga donde vive el
  panel, así que un sistema generado que llame al suyo «feria» o
  «discapacidad» hereda la política correcta en vez de quedarse sin JavaScript.
  La cabecera que sale por el cable es la misma de antes, directiva por
  directiva.
- **El nonce dejó de regalarse**: el marcador fijo que se sustituía en todo el
  cuerpo permitía que una inyección reclamara un nonce válido.
- **Queda constancia de quién entra**, sin guardar la dirección IP en claro
  (huella con la sal de la aplicación, Ley 21.719) ni la contraseña tecleada.
- **Adivinar una contraseña cuesta**: se limita por cuenta y por origen a la vez.
  Contar solo por origen dejaba sin panel a toda una oficina que sale a internet
  por la misma dirección.
- **Las credenciales de ejemplo no llegan a producción**: arrancar con la
  contraseña que está publicada en este repositorio falla, diciendo qué cambiar.
- **La sesión dura una jornada** y se corta si aparece desde otra red.
- **Nada se guarda para siempre**: trabajos fallidos e historial del panel se
  podan solos.

### Panel

- **El escritorio dejó de estar vacío**: lo que requiere atención hoy, el estado
  del sistema y el movimiento reciente —este último solo de lo que cada persona
  administra—.
- **Se comporta como una sola aplicación**: navegación sin recargar, buscador con
  ⌘K y aviso antes de abandonar un formulario a medio llenar.
- **El botón de ayuda vuelve a servir**: llamaba a una función que no existía en
  ningún archivo del proyecto.
- **El ciclo ARCOP de la Ley 21.719 viene montado** desde los paquetes
  compartidos. Cada sistema pone lo único que un paquete no puede saber: cómo
  encuentra a la persona, y qué deja de tratarse de verdad cuando alguien se
  opone.

### Accesibilidad y rendimiento

- **Se comprueban solos**, en cada corrida: WCAG 2.2 AA en cinco pantallas por
  dos modos, recorrido completo por teclado, y presupuesto de rendimiento.
  Encontraron el foco invisible en el menú lateral —el panel no se podía navegar
  sin ratón—, áreas táctiles por debajo de 24 px y contrastes insuficientes.
- **Medido**: LCP 668 ms en el escritorio, CLS 0,000.

### Configuración y despliegue

- **Una feature se apaga en producción sin redeploy y sin tocar el `.env`**
  (`laravel/pennant`): `php artisan feature asistente-ia apagada` escribe un
  interruptor global en la base y Octane lo ve en la petición siguiente.
  `config/features.php` declara con qué valor nace cada una; `asistente-ia`
  nace apagada. El scope por defecto es el del sistema, no el usuario: con el
  de fábrica, «apagar para todos» no apagaba nada para quien entraba después.

- **La zona horaria y el idioma dejaron de ignorarse**: sin `config/app.php`,
  Laravel corría en UTC por más que el `.env` dijera otra cosa.
- **Subir una foto de teléfono ya no falla en silencio**: el contenedor limitaba
  a 2 MB. Y las fotos se reducen en el navegador antes de subirse.
- **Los estilos del panel llevan la huella de su contenido**: sin ella un arreglo
  tardaba hasta un día en verse, y nunca para quien tuviera la pestaña abierta.
- **Despliegue por runner dentro del servidor**: sin llaves SSH en GitHub ni
  puerto abierto a internet.

### Generador

- **El generador elige el frontend**: `--frontend=livewire|inertia-react`. `livewire` es el
  valor por defecto y produce exactamente el proyecto de siempre; `inertia-react` suma una
  consola de operador en `/consola` (Inertia 3 + React 19 + TypeScript) **sin tocar el panel**:
  Filament, Shield, ARCOP, muni-ui, policies, jobs y despliegue quedan igual. Vive como preset
  fuera del árbol Laravel, así que un proyecto que no la pide no arrastra React ni un segundo
  juego de tests que mantener en verde.
- **La consola nace con la política de contenido más estricta del proyecto**: nonce y sin
  `unsafe-inline` —Inertia 3 entrega el estado en un bloque de datos,
  `<script data-page="app" type="application/json">`, que el navegador no ejecuta, así que no lo
  necesita— y props compartidas sin datos personales. Un frontend nuevo es una puerta nueva.
- **La consola no queda en el disco de un equipo compartido**: se sirve con `no-store`. Con
  Inertia la pantalla entera —permisos, recuentos, la bitácora— viaja dentro del HTML, y sin eso
  la persona siguiente en el mostrador pulsa «Atrás» después de que la anterior cerró sesión y
  vuelve a verlo todo, sin pasar por el servidor.
- **Rotar una contraseña cierra también la consola**, no sólo el panel. Faltaba `auth.session`:
  quien robaba una sesión perdía `/admin` y conservaba `/consola` abierta con la misma cookie —la
  mitad del sistema que nadie revisa después de un incidente.
- **La bitácora tiene su propio techo de peticiones**, por usuario y no por dirección. Es un
  amplificador barato —una petición de nada devuelve cien filas de la tabla que más crece—, y
  reutilizar el limitador del panel comparte el contador entre pantallas que no tienen nada que
  ver: ya reventó una batería de pruebas entera en el ecosistema.
- **Cada pantalla exige su permiso en el servidor**: el escritorio, el mismo que gobierna la
  puerta del panel; la bitácora, el mismo del recurso equivalente. Esconder un botón en el
  navegador no autoriza nada, y antes el escritorio contaba cuántas cuentas hay y si esto es
  producción a cualquiera con sesión.
- **Queda constancia de quién lee la bitácora**, en un canal aparte para que no se liste a sí
  misma. Leerla es acceder a datos personales de terceros, y la Ley 21.719 pide trazabilidad de
  los accesos, no sólo de las modificaciones: era la única acción del sistema que no dejaba
  huella.
- **Publicar una ruta al navegador es un acto explícito**: la lista blanca de Ziggy las enumera
  una a una, no por familia. Con `consola.*` la pantalla siguiente que alguien agregue se publica
  sola, con su dirección y sus parámetros, sin que nadie lo haya aprobado.
- **El mismo trío de acceso, sesión acotada y segundo factor que el panel**, más `web` explícito:
  el archivo de rutas se registra por su cuenta, y sin eso los otros tres miran un request que
  nunca supo quién era y dejan pasar a todo el mundo como invitado.
- **Está escrito cuándo NO usarla**, en `docs/FRONTEND-INERTIA-REACT.md`: cuatro señales
  medidas, no imaginadas. Si ninguna se cumple, la solución es quedarse en Livewire y arreglar
  el dolor real.
- **`--shared` dejó de ser folklore oral**: `docs/PAQUETE-COMPARTIDO.md` explica qué reescribe,
  qué borra y por qué no tiene valor por defecto. El generador ya remitía a ese archivo en un
  comentario y el archivo no existía.

### Flujo git

- **El `pre-push` decide por lo que se envía, no por la rama en la que uno está
  parado**: borrar una rama remota o publicar una rama de trabajo desde
  `develop` ya no corre la suite, y `git push origin HEAD:develop` desde una
  rama de trabajo sí. Además invoca la suite con `docker compose exec -T`
  (`DC_EXEC_FLAGS` en el Makefile): dentro de un hook stdin no es una terminal
  y sin `-T` docker se negaba a arrancar, así que el hook cancelaba TODO envío
  a `develop` con los contenedores arriba —justo el caso que quería proteger.
  Un test hace `git push` de verdad contra un remoto en disco y lo prueba.

### Decisiones tomadas con medición, no con intuición

- **React no pasa a ser el default**: el sistema que «obviamente lo necesitaba» —la consola de
  seguridad— no cumplía ninguna de las cuatro señales al medirlo. Su parte interactiva ya no era
  Livewire, sino ~1.950 líneas de JavaScript suelto, y con menos de veinte móviles en calle no
  había dolor que arreglar. Cambiar de framework habría movido el problema de lugar.
- **Rust (`lightningcss`) no entra**: medido con el mismo código de entrada, sale
  0,3 % peor que el minificador de fábrica.
- **WebAssembly no hace falta**: Filament ya redimensiona las imágenes en el
  navegador, con cero kilobytes extra.
- **`filament-shield` se queda en 4.2**: la 4.3 renombra todos los permisos, que
  son filas ya asignadas a personas en sistemas en producción.
