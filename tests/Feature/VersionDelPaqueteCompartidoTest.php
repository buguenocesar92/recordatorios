<?php

/**
 * `./scaffold new ... --shared=muni` declara qué versión de
 * muni-graneros/laravel-muni-shared pide el proyecto nuevo — pero
 * composer.json, en la raíz de este mismo scaffold, YA exige una versión de
 * ese paquete (para el resto de las clases que no pasan por
 * usar-paquete-compartido.php). Si el mínimo que declara el cableado es más
 * viejo que el que composer.json ya exige, no rompe la instalación —composer
 * se queda con el más restrictivo—, pero miente sobre qué versión pide de
 * verdad este flujo.
 */
it('--shared=muni pide la misma versión mínima que composer.json ya exige', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $version = $composer['require']['muni-graneros/laravel-muni-shared'] ?? null;

    expect($version)->not->toBeNull('composer.json ya no exige muni-graneros/laravel-muni-shared: revisa esta prueba.');

    $herramienta = (string) file_get_contents(base_path('tools/usar-paquete-compartido.php'));

    expect(str_contains($herramienta, "'version' => '{$version}',"))->toBeTrue(
        "usar-paquete-compartido.php declara una versión de muni-graneros/laravel-muni-shared distinta de la {$version} que composer.json ya exige.",
    );
});
