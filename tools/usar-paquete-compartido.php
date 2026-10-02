<?php

/**
 * Cablea un proyecto recién generado al paquete compartido de su ecosistema:
 * reescribe las referencias, borra las copias locales y declara la dependencia.
 *
 * Lo llama `./scaffold new ... --shared=<eco>`. Se hace en PHP y no en
 * sed/grep porque los namespaces llevan backslashes y el doble escape entre
 * bash y las expresiones regulares es una fuente segura de errores silenciosos.
 *
 * Uso: php tools/usar-paquete-compartido.php <destino> <muni|kraftdo>
 */
/** Escribe en STDERR y termina con error: el fallo se ve, no se traga. */
function morir(string $mensaje): never
{
    fwrite(STDERR, $mensaje."\n");
    exit(1);
}

// `nodoDelArbol()`, `hojasDelArbol()` y el patrón del bloque del árbol viven
// en un único archivo, compartido con `tests/Feature/EstructuraDelReadmeTest`
// y `tests/Generador/ElPaqueteCompartidoPodaElReadmeTest`: ver el comentario
// de cabecera de `arbol-del-readme.php` para el porqué.
require_once __DIR__.'/arbol-del-readme.php';

/**
 * Quita del árbol las hojas cuya ruta está en `$rutas` y las carpetas que
 * quedaron sin hijos por esa poda (sólo ésas: una carpeta que ya nacía sin
 * hijos se respeta), y redibuja los conectores («├── »/«└── » y las barras
 * de los niveles) para que el árbol siga siendo coherente. Las líneas que
 * no son del árbol (hoy no hay ninguna) se conservan tal cual.
 *
 * @param  list<string>  $lineas
 * @param  list<string>  $rutas
 * @return array{lineas: list<string>, quitadas: list<string>}
 */
function podarArbolDelReadme(array $lineas, array $rutas): array
{
    /** @var list<array{profundidad: int|null, nombre: string, resto: string, ruta: string, dir: bool, raw: string}> $nodos */
    $nodos = [];
    $pila = [];
    $quitadas = [];
    /** @var array<string, true> $carpetasHuerfanas */
    $carpetasHuerfanas = [];

    foreach ($lineas as $linea) {
        $nodo = nodoDelArbol($linea);
        if ($nodo === null) {
            $nodos[] = ['profundidad' => null, 'nombre' => '', 'resto' => '', 'ruta' => '', 'dir' => false, 'raw' => $linea];

            continue;
        }

        $pila = array_slice($pila, 0, $nodo['profundidad']);
        $pila[] = $nodo['nombre'];
        $ruta = implode('', $pila);
        $esDir = str_ends_with($nodo['nombre'], '/');

        if (! $esDir && in_array($ruta, $rutas, true)) {
            $quitadas[] = $ruta;
            // Sus ancestros son candidatos a quedar sin hijos.
            for ($nivel = 1; $nivel < count($pila); $nivel++) {
                $carpetasHuerfanas[implode('', array_slice($pila, 0, $nivel))] = true;
            }

            continue;
        }

        $nodos[] = $nodo + ['ruta' => $ruta, 'dir' => $esDir, 'raw' => ''];
    }

    // Una carpeta candidata se va sólo si ya no le queda ningún hijo; quitarla
    // puede dejar sin hijos a la de arriba, así que se repite hasta que una
    // pasada completa no quite nada.
    do {
        $huboCambio = false;
        $conservados = [];
        foreach ($nodos as $indice => $nodo) {
            if ($nodo['dir'] && isset($carpetasHuerfanas[$nodo['ruta']]) && ! tieneHijosEnElArbol($nodos, $indice)) {
                $huboCambio = true;

                continue;
            }
            $conservados[] = $nodo;
        }
        $nodos = $conservados;
    } while ($huboCambio);

    $resultado = [];
    foreach ($nodos as $indice => $nodo) {
        if ($nodo['profundidad'] === null) {
            $resultado[] = $nodo['raw'];

            continue;
        }

        $prefijo = '';
        for ($nivel = 0; $nivel < $nodo['profundidad']; $nivel++) {
            $prefijo .= esElUltimoDeSusHermanos($nodos, ancestroEnElArbol($nodos, $indice, $nivel)) ? '    ' : '│   ';
        }
        $conector = esElUltimoDeSusHermanos($nodos, $indice) ? '└── ' : '├── ';
        $resultado[] = $prefijo.$conector.$nodo['nombre'].$nodo['resto'];
    }

    return ['lineas' => $resultado, 'quitadas' => $quitadas];
}

/**
 * @param  list<array{profundidad: int|null, nombre: string, resto: string, ruta: string, dir: bool, raw: string}>  $nodos
 */
function tieneHijosEnElArbol(array $nodos, int $indice): bool
{
    $profundidad = $nodos[$indice]['profundidad'] ?? 0;
    for ($siguiente = $indice + 1; $siguiente < count($nodos); $siguiente++) {
        $otra = $nodos[$siguiente]['profundidad'];
        if ($otra === null) {
            continue;
        }

        return $otra > $profundidad;
    }

    return false;
}

/**
 * @param  list<array{profundidad: int|null, nombre: string, resto: string, ruta: string, dir: bool, raw: string}>  $nodos
 */
function esElUltimoDeSusHermanos(array $nodos, int $indice): bool
{
    $profundidad = $nodos[$indice]['profundidad'] ?? 0;
    for ($siguiente = $indice + 1; $siguiente < count($nodos); $siguiente++) {
        $otra = $nodos[$siguiente]['profundidad'];
        if ($otra === null) {
            continue;
        }
        if ($otra < $profundidad) {
            return true;
        }
        if ($otra === $profundidad) {
            return false;
        }
    }

    return true;
}

/**
 * Índice del ancestro de `$indice` que vive en el nivel `$nivel`: el nodo
 * anterior más cercano con esa profundidad.
 *
 * @param  list<array{profundidad: int|null, nombre: string, resto: string, ruta: string, dir: bool, raw: string}>  $nodos
 */
function ancestroEnElArbol(array $nodos, int $indice, int $nivel): int
{
    for ($anterior = $indice - 1; $anterior >= 0; $anterior--) {
        if ($nodos[$anterior]['profundidad'] === $nivel) {
            return $anterior;
        }
    }

    morir("El árbol del README tiene un nodo de nivel {$nivel} sin carpeta padre: revisá la indentación.");
}

$dest = $argv[1] ?? '';
$eco = $argv[2] ?? '';

if ($dest === '' || ! is_dir($dest)) {
    fwrite(STDERR, "Destino inválido: {$dest}\n");
    exit(1);
}

/** Namespace de cada clase según el paquete del ecosistema. */
$ecosistemas = [
    'muni' => [
        'paquete' => 'muni-graneros/laravel-muni-shared',
        // El mismo mínimo que composer.json ya exige en la raíz del scaffold:
        // declarar acá una versión más vieja no rompe nada por sí solo
        // —composer se queda con la más restrictiva—, pero miente sobre qué
        // versión pide realmente este cableado.
        //
        // Se quedó en "^1.16" cuando composer.json subió a "^1.18", y así se
        // fue a la rama: es exactamente la deriva que «VersionDelPaqueteCompartidoTest»
        // existe para detectar. Al subir el constraint de la raíz hay que
        // subir este número en el mismo commit.
        'version' => '^1.21',
        // Son paquetes privados servidos por VCS: sin declarar de dónde se bajan,
        // Composer responde «could not be found in any version» y el proyecto recién
        // generado no arranca. El nombre del paquete no dice dónde vive.
        'repositorio' => 'https://github.com/muni-graneros/laravel-muni-shared.git',
        'clases' => [
            'App\\Helpers\\RutHelper' => 'Muni\\Shared\\RutHelper',
            'App\\Rules\\RutValido' => 'Muni\\Shared\\RutValido',
            'App\\Support\\Coordenadas' => 'Muni\\Shared\\Coordenadas',
            'App\\Contracts\\PersonaResolverInterface' => 'Muni\\Shared\\Persona\\PersonaResolverInterface',
            'App\\DTOs\\PersonaDTO' => 'Muni\\Shared\\Persona\\PersonaDTO',
        ],
    ],
    'kraftdo' => [
        'paquete' => 'kraftdo/laravel-kraftdo-shared',
        'version' => '^1.0',
        'repositorio' => 'https://github.com/kraftdo/laravel-kraftdo-shared.git',
        'clases' => [
            'App\\Helpers\\RutHelper' => 'Kraftdo\\Shared\\Rut\\RutHelper',
            'App\\Rules\\RutValido' => 'Kraftdo\\Shared\\Rut\\RutValido',
            'App\\Support\\Coordenadas' => 'Kraftdo\\Shared\\Support\\Coordenadas',
            'App\\Contracts\\PersonaResolverInterface' => 'Kraftdo\\Shared\\Persona\\PersonaResolverInterface',
            'App\\DTOs\\PersonaDTO' => 'Kraftdo\\Shared\\Persona\\PersonaDTO',
            'App\\Casts\\EncryptedSeguro' => 'Kraftdo\\Shared\\Casts\\EncryptedSeguro',
            'App\\Models\\OnboardingTour' => 'Kraftdo\\Shared\\Onboarding\\OnboardingTour',
            'App\\Models\\OnboardingStep' => 'Kraftdo\\Shared\\Onboarding\\OnboardingStep',
            'App\\Models\\OnboardingProgress' => 'Kraftdo\\Shared\\Onboarding\\OnboardingProgress',
            'App\\Http\\Controllers\\OnboardingController' => 'Kraftdo\\Shared\\Onboarding\\OnboardingController',
        ],
    ],
];

if (! isset($ecosistemas[$eco])) {
    fwrite(STDERR, "Ecosistema desconocido: {$eco} (usa: ".implode(', ', array_keys($ecosistemas)).")\n");
    exit(1);
}

$cfg = $ecosistemas[$eco];

/** Archivo local que provee cada clase migrada (se borra tras reescribir). */
$archivoDe = [
    'App\\Helpers\\RutHelper' => 'app/Helpers/RutHelper.php',
    'App\\Rules\\RutValido' => 'app/Rules/RutValido.php',
    'App\\Support\\Coordenadas' => 'app/Support/Coordenadas.php',
    'App\\Contracts\\PersonaResolverInterface' => 'app/Contracts/PersonaResolverInterface.php',
    'App\\DTOs\\PersonaDTO' => 'app/DTOs/PersonaDTO.php',
    'App\\Casts\\EncryptedSeguro' => 'app/Casts/EncryptedSeguro.php',
    'App\\Models\\OnboardingTour' => 'app/Models/OnboardingTour.php',
    'App\\Models\\OnboardingStep' => 'app/Models/OnboardingStep.php',
    'App\\Models\\OnboardingProgress' => 'app/Models/OnboardingProgress.php',
    'App\\Http\\Controllers\\OnboardingController' => 'app/Http/Controllers/OnboardingController.php',
];

$aBorrar = [];
foreach (array_keys($cfg['clases']) as $clase) {
    if (isset($archivoDe[$clase])) {
        $aBorrar[] = $dest.'/'.$archivoDe[$clase];
    }
}

// 1) Reescribir referencias en todo el proyecto (sin tocar lo que se borrará).
$iter = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $f): bool {
            return ! in_array($f->getFilename(), ['vendor', 'node_modules', '.git', 'storage'], true);
        }
    )
);

$tocados = 0;
foreach ($iter as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    if (in_array($file->getPathname(), $aBorrar, true)) {
        continue;
    }

    $antes = (string) file_get_contents($file->getPathname());
    $despues = str_replace(array_keys($cfg['clases']), array_values($cfg['clases']), $antes);

    if ($antes !== $despues) {
        file_put_contents($file->getPathname(), $despues);
        $tocados++;
    }
}

// 2) Borrar SOLO los archivos migrados (nunca la carpeta: puede tener otros).
$borrados = 0;
/** @var list<string> $borradosRelativos rutas relativas al proyecto de lo que sí se borró */
$borradosRelativos = [];
foreach ($aBorrar as $ruta) {
    if (is_file($ruta)) {
        unlink($ruta);
        $borrados++;
        $borradosRelativos[] = substr($ruta, strlen($dest) + 1);
    }
}

// 3) Limpiar carpetas que quedaron vacías.
foreach (['app/Helpers', 'app/Rules', 'app/Support', 'app/Contracts', 'app/DTOs', 'app/Casts'] as $dir) {
    $ruta = $dest.'/'.$dir;
    if (is_dir($ruta) && count((array) scandir($ruta)) === 2) {
        rmdir($ruta);
    }
}

// 3.b) Podar del README el árbol «Estructura del Scaffold».
//
//      `tests/Feature/EstructuraDelReadmeTest` viaja al proyecto generado y
//      comprueba que cada hoja de ese árbol exista en disco. El árbol lista
//      `app/Helpers/RutHelper.php`, que el paso 2 acaba de borrar: sin esto,
//      TODO proyecto `--shared=muni` nacía con la suite en rojo («El README
//      dice que existe «app/Helpers/RutHelper.php» y no está en el
//      repositorio»). Se poda el árbol —hojas borradas y carpetas que quedan
//      sin hijos, redibujando los conectores— en vez de debilitar el test.
$readme = $dest.'/README.md';
if (! is_file($readme)) {
    morir("{$readme} no existe: no puedo quitar del árbol los archivos migrados.");
}

$contenidoReadme = (string) file_get_contents($readme);
// La misma ancla que usa EstructuraDelReadmeTest: si el bloque cambia de
// forma, tiene que fallar acá y no en el primer `make test` del proyecto nuevo.
if (preg_match(PATRON_BLOQUE_ARBOL_DEL_README, $contenidoReadme, $capturaArbol, PREG_OFFSET_CAPTURE) !== 1) {
    morir("{$readme}: no encontré el bloque «Estructura del Scaffold» que EstructuraDelReadmeTest comprueba.");
}

$capturaDelArbol = $capturaArbol[1];
$arbolOriginal = explode("\n", rtrim($capturaDelArbol[0], "\n"));
$poda = podarArbolDelReadme($arbolOriginal, $borradosRelativos);

// Comprobación de fondo: lo que el test del proyecto va a mirar.
$sobrevivientes = array_intersect(hojasDelArbol($poda['lineas']), $borradosRelativos);
if ($sobrevivientes !== []) {
    morir("{$readme}: el árbol sigue listando archivos migrados al paquete: ".implode(', ', $sobrevivientes));
}

$contenidoReadme = substr_replace(
    $contenidoReadme,
    implode("\n", $poda['lineas'])."\n",
    $capturaDelArbol[1],
    strlen($capturaDelArbol[0]),
);
file_put_contents($readme, $contenidoReadme);
$lineasPodadasDelReadme = count($arbolOriginal) - count($poda['lineas']);

// ---------------------------------------------------------------------------
// La landing pública se pinta con la paleta de la entidad
// ---------------------------------------------------------------------------
// `resources/css/sitio.css` trae la paleta neutra del scaffold entre dos
// marcadores; acá se reemplaza por la del ecosistema, leída de la única fuente
// (tools/paletas-de-entidad.php). Y `config/sitio.php` recibe el tipo de
// entidad que decide el JSON-LD de la portada. Sin marcador NO se sigue: una
// landing gris no es un fallo visible y por eso se hace ruidoso.
//
// Primero se validan las TRES condiciones (paleta, marcadores del CSS, ancla
// del config) y recién después se escribe. Escribir el CSS antes de validar
// el config dejaba, ante un config sin ancla, el CSS ya pintado y el config
// intacto: un cableado a medias que ningún código de salida delata.
$paletas = require __DIR__.'/paletas-de-entidad.php';
$tiposDeEntidad = ['muni' => 'municipalidad', 'kraftdo' => 'empresa'];

if (! isset($paletas[$eco]) || count($paletas[$eco]) !== 20) {
    fwrite(STDERR, "tools/paletas-de-entidad.php no tiene la paleta «{$eco}» con sus 20 primitivos: no se puede pintar la landing.\n");
    exit(1);
}

$rutaCss = $dest.'/resources/css/sitio.css';
$css = is_file($rutaCss) ? (string) file_get_contents($rutaCss) : '';
$inicio = strpos($css, '/* PRIMITIVOS-DE-LA-ENTIDAD */');
$fin = strpos($css, '/* FIN-PRIMITIVOS-DE-LA-ENTIDAD */');

if ($inicio === false || $fin === false || $fin < $inicio) {
    fwrite(STDERR, "resources/css/sitio.css no tiene los marcadores PRIMITIVOS-DE-LA-ENTIDAD: no se puede pintar la landing de {$eco}.\n");
    exit(1);
}

$rutaConfig = $dest.'/config/sitio.php';
$config = is_file($rutaConfig) ? (string) file_get_contents($rutaConfig) : '';
if (! str_contains($config, "'tipo' => 'organizacion'")) {
    fwrite(STDERR, "config/sitio.php no tiene el ancla 'tipo' => 'organizacion': no se puede fijar el tipo de entidad.\n");
    exit(1);
}

$bloqueCss = "/* PRIMITIVOS-DE-LA-ENTIDAD */\n:root {\n";
foreach ($paletas[$eco] as $token => $hex) {
    $bloqueCss .= "    {$token}: {$hex};\n";
}
$bloqueCss .= "}\n";

file_put_contents($rutaCss, substr($css, 0, $inicio).$bloqueCss.substr($css, $fin));
echo "   landing: paleta «{$eco}» escrita en resources/css/sitio.css\n";

file_put_contents($rutaConfig, str_replace("'tipo' => 'organizacion'", "'tipo' => '{$tiposDeEntidad[$eco]}'", $config));
echo "   landing: entidad de tipo «{$tiposDeEntidad[$eco]}» en config/sitio.php\n";

// 4) Declarar la dependencia.
$composer = $dest.'/composer.json';
$json = json_decode((string) file_get_contents($composer), true);
$json['require'][$cfg['paquete']] = $cfg['version'];

// Y de dónde se baja. Requerir el paquete sin declarar su repositorio dejaba el
// proyecto sin arrancar: Composer no puede resolver un paquete privado que no está
// en packagist y responde «could not be found in any version», que suena a nombre
// mal escrito y manda a buscar por el lado equivocado.
$clave = basename(str_replace('.git', '', $cfg['repositorio']));
$json['repositories'][$clave] = [
    'type' => 'vcs',
    'url' => $cfg['repositorio'],
];

// 5) Y, si la entidad NO es la Municipalidad, se van los paquetes de la
//    Municipalidad.
//
//    Sin esto, `--shared=kraftdo` agregaba el paquete de KraftDo y DEJABA
//    `muni-graneros/laravel-muni-shared` y `laravel-muni-ui` en composer.json:
//    el proyecto de KraftDo seguía sin instalar fuera de la cuenta de Graneros
//    —son repositorios privados— y, si instalaba, era porque tenía su token.
//    Meter una entidad dentro de otra es justamente lo que no se hace.
if ($eco !== 'muni') {
    $retirados = [];

    foreach (array_keys($json['require'] ?? []) as $paquete) {
        if (str_starts_with((string) $paquete, 'muni-graneros/')) {
            unset($json['require'][$paquete]);
            $retirados[] = $paquete;
        }
    }

    foreach (array_keys($json['repositories'] ?? []) as $repo) {
        $url = $json['repositories'][$repo]['url'] ?? '';
        if (str_contains((string) $url, 'muni-graneros/')) {
            unset($json['repositories'][$repo]);
        }
    }

    if ($retirados !== []) {
        echo '   retirados (otra entidad): '.implode(', ', $retirados)."\n";
        echo "   ⚠  Quedan referencias a Muni\\Shared y Muni\\Ui en app/: el módulo de\n";
        echo "      Privacidad (ARCOP) y el plugin del panel viven en esos paquetes y todavía\n";
        echo "      no están extraídos a uno sin entidad. Este proyecto NO compila hasta\n";
        echo "      reemplazarlos. Ver el hallazgo «El generador contamina entre entidades».\n";
    }
}

file_put_contents(
    $composer,
    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

echo "   referencias reescritas: {$tocados} archivos · copias locales eliminadas: {$borrados}\n";
echo "   README: {$lineasPodadasDelReadme} líneas podadas del árbol «Estructura del Scaffold»\n";
echo "   composer.json requiere {$cfg['paquete']}\n";
