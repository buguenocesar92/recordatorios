<?php

/**
 * Cambia los puertos de host que el compose de DESARROLLO expone (app y
 * base), derivándolos del propio archivo en vez de asumir los del scaffold
 * (8001/3308).
 *
 * Lo llama `./scaffold new`. Antes eran sed sobre "8001:8000"/"3308:3306" y
 * "localhost:8211" literales: calzaban solo mientras ESTE checkout usara esos
 * puertos. Medido el 2026-09-13: la variante que expone la consola Inertia en
 * otro rango (8091/3319, `docker-compose.yml`) generaba proyectos con LOS
 * PUERTOS DEL SCAFFOLD, no los pedidos —dos proyectos nacidos de esa variante
 * chocaban entre sí sin que nada avisara—.
 *
 * El puerto vigente se deduce de las propias líneas «ports:» del compose (las
 * que publican el contenedor interno 8000 —app— y 3306 —base—), sea cual sea
 * el host al que se publican (`${BIND_HOST:-127.0.0.1}` o una IP literal): el
 * formato de bind no se toca, solo el número de puerto de host. Si el compose
 * no trae ninguna de las dos líneas, o si tras reemplazar sigue quedando el
 * puerto viejo, el script muere antes de dejar el proyecto a medias.
 *
 * Uso: php tools/parametrizar-puertos.php <destino> <app_port> <db_port>
 */
$dest = $argv[1] ?? '';
$appPort = $argv[2] ?? '';
$dbPort = $argv[3] ?? '';

/** Escribe en STDERR y termina con error: el fallo se ve, no se traga. */
function morir(string $mensaje): never
{
    fwrite(STDERR, $mensaje."\n");
    exit(1);
}

if ($dest === '' || ! is_dir($dest)) {
    morir("Destino inválido: {$dest}");
}

foreach (['app_port' => $appPort, 'db_port' => $dbPort] as $nombre => $valor) {
    if (preg_match('/^\d+$/', (string) $valor) !== 1) {
        morir("{$nombre} inválido: «{$valor}» (debe ser numérico).");
    }
}

$compose = $dest.'/docker-compose.yml';
if (! is_file($compose)) {
    morir("{$dest} no tiene docker-compose.yml: no es un proyecto copiado del scaffold.");
}

$contenido = (string) file_get_contents($compose);

/**
 * Deduce el puerto de host vigente buscando la línea que publica el puerto
 * INTERNO dado («:8000"» para la app, «:3306"» para la base): sea cual sea el
 * host que la precede, el número justo antes de esos dos puntos es el puerto
 * de host que hoy usa el compose.
 */
function puertoVigente(string $contenido, string $interno, string $etiqueta): string
{
    if (preg_match('/(\d+):'.preg_quote($interno, '/').'"/', $contenido, $coincidencia) !== 1) {
        morir("docker-compose.yml no publica ningún puerto de host hacia el {$interno} ({$etiqueta}): no sé qué reemplazar.");
    }

    return $coincidencia[1];
}

$appPortVigente = puertoVigente($contenido, '8000', 'app');
$dbPortVigente = puertoVigente($contenido, '3306', 'db');

$actualizado = str_replace(
    ["{$appPortVigente}:8000\"", "localhost:{$appPortVigente}", "{$dbPortVigente}:3306\""],
    ["{$appPort}:8000\"", "localhost:{$appPort}", "{$dbPort}:3306\""],
    $contenido,
);

file_put_contents($compose, $actualizado);

// Comprobación final: si el puerto viejo sobrevive donde antes publicaba el
// contenedor interno, el proyecto seguiría chocando con el scaffold del que
// nació. «Parametrizando proyecto…» no puede mentir.
if ($appPortVigente !== $appPort && str_contains($actualizado, "{$appPortVigente}:8000\"")) {
    morir("docker-compose.yml conserva el puerto de app del scaffold ({$appPortVigente}) tras parametrizar.");
}

if ($dbPortVigente !== $dbPort && str_contains($actualizado, "{$dbPortVigente}:3306\"")) {
    morir("docker-compose.yml conserva el puerto de base del scaffold ({$dbPortVigente}) tras parametrizar.");
}

echo "   puertos: app {$appPortVigente}→{$appPort}, db {$dbPortVigente}→{$dbPort}\n";
