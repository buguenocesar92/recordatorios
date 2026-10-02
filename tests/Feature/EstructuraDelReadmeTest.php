<?php

/**
 * El README promete cosas que no existen: probaba una cola offline, un service
 * worker que nunca se registra en ninguna vista y archivos de la sección
 * «Estructura del Scaffold» que nunca se crearon (SincronizarOfflineAction.php,
 * SincronizaOffline.php). Quien adopta el scaffold espera esa estructura y no
 * la encuentra.
 *
 * Este candado recorre el árbol ASCII del README y confirma que cada archivo
 * que declara existe de verdad, para que una futura entrada inventada —o un
 * archivo real que se borre sin actualizar el README— no pase desapercibida.
 *
 * El parser (`hojasDelArbolDesdeReadme()`) vive en `tools/arbol-del-readme.php`,
 * compartido con `tools/usar-paquete-compartido.php` y con
 * `tests/Generador/ElPaqueteCompartidoPodaElReadmeTest`: este archivo viaja al
 * proyecto generado y `tools/` viaja con él (no está en la lista de exclusión
 * de `scaffold`), así que el `require` sigue resolviendo en el destino. Usa
 * `__DIR__` y no `base_path()` porque este `require` corre a nivel de archivo,
 * antes de que Pest levante el contenedor de la aplicación.
 */
require_once __DIR__.'/../../tools/arbol-del-readme.php';

it('cada archivo que la estructura del README dice tener existe de verdad', function () {
    $readme = (string) file_get_contents(base_path('README.md'));

    $rutas = hojasDelArbolDesdeReadme($readme);

    expect($rutas)->not->toBeEmpty('No extraje ninguna ruta del árbol: revisa el formato del bloque.');

    foreach ($rutas as $ruta) {
        expect(file_exists(base_path($ruta)))->toBeTrue("El README dice que existe «{$ruta}» y no está en el repositorio.");
    }
});
