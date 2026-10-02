<?php

/**
 * Da al proyecto recién copiado SUS nombres de Docker: contenedores, volúmenes
 * y red. Los puertos no son de acá: los cambia `scaffold` después.
 *
 * Lo llama `./scaffold new`. Antes era un `sed` sobre literales
 * (`scaffold-app-`, `mariadb_scaffold_data`, `scaffold_storage`); cuando el
 * compose pasó a nombrarse con el repo entero (`<repo>-app-*`) los literales
 * dejaron de calzar, el `sed` no dio error y cada proyecto generado nacía con
 * los `container_name` y los volúmenes del scaffold: chocaba con su checkout o
 * montaba el mismo volumen de la base.
 *
 * Por eso el prefijo ya no se escribe acá: se DEDUCE del propio compose, del
 * `container_name` de sus servicios (`<prefijo>-app-<servicio>`). Si el compose
 * deja de seguir ese esquema, el script muere antes de tocar nada en vez de
 * seguir de largo. Va en PHP y no en bash porque la suite corre dentro del
 * contenedor, que no trae bash, y así el test ejecuta el código real.
 *
 * Uso: php tools/parametrizar-identidad.php <destino> <slug>
 */
$dest = $argv[1] ?? '';
$slug = $argv[2] ?? '';

/** Escribe en STDERR y termina con error: el fallo se ve, no se traga. */
function morir(string $mensaje): never
{
    fwrite(STDERR, $mensaje."\n");
    exit(1);
}

if ($dest === '' || ! is_dir($dest)) {
    morir("Destino inválido: {$dest}");
}

if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
    morir("Slug inválido: «{$slug}» (solo minúsculas, dígitos y guiones).");
}

$composeDev = $dest.'/docker-compose.yml';
if (! is_file($composeDev)) {
    morir("{$dest} no tiene docker-compose.yml: no es un proyecto copiado del scaffold.");
}

$contenidoDev = (string) file_get_contents($composeDev);

// 1) Prefijo de identidad: el mismo en todos los container_name.
preg_match_all('/^\s*container_name:\s*["\']?(\S+?)["\']?\s*$/m', $contenidoDev, $contenedores);

if ($contenedores[1] === []) {
    morir('docker-compose.yml no declara ningún container_name: no hay identidad que renombrar.');
}

$prefijos = [];
foreach ($contenedores[1] as $contenedor) {
    if (preg_match('/^([a-z0-9][a-z0-9._-]*?)-app-[a-z0-9._-]+$/', $contenedor, $partes) !== 1) {
        morir("container_name «{$contenedor}» no sigue el esquema <prefijo>-app-<servicio>: no sé qué renombrar.");
    }
    $prefijos[$partes[1]] = true;
}

if (count($prefijos) !== 1) {
    morir('Los container_name de docker-compose.yml no comparten un solo prefijo: '.implode(', ', array_keys($prefijos)));
}

$prefijo = (string) array_key_first($prefijos);

// 2) Red: la clave de `networks:` de primer nivel. Compose la antepone con el
//    nombre del proyecto, así que no choca; se renombra para que el compose del
//    proyecto no hable del scaffold. Solo en los compose: el nombre es genérico
//    (`scaffold_network`) y reemplazarlo a ciegas en todo el árbol es arriesgado.
$redes = [];
if (preg_match('/^networks:[ \t]*\R((?:(?:[ \t]+.*)?\R?)*)/m', $contenidoDev, $bloque) === 1) {
    preg_match_all('/^[ \t]{2}([A-Za-z0-9._-]+):/m', $bloque[1], $claves);
    $redes = $claves[1];
}

/**
 * Reemplazos en todo archivo de texto del proyecto. Específicos del prefijo,
 * así que no tocan nada que no sea un nombre de Docker (test-all.sh busca el
 * contenedor por nombre, los tests de espacio de nombres exigen el prefijo).
 */
$reemplazosGlobales = [
    "mariadb_{$prefijo}_data" => "mariadb_{$slug}_data",
    "{$prefijo}_storage" => "{$slug}_storage",
    "{$prefijo}-app-" => "{$slug}-app-",
];

$reemplazosDeRed = [];
if (count($redes) === 1 && $redes[0] !== "{$slug}_network") {
    $reemplazosDeRed['/(?<![A-Za-z0-9._-])'.preg_quote($redes[0], '/').'(?![A-Za-z0-9._-])/'] = "{$slug}_network";
}

$iterador = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $archivo): bool => ! in_array($archivo->getFilename(), ['vendor', 'node_modules', '.git', 'storage'], true),
    ),
);

$tocados = 0;
foreach ($iterador as $archivo) {
    /** @var SplFileInfo $archivo */
    if (! $archivo->isFile()) {
        continue;
    }

    $ruta = $archivo->getPathname();
    $antes = (string) file_get_contents($ruta);

    // Misma heurística que `grep -I`: un NUL en el contenido es binario.
    if (str_contains($antes, "\0")) {
        continue;
    }

    $despues = strtr($antes, $reemplazosGlobales);

    if ($reemplazosDeRed !== [] && str_starts_with($archivo->getFilename(), 'docker-compose')) {
        $despues = (string) preg_replace(array_keys($reemplazosDeRed), array_values($reemplazosDeRed), $despues);
    }

    if ($despues !== $antes) {
        file_put_contents($ruta, $despues);
        $tocados++;
    }
}

// 3) Comprobación final: si en algún compose queda un nombre del origen, el
//    proyecto chocaría con él. «Parametrizando proyecto…» no puede mentir.
if ($prefijo !== $slug) {
    foreach (glob($dest.'/docker-compose*.yml') ?: [] as $compose) {
        $contenido = (string) file_get_contents($compose);

        foreach (array_keys($reemplazosGlobales) as $nombreDelOrigen) {
            if (str_contains($contenido, $nombreDelOrigen)) {
                morir(basename($compose)." conserva «{$nombreDelOrigen}» tras parametrizar: el proyecto chocaría con el origen.");
            }
        }
    }
}

echo "   identidad: {$prefijo} → {$slug} ({$tocados} archivo(s))\n";
