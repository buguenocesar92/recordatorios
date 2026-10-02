<?php

/**
 * Instala el frontend de consola (Inertia + React + TypeScript) sobre un
 * proyecto recién generado: copia el preset, declara las dependencias y
 * parchea los tres puntos del esqueleto Laravel que no se pueden resolver
 * copiando archivos (carga de rutas, CSP y bloqueos de dependencias).
 *
 * El middleware de Inertia NO se parchea acá: viaja en el grupo de
 * `routes/consola.php`, que sí es un archivo del preset. Ver el bloque 4.
 *
 * Lo llama `./scaffold new ... --frontend=inertia-react`. Se hace en PHP y no
 * en sed por la misma razón que `usar-paquete-compartido.php`: hay que editar
 * JSON respetando su forma y hay que insertar bloques de código PHP con
 * comillas y backslashes, donde el doble escape entre bash y las expresiones
 * regulares falla en silencio.
 *
 * El proyecto sigue siendo el mismo de siempre —Filament, Shield, ARCOP,
 * muni-ui, policies, jobs y despliegue no se tocan—. Lo único que se añade es
 * una capa de vistas para superficies de operador, montada en /consola.
 *
 * La ENTIDAD es obligatoria y va en $argv[2], igual que en
 * `usar-paquete-compartido.php`. No es burocracia: la consola nace con la
 * identidad visual de su entidad, y hasta el 2026-09-05 el `scaffold` llamaba a
 * este script SIN pasarla. Resultado: `./scaffold new X --shared=kraftdo
 * --frontend=inertia-react` entregaba una consola de KraftDo pintada con la
 * paleta oficial de la Municipalidad de Graneros, que el `CLAUDE.md` del
 * ecosistema prohíbe explícitamente. Ver el bloque 1.b y la cabecera de
 * `presets/inertia-react/archivos/resources/css/consola.css`.
 *
 * Uso: php tools/aplicar-frontend-inertia-react.php <destino> <muni|kraftdo>
 */
$dest = $argv[1] ?? '';
$eco = $argv[2] ?? '';

if ($dest === '' || ! is_dir($dest) || ! is_file($dest.'/artisan')) {
    fwrite(STDERR, "Destino inválido (no parece un proyecto Laravel): {$dest}\n");
    exit(1);
}

/**
 * Primitivos de color por entidad. Se leen de `tools/paletas-de-entidad.php`,
 * la ÚNICA fuente de las paletas del scaffold desde la landing pública
 * (2026-09-13): `usar-paquete-compartido.php` escribe los mismos 20 primitivos
 * por rol en `sitio.css` (la portada) y este script en `consola.css` (la
 * consola), así que la portada, el panel y la consola de una entidad se ven
 * iguales y ninguna entidad hereda la identidad de otra. Hasta ese día este
 * script llevaba su propia copia de la tabla, idéntica hex por hex: dos copias
 * que sólo podían divergir.
 *
 * `neutra` —lo que el scaffold base trae escrito y no es de nadie— se descarta
 * a propósito: la consola no se genera «sin pintar», sólo para una entidad.
 *
 * Contrato completo y contrastes medidos: la cabecera de `consola.css`. Las
 * relaciones WCAG de las DOS paletas las recalcula
 * `PaletaDeLaConsolaSigueLaEntidadTest` en cada corrida.
 */
$paletas = array_intersect_key(
    require __DIR__.'/paletas-de-entidad.php',
    array_flip(['muni', 'kraftdo']),
);

// Misma guarda y mismo mensaje que `usar-paquete-compartido.php`: sin entidad
// no se puede pintar la consola, y adivinarla es mezclar frentes.
if (! isset($paletas[$eco])) {
    fwrite(STDERR, "Ecosistema desconocido: {$eco} (usa: ".implode(', ', array_keys($paletas)).")\n");
    fwrite(STDERR, "La consola nace con la identidad visual de su entidad; sin saber cuál es, no se genera.\n");
    exit(1);
}

if (count($paletas[$eco]) !== 20) {
    fwrite(STDERR, "tools/paletas-de-entidad.php no tiene la paleta «{$eco}» con sus 20 primitivos: no se puede pintar la consola.\n");
    exit(1);
}

$preset = __DIR__.'/../presets/inertia-react/archivos';

if (! is_dir($preset)) {
    fwrite(STDERR, "No encuentro el preset en {$preset}\n");
    exit(1);
}

// El destino NO puede ser este mismo repositorio.
//
// `php tools/aplicar-frontend-inertia-react.php .` desde la raíz del scaffold
// pasa las dos comprobaciones de arriba —hay un `artisan` y hay un preset— y a
// partir de ahí el script trabaja sobre su propia fuente: pisa `vite.config.js`
// con la versión del preset, siembra `config/ziggy.php` y `config/inertia.php`
// en el scaffold, y le mete a su composer.json dependencias que el scaffold no
// declara. Nada de eso avisa: el resultado es una rama con cambios que nadie
// escribió y un generador que ya viene con medio Inertia dentro.
//
// Se compara por ruta real —no por la cadena que llegó por argumento— porque
// «.», «./» y un enlace simbólico apuntan al mismo sitio y ninguna comparación
// de texto los ve iguales.
$raizDelScaffold = realpath(__DIR__.'/..');
$raizDelDestino = realpath($dest);

if ($raizDelScaffold !== false && $raizDelScaffold === $raizDelDestino) {
    fwrite(STDERR, "El destino es el propio scaffold ({$raizDelDestino}). Esta herramienta se aplica a un proyecto GENERADO, nunca al repositorio que lo genera.\n");
    exit(1);
}

// Segundo cinturón, por si algún día el aplicador se copia a otro sitio y la
// comparación de rutas deja de coincidir: un proyecto generado nunca lleva el
// preset dentro.
if (is_dir($dest.'/presets/inertia-react')) {
    fwrite(STDERR, "El destino contiene presets/inertia-react: parece el scaffold y no un proyecto generado.\n");
    exit(1);
}

/**
 * Versiones verificadas contra packagist y el registro de npm el 2026-09-05.
 *
 * No se tocan a ojo. `VersionesDelFrontendInertiaTest` compara estos números
 * con los del spec: subir uno acá sin subirlo allá deja al proyecto generado
 * instalando una combinación que nadie probó. La comprobación importante es
 * que `@inertiajs/vite` declara `vite ^7 || ^8` como peer y el scaffold trae
 * Vite ^8.2.2 — si el scaffold sube de major, esto hay que volver a mirarlo.
 */
$composerRequiere = [
    // v3.3.3 requiere laravel/framework ^11.35|^12.0|^13.0 y php ^8.2.0.
    'inertiajs/inertia-laravel' => '^3.3',
    // Data classes: la única forma de pasarle algo a Inertia::render. Un modelo
    // Eloquent serializa TODAS sus columnas, incluidas las que nadie pidió.
    // 4.23.0 (2026-05-08): laravel ^10|^11|^12|^13, php ^8.1.
    'spatie/laravel-data' => '^4.23',
    // Genera resources/js/consola/types/generated.d.ts desde app/Data. Va en
    // `require` y no en `require-dev`: TiposGeneradosAlDiaTest regenera dentro
    // de la suite y la imagen de producción instala sin dev.
    // 3.3.0 (2026-06-19): illuminate/contracts ^11|^12|^13, php ^8.2.
    'spatie/laravel-typescript-transformer' => '^3.3',
    // Ziggy publica las rutas con nombre al navegador. Va con lista blanca:
    // ver config/ziggy.php.
    'tightenco/ziggy' => '^2.6',
];

$npmDependencias = [
    '@inertiajs/react' => '^3.7',
    // El stage `assets` del Dockerfile corre `npm ci --omit=dev`: un plugin de
    // Vite en devDependencies deja la imagen sin compilar la consola.
    '@inertiajs/vite' => '^3.7',
    '@tanstack/react-table' => '^9.2',
    // Mismo motivo que `@inertiajs/vite`: Vite lo necesita en el build de
    // producción, no solo en desarrollo.
    '@vitejs/plugin-react' => '^6.1',
    'react' => '^19.2',
    'react-dom' => '^19.2',
    'ziggy-js' => '^2.6',
];

$npmDesarrollo = [
    '@testing-library/react' => '^16.3',
    '@types/react' => '^19.2',
    '@types/react-dom' => '^19.2',
    // El `dev` de acá abajo corre `php artisan typescript:transform --watch`, y ese
    // comando delega en `spatie/file-system-watcher`, que a su vez ejecuta un binario
    // de Node. Sin este paquete el watcher no arranca: «Could not start watcher. Make
    // sure you have required chokidar» (revelado por una generación en limpio, Tarea
    // 9). Versión mayor fija en «4» porque es la que el propio paquete vendorizado
    // declara en su `package.json`.
    'chokidar' => '^4.0',
    // Sin esto, tsc no encuentra tipos para `node:url`, que usa vitest.config.ts
    // para resolver el alias `@/` (vite.config.js ya NO lo importa: el candado
    // HigieneDeLaEtapaDeAssets lo rechaza, ver Tarea 10 Parte C). Misma versión
    // que ya fija `e2e/package.json` para no tener dos criterios sueltos.
    '@types/node' => '^20.19.43',
    'jsdom' => '^28.0',
    'typescript' => '^5.9',
    // NO subir a ^5.0 sin comprobarlo: vitest 5.0.0 (2026-09-03) hace explotar
    // la resolución de npm 10.8.2 con «Cannot read properties of null (reading
    // 'edgesOut')» dentro de #loadPeerSet — el proyecto generado no llega ni a
    // instalar. La línea 4.1 declara `vite ^6 || ^7 || ^8`, que es lo que trae
    // el scaffold, y resuelve limpio.
    'vitest' => '^4.1',
];

$npmScripts = [
    // Pisa el `dev` del scaffold (`vite`): sin HMR a propósito (spec
    // 2026-09-11 §1). Compila en modo watch y regenera los tipos cuando cambia
    // app/Data. `php` local ya es prerrequisito del flag.
    'dev' => 'concurrently -n vite,tipos -c blue,magenta "vite build --watch" "php artisan typescript:transform --watch"',
    'types:generate' => 'php artisan typescript:transform',
    // Para CI y antes de mergear: si el front quedó desincronizado del back,
    // tsc falla contra el archivo recién regenerado.
    'types:check' => 'php artisan typescript:transform && tsc --noEmit',
    'test:js' => 'vitest run',
];

// ---------------------------------------------------------------------------
// 1) Copiar el preset sobre el proyecto
// ---------------------------------------------------------------------------
$copiados = 0;

// Cuántos archivos del proyecto quedaron reemplazados por los del preset.
//
// La copia pisa el destino a propósito —hoy el único caso real es
// `vite.config.js`, y la versión del preset es un superconjunto de la del
// scaffold: añade el entrypoint de la consola y el plugin de React sin quitar
// nada—. Lo que no puede pasar es que lo haga en silencio: el día que el preset
// crezca y empiece a tapar un archivo que alguien editó a mano, el número de
// abajo es lo único que lo delata antes de que el proyecto se comporte raro.
$sobrescritos = [];

$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($preset, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

/** @var SplFileInfo $archivo */
foreach ($iterador as $archivo) {
    $relativo = substr($archivo->getPathname(), strlen($preset) + 1);
    $destino = $dest.'/'.$relativo;

    if ($archivo->isDir()) {
        if (! is_dir($destino)) {
            mkdir($destino, 0o755, true);
        }

        continue;
    }

    $carpeta = dirname($destino);
    if (! is_dir($carpeta)) {
        mkdir($carpeta, 0o755, true);
    }

    if (is_file($destino)) {
        $sobrescritos[] = $relativo;
    }

    copy($archivo->getPathname(), $destino);
    $copiados++;
}

// ---------------------------------------------------------------------------
// 1.b) Identidad visual de la entidad en la hoja de la consola
// ---------------------------------------------------------------------------
// `consola.css` viaja SIN paleta: declara el contrato de primitivos por rol y
// deja un marcador donde va el bloque de valores. Acá se escribe el de la
// entidad de este proyecto.
//
// Si el marcador no está —alguien horneó una paleta a mano, o reformateó el
// archivo—, la sustitución no ocurre, todos los `var(--marca-*)` quedan sin
// valor y la consola sale sin colores. Eso NO puede salir con código 0: se
// comprueba justo después y el `exit(1)` del final lo recoge.
$rutaCss = $dest.'/resources/css/consola.css';
$css = (string) file_get_contents($rutaCss);

$marcador = '/* PRIMITIVOS-DE-LA-ENTIDAD */';

$lineas = [];
foreach ($paletas[$eco] as $token => $valor) {
    $lineas[] = "    {$token}: {$valor};";
}

$bloqueDePrimitivos = "/* Escrito por tools/aplicar-frontend-inertia-react.php para --shared={$eco}.\n"
    ."   No editar a mano: la fuente de verdad es tools/paletas-de-entidad.php. */\n"
    .":root {\n".implode("\n", $lineas)."\n}";

$css = str_replace($marcador, $bloqueDePrimitivos, $css);

file_put_contents($rutaCss, $css);

// La comprobación es sobre el resultado, no sobre la intención: el marcador se
// tiene que haber ido Y el token que lee `app.tsx` para la barra de progreso
// tiene que estar escrito.
$paletaOk = ! str_contains($css, $marcador)
    && str_contains($css, '--marca-progreso:');

// Y la consola de una entidad NO puede acabar con los primitivos de otra. Es el
// candado del incidente del 2026-09-05: barato de comprobar acá mismo, y lo
// vigila además `PaletaDeLaConsolaSigueLaEntidadTest`.
if ($eco !== 'muni' && str_contains($css, '--mg-')) {
    fwrite(STDERR, "La hoja de la consola quedó con primitivos «--mg-» (identidad de la Municipalidad de Graneros) en un proyecto --shared={$eco}.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 2) composer.json — dependencias del lado servidor
// ---------------------------------------------------------------------------
$rutaComposer = $dest.'/composer.json';
$composer = json_decode((string) file_get_contents($rutaComposer), true);

if (! is_array($composer)) {
    fwrite(STDERR, "composer.json ilegible en {$dest}\n");
    exit(1);
}

foreach ($composerRequiere as $paquete => $version) {
    $composer['require'][$paquete] = $version;
}

ksort($composer['require']);

file_put_contents(
    $rutaComposer,
    json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

// ---------------------------------------------------------------------------
// 3) package.json — dependencias del lado navegador
// ---------------------------------------------------------------------------
$rutaPackage = $dest.'/package.json';
$package = json_decode((string) file_get_contents($rutaPackage), true);

if (! is_array($package)) {
    fwrite(STDERR, "package.json ilegible en {$dest}\n");
    exit(1);
}

foreach ($npmDependencias as $paquete => $version) {
    $package['dependencies'][$paquete] = $version;
}

foreach ($npmDesarrollo as $paquete => $version) {
    $package['devDependencies'][$paquete] = $version;
}

foreach ($npmScripts as $nombre => $comando) {
    $package['scripts'][$nombre] = $comando;
}

// Reemplazado por types:check.
unset($package['scripts']['typecheck']);

ksort($package['dependencies']);
ksort($package['devDependencies']);

file_put_contents(
    $rutaPackage,
    json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

// ---------------------------------------------------------------------------
// 4) bootstrap/app.php — carga de las rutas de la consola
// ---------------------------------------------------------------------------
//
// Acá NO se registra `HandleInertiaRequests`, y es a propósito.
//
// La primera versión lo metía en `$middleware->web(append: [...])`, es decir en
// el grupo `web` GLOBAL. Con eso, `Inertia::share()` y `setRootView('consola')`
// quedaban activos en TODAS las peticiones web del sistema, no solo en las de
// la consola. El día que alguien escriba un `Inertia::render()` en una ruta
// pública —una consulta de estado para el vecino, una página de seguimiento sin
// sesión—, esa respuesta hereda sin decisión de nadie los permisos del usuario
// autenticado y el mapa de rutas de Ziggy, y los publica en el HTML. Nadie
// habría elegido eso: se habría heredado.
//
// El middleware va donde vive lo que protege: en el array de middleware del
// grupo de `routes/consola.php`. Ahí su alcance es exactamente el prefijo
// `/consola`, y una ruta nueva fuera de ese archivo no comparte nada por
// accidente.
$rutaBootstrap = $dest.'/bootstrap/app.php';
$bootstrap = (string) file_get_contents($rutaBootstrap);

// El `use` de la fachada Route se ancla en `use Sentry\Laravel\Integration;`,
// una línea del scaffold que este script no controla. Si el scaffold quitara
// Sentry o reordenara sus imports, la sustitución no ocurriría —en silencio— y
// el `then:` de más abajo SÍ se insertaría, porque su ancla (`health: '/up',`)
// seguiría existiendo: el proyecto saldría con un `bootstrap/app.php` que
// referencia `Route::` sin importarla y no compila. Por eso hay comprobación
// posterior propia, y no basta con la del archivo de rutas.
if (! str_contains($bootstrap, 'use Illuminate\Support\Facades\Route;')) {
    $bootstrap = str_replace(
        'use Sentry\Laravel\Integration;',
        "use Illuminate\Support\Facades\Route;\nuse Sentry\Laravel\Integration;",
        $bootstrap
    );
}

// `routes/consola.php` se carga con `then` y sin middleware acá: el propio
// archivo declara su grupo con ['web','auth','sesion.acotada','mfa'], que es
// el mismo trío del panel. Añadirle 'web' otra vez desde aquí duplicaría
// StartSession y la sesión se leería dos veces por petición.
$cargaDeConsola = <<<'PHP'
            // La consola de operador (Inertia + React). El archivo trae su
            // propio grupo de middleware; ver routes/consola.php.
            Route::group([], base_path('routes/consola.php'));
PHP;

// Desde la landing pública (2026-09-13) el scaffold YA trae un `then:` en
// `withRouting()`: el que carga `routes/sitio.php` con el grupo «sitio».
// `withRouting()` admite un solo argumento `then`, así que la consola no puede
// sumar el suyo: se injerta DENTRO de ese closure, anclando en su única
// sentencia. `AnclajesDelAplicadorDeFrontendTest` vigila que el scaffold siga
// ofreciendo esta línea tal cual.
$anclaDeLaLanding = "            Route::middleware('sitio')->group(base_path('routes/sitio.php'));";

// Y la forma vieja, para un esqueleto SIN ningún `then:` (anterior a la
// landing): ahí sí se declara el argumento entero, anclado en la ruta de salud.
$cargaDeRutas = <<<PHP
        health: '/up',
        then: function () {
{$cargaDeConsola}
        },
PHP;

if (! str_contains($bootstrap, "base_path('routes/consola.php')")) {
    if (str_contains($bootstrap, $anclaDeLaLanding)) {
        $bootstrap = str_replace($anclaDeLaLanding, $anclaDeLaLanding."\n".$cargaDeConsola, $bootstrap);
    } elseif (str_contains($bootstrap, 'then:')) {
        // Un `then:` que no es el de la landing ni el de la consola: insertar
        // el nuestro a ciegas dejaría DOS argumentos con nombre `then` en
        // `withRouting()` —fatal en el arranque— y este script seguiría
        // saliendo con código 0, porque ninguna comprobación posterior mira
        // otra cosa que `routes/consola.php`. Morir acá, antes de escribir
        // nada, en vez de adivinar dónde va la línea.
        fwrite(STDERR, "bootstrap/app.php ya declara un `then:` en withRouting() que no es el de la landing ni el de la consola.\n");
        fwrite(STDERR, "Insertarlo a ciegas dejaría dos argumentos `then:` (fatal al arrancar). Fusionalos a mano: la consola necesita\n");
        fwrite(STDERR, "    Route::group([], base_path('routes/consola.php'));\n");
        fwrite(STDERR, "dentro de ese closure.\n");
        exit(1);
    } else {
        $bootstrap = str_replace("        health: '/up',", $cargaDeRutas, $bootstrap);
    }
}

file_put_contents($rutaBootstrap, $bootstrap);

// Lo único que este parche tiene que dejar hecho en bootstrap/app.php es la
// carga del archivo de rutas. La cadena que se comprueba es la que el propio
// parche escribe, no una genérica: si mañana el scaffold trae otro `then:` por
// su cuenta, esto no lo confunde con el nuestro.
$rutasOk = str_contains($bootstrap, "base_path('routes/consola.php')");

// Y el `use` que ese `then:` necesita para compilar. Se comprueba aparte
// porque su ancla es otra y puede fallar sola.
$usoDeRouteOk = str_contains($bootstrap, 'use Illuminate\Support\Facades\Route;');

// ---------------------------------------------------------------------------
// 5) Política de contenido: NO se parchea nada
// ---------------------------------------------------------------------------
// Hasta el 2026-09-08 acá se editaba a mano el middleware artesanal que le
// daba a «/consola» una política propia. Ese middleware ya no existe: la
// política la declara spatie/laravel-csp desde `app/Support/Csp/PoliticaDelSitio.php`
// (`script-src 'self' 'nonce-…'`, sin unsafe-inline ni unsafe-eval) y la monta
// el grupo «web», que `routes/consola.php` lleva explícito. Es la misma
// política estricta que el parche escribía a mano, sin el parche. El candado
// vive en el proyecto generado: `PoliticaDeContenidoDeLaConsolaTest`.

// ---------------------------------------------------------------------------
// 5.b) bootstrap/providers.php — el provider que genera los tipos
// ---------------------------------------------------------------------------
// `App\Providers\TypeScriptTransformerServiceProvider` viaja con el preset y
// configura spatie/laravel-typescript-transformer v3 (que se configura por
// provider, no por config). Sin registrarlo, `typescript:transform` corre con
// la configuración por defecto del paquete y escribe en otra ruta: el
// generated.d.ts de la consola queda vacío y tsc falla en cada tipo `App.Data`.
$rutaProviders = $dest.'/bootstrap/providers.php';
$providers = (string) file_get_contents($rutaProviders);

if (! str_contains($providers, 'TypeScriptTransformerServiceProvider')) {
    $providers = str_replace(
        'use App\Providers\Filament\AdminPanelProvider;',
        "use App\Providers\Filament\AdminPanelProvider;\nuse App\Providers\TypeScriptTransformerServiceProvider;",
        $providers
    );
    $providers = str_replace(
        "    AdminPanelProvider::class,\n];",
        "    AdminPanelProvider::class,\n    TypeScriptTransformerServiceProvider::class,\n];",
        $providers
    );
}

file_put_contents($rutaProviders, $providers);

// Las dos sustituciones por separado: el `use` sin la entrada no hace nada, y
// la entrada sin el `use` no compila.
$providerOk = str_contains($providers, 'use App\Providers\TypeScriptTransformerServiceProvider;')
    && str_contains($providers, '    TypeScriptTransformerServiceProvider::class,');

// ---------------------------------------------------------------------------
// 5.c) CLAUDE.md — las convenciones de la consola viajan con el proyecto
// ---------------------------------------------------------------------------
// El CLAUDE.md del scaffold ya permite React sólo en la consola de operador
// (`/consola`, vía Inertia): no hay ninguna restricción que reescribir. Lo que
// falta es DÓNDE vive cada cosa, que los tipos se generan y no se escriben, la
// regla de Data class y los comandos — eso se anexa desde
// presets/inertia-react/CLAUDE-consola.md, que no vive en `archivos/` porque
// no es un archivo nuevo del proyecto sino un trozo de uno que ya existe.
$rutaClaude = $dest.'/CLAUDE.md';
$claude = (string) file_get_contents($rutaClaude);
$seccionDeLaConsola = (string) file_get_contents(__DIR__.'/../presets/inertia-react/CLAUDE-consola.md');

if (! str_contains($claude, '## Consola de operador (Inertia + React)')) {
    $claude = rtrim($claude)."\n".$seccionDeLaConsola;
}

file_put_contents($rutaClaude, $claude);

$claudeOk = str_contains($claude, '## Consola de operador (Inertia + React)');

// ---------------------------------------------------------------------------
// 6) Bloqueos de dependencias
// ---------------------------------------------------------------------------
// composer.json y package.json cambiaron; sus locks no. El entrypoint del
// contenedor corre `composer install` y el generador corre `npm ci`, y ambos
// se niegan a trabajar con un lock desfasado. Se intenta sincronizarlos acá;
// si no se puede —sin red, o sin token para los paquetes privados— se avisa
// con el comando exacto en vez de dejar que el proyecto reviente al arrancar.
$avisos = [];

$sincronizar = function (string $comando, string $etiqueta) use ($dest, &$avisos): void {
    // `exec()` AÑADE al arreglo que recibe en vez de vaciarlo. Sin esta línea,
    // la segunda llamada (npm) arrastraba la salida de la primera (composer) y
    // cualquier lectura de `$salida` para depurar mostraba dos comandos
    // mezclados como si fueran uno.
    $salida = [];

    exec(sprintf('cd %s && %s 2>&1', escapeshellarg($dest), $comando), $salida, $codigo);

    if ($codigo !== 0) {
        $avisos[] = "no se pudo actualizar {$etiqueta}; correr a mano en el proyecto:\n      {$comando}";
        // Fallo silencioso encontrado en el CI del PR #45: el aviso de arriba
        // no dice POR QUÉ falló, y quien lo lee no puede reproducirlo. Las
        // últimas ~40 líneas (stdout+stderr, ya mezclados por el «2>&1» de
        // arriba) van a STDERR con un encabezado, que es donde las mira quien
        // corre `new` a mano y donde las junta `ComandoNew` en el mensaje de
        // error si la operación termina fallando.
        $ultimas = array_slice($salida, -40);
        fwrite(STDERR, "\n=== {$etiqueta}: «{$comando}» salió con código {$codigo} ===\n".implode("\n", $ultimas)."\n=== fin de la salida de «{$comando}» ===\n\n");
    }
};

if (trim((string) shell_exec('command -v composer')) !== '') {
    // `--no-scripts` sin `--no-plugins` no aísla nada: los plugins de Composer
    // declarados en el composer.json del DESTINO se cargan y ejecutan código
    // igual. Y el destino llega por argumento —esta herramienta está pensada
    // para apuntarla a un directorio que no necesariamente escribiste tú—, así
    // que resolver su lock no puede implicar correr su código.
    $sincronizar(
        'composer update '.implode(' ', array_keys($composerRequiere)).
        ' --no-install --no-interaction --no-scripts --no-plugins --quiet',
        'composer.lock'
    );
} else {
    $avisos[] = 'composer no está en el PATH; composer.lock quedó desfasado.';
}

if (trim((string) shell_exec('command -v npm')) !== '') {
    // Sin `--silent`: ese flag es `--loglevel=silent` en npm, que no baja el
    // ruido — apaga TODO, errores incluidos. Con él, un `npm install` que
    // fallara (ERESOLVE, red, registro) no dejaba una sola línea que capturar
    // arriba: por eso el CI del PR #45 no explicaba nada. `--no-audit
    // --no-fund` sí siguen puestos: esos no tocan los errores, sólo el ruido
    // informativo que nadie necesita acá.
    $sincronizar('npm install --package-lock-only --no-audit --no-fund', 'package-lock.json');
} else {
    $avisos[] = 'npm no está en el PATH; package-lock.json quedó desfasado.';
}

// ---------------------------------------------------------------------------
// 7) Reporte
// ---------------------------------------------------------------------------
echo "   archivos del preset copiados: {$copiados}\n";

if ($sobrescritos !== []) {
    echo '   de ellos, '.count($sobrescritos).' reemplazaron un archivo que ya existía: '
        .implode(', ', $sobrescritos)."\n";
}

echo '   composer.json requiere '.implode(', ', array_keys($composerRequiere))."\n";
echo '   package.json añade '.count($npmDependencias + $npmDesarrollo)." paquetes y los scripts dev/types:generate/types:check/test:js\n";
echo '   identidad de la consola: '.($paletaOk ? "primitivos de --shared={$eco} escritos en resources/css/consola.css" : '⚠  NO se pudo escribir la paleta — la consola saldría sin colores')."\n";
echo '   bootstrap/app.php: '.($rutasOk ? 'routes/consola.php cargado (el middleware de Inertia va en el grupo de ese archivo, no en el «web» global)' : '⚠  NO se pudo parchear — revisar a mano')."\n";
echo '   bootstrap/app.php: '.($usoDeRouteOk ? 'la fachada Route está importada' : '⚠  falta «use Illuminate\Support\Facades\Route;» — el archivo NO compila')."\n";
echo '   bootstrap/providers.php: '.($providerOk ? 'TypeScriptTransformerServiceProvider registrado' : '⚠  NO se pudo registrar el provider de tipos — revisar a mano')."\n";
echo '   CLAUDE.md: '.($claudeOk ? 'sección de la consola anexada' : '⚠  NO se pudo parchear CLAUDE.md — revisar a mano')."\n";
echo "   CSP: /consola hereda PoliticaDelSitio (spatie): nonce, sin unsafe-inline ni unsafe-eval\n";

foreach ($avisos as $aviso) {
    echo "   ⚠  {$aviso}\n";
}

// Los parches de código son la parte frágil: TODOS anclan en cadenas literales
// del scaffold. Si el scaffold reescribe bootstrap/app.php o la hoja de la
// consola, las cadenas dejan de coincidir y el proyecto nace sin rutas, sin la
// fachada importada (no compila) o sin identidad visual. Cada sustitución
// tiene su comprobación propia y todas llegan acá: mejor morir que entregar
// eso con código 0.
//
// `AnclajesDelAplicadorDeFrontendTest` vigila desde el otro lado que esas
// cadenas sigan existiendo en el scaffold.
if (! $rutasOk || ! $usoDeRouteOk || ! $paletaOk || ! $providerOk || ! $claudeOk) {
    fwrite(STDERR, "El preset se copió pero los parches de código fallaron: el proyecto NO queda funcionando.\n");
    exit(1);
}
