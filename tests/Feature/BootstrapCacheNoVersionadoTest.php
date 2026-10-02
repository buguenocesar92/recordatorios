<?php

/**
 * `bootstrap/cache/packages.php` y `services.php` estaban versionados: el
 * único repo del ecosistema con esto. Son manifiestos que Laravel regenera
 * solo -`package:discover` los reescribe en cada `composer install`-, así
 * que versionarlos deja dos problemas: un `composer install` en producción
 * puede terminar leyendo un manifiesto de proveedores que quedó de un commit
 * viejo en vez del que corresponde al `composer.lock` actual, y cada
 * `package:discover` local ensucia `git status` con un archivo que nadie
 * edita a mano.
 *
 * El arreglo es el de siempre en Laravel: todo lo que cae en
 * `bootstrap/cache/` se ignora, salvo el propio `.gitignore` que lo declara
 * (mismo patrón que ya usa `feria-graneros`). `git ls-files` no se puede usar
 * como candado acá porque `git` no está instalado dentro del contenedor
 * donde corre esta batería (imagen Alpine de desarrollo, sin el paquete
 * `git`) -y si lo estuviera, ejecutarlo desde un test que corre a diario
 * dentro de ese contenedor fallaría siempre por «git: not found», no por la
 * regla-. Lo que sí se puede comprobar desde PHP, sin invocar ningún binario
 * externo, es la regla que hace el trabajo real: el contenido exacto del
 * `.gitignore` de la carpeta.
 */
it('bootstrap/cache tiene su propio .gitignore que excluye todo menos a sí mismo', function () {
    $ruta = base_path('bootstrap/cache/.gitignore');

    expect(file_exists($ruta))->toBeTrue('Falta bootstrap/cache/.gitignore');

    $reglas = array_values(array_filter(array_map('trim', explode("\n", (string) file_get_contents($ruta)))));

    expect($reglas)->toBe(
        ['*', '!.gitignore'],
        'bootstrap/cache/.gitignore no ignora todo salvo a sí mismo: un manifiesto regenerado podría volver a colarse',
    );
});
