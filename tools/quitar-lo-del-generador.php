<?php

declare(strict_types=1);

/**
 * Quita del `phpstan.neon` copiado al proyecto nuevo las entradas que sólo
 * tienen sentido en el scaffold: las que apuntan a `presets/`, carpeta que el
 * generador NO copia (ni siquiera con `--frontend=inertia-react`, donde el
 * preset ya se aplicó sobre `app/` y no queda como carpeta separada). Sin
 * esto, `vendor/bin/phpstan analyse` (o `make stan`) muere con «Path
 * .../presets/inertia-react/archivos/app does not exist» en CUALQUIER
 * proyecto recién generado.
 *
 * Las entradas a quitar están delimitadas en `phpstan.neon` con un par de
 * marcadores («# >>> sólo-en-el-scaffold» / «# <<< sólo-en-el-scaffold»),
 * ambas líneas incluidas. Puede haber varios pares (paths, excludePaths,
 * ignoreErrors); cada uno se quita completo.
 *
 * Quitar un bloque puede dejar la clave que lo introducía («excludePaths:
 * analyse:», hoy) sin una sola línea hija: Nette Neon la lee como `null`, y
 * PHPStan la tolera en silencio (comprobado: sigue saliendo «[OK]»), pero
 * depender de esa tolerancia implícita es frágil y no dice lo que pasó. Esas
 * claves huérfanas se quitan enteras: una clave ausente y una clave `null`
 * significan lo mismo para PHPStan, y lo que queda en el archivo es sólo lo
 * que de verdad tiene contenido.
 *
 * `paths:` es la ÚNICA excepción que no puede vaciarse nunca: sin ninguna
 * ruta, PHPStan analiza cero archivos y sale con código 0 sin decir nada. Si
 * el recorte la dejara vacía —los marcadores envolvieron TODAS sus entradas,
 * no sólo la del preset—, el script muere en vez de entregar un proyecto que
 * "pasa" PHPStan sin haber analizado una sola línea de código.
 *
 * Uso: php tools/quitar-lo-del-generador.php <destino>
 */
$dest = $argv[1] ?? '';

/** Escribe en STDERR y termina con error: el fallo se ve, no se traga. */
function morir(string $mensaje): never
{
    fwrite(STDERR, $mensaje."\n");
    exit(1);
}

if ($dest === '' || ! is_dir($dest)) {
    morir("Destino inválido: {$dest}");
}

const MARCADOR_INICIO = '# >>> sólo-en-el-scaffold';
const MARCADOR_FIN = '# <<< sólo-en-el-scaffold';

$ruta = $dest.'/phpstan.neon';
if (! is_file($ruta)) {
    morir("{$dest}/phpstan.neon no existe: no hay nada que quitar.");
}

$lineas = explode("\n", (string) file_get_contents($ruta));

$resultado = [];
$dentroDelBloque = false;
$bloquesQuitados = 0;

foreach ($lineas as $linea) {
    if (! $dentroDelBloque && str_contains($linea, MARCADOR_INICIO)) {
        $dentroDelBloque = true;

        continue;
    }

    if ($dentroDelBloque) {
        if (str_contains($linea, MARCADOR_FIN)) {
            $dentroDelBloque = false;
            $bloquesQuitados++;
        }

        continue;
    }

    $resultado[] = $linea;
}

if ($dentroDelBloque) {
    morir("{$ruta} tiene un marcador «".MARCADOR_INICIO.'» sin su «'.MARCADOR_FIN.'» de cierre.');
}

/**
 * Si desde `$indice` (una línea «clave:» sin valor en la misma línea) no hay
 * ninguna línea siguiente con MÁS indentación que `$indentacion` —ignorando
 * comentarios y líneas en blanco—, esa clave se quedó sin hijos: el bloque
 * que le daba contenido era, entero, sólo-en-el-scaffold.
 *
 * @param  list<string>  $lineas
 */
function claveNeonQuedaVacia(array $lineas, int $indice, int $indentacion): bool
{
    for ($siguiente = $indice + 1; $siguiente < count($lineas); $siguiente++) {
        $sinEspacios = ltrim($lineas[$siguiente]);

        if ($sinEspacios === '' || $sinEspacios[0] === '#') {
            continue;
        }

        return (strlen($lineas[$siguiente]) - strlen($sinEspacios)) <= $indentacion;
    }

    return true;
}

// Quitar una clave huérfana puede dejar huérfana a la clave que la contenía
// (quitar «analyse:» deja a «excludePaths:» sin hijos). Se repite hasta que
// una pasada completa no quite nada: así la orfandad sube en cascada todo lo
// que tenga que subir, sin adivinar cuántos niveles hacen falta.
do {
    $huboCambio = false;
    $sinClavesHuerfanas = [];

    foreach ($resultado as $indice => $linea) {
        if (preg_match('/^(\s*)([A-Za-z][\w-]*):\s*$/', $linea, $coincidencia) === 1
            && claveNeonQuedaVacia($resultado, $indice, strlen($coincidencia[1]))) {
            if ($coincidencia[2] === 'paths') {
                morir("{$ruta}: «paths:» quedó sin ninguna ruta tras quitar los bloques sólo-en-el-scaffold. Los marcadores envolvieron TODAS sus entradas, no sólo la del preset.");
            }

            $huboCambio = true;

            continue;
        }

        $sinClavesHuerfanas[] = $linea;
    }

    $resultado = $sinClavesHuerfanas;
} while ($huboCambio);

$contenidoFinal = implode("\n", $resultado);

// La comprobación de fondo: si queda CUALQUIER mención a presets/, el
// proyecto generado va a fallar con "Path ... does not exist" igual que
// antes de esta herramienta — no basta con que el borrado "haya corrido".
if (str_contains($contenidoFinal, 'presets/')) {
    morir("{$ruta} sigue mencionando presets/ después de quitar los bloques sólo-en-el-scaffold.");
}

if (str_contains($contenidoFinal, MARCADOR_INICIO) || str_contains($contenidoFinal, MARCADOR_FIN)) {
    morir("{$ruta} conserva un marcador sólo-en-el-scaffold residual.");
}

file_put_contents($ruta, $contenidoFinal);

echo "   phpstan.neon: {$bloquesQuitados} bloque(s) sólo-en-el-scaffold quitados\n";
