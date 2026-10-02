<?php

/**
 * El primer despliegue de dispositivos-graneros (run 36892413754) murió al
 * construir la imagen, en `composer dump-autoload`: su hook `post-autoload-dump`
 * (`package:discover`) arranca la aplicación, y la config por defecto de
 * Laravel resuelve `view.compiled` con `realpath(storage/framework/views)`, que
 * devuelve false si la carpeta no está → «Please provide a valid cache path».
 *
 * La carpeta existía en el disco de quien desarrolla, porque la app ya había
 * corrido; en el checkout limpio del CI sólo existe si git la versiona. El
 * generador la excluía entera y la recreaba vacía, así que git nunca la vio.
 *
 * Dos cerraduras, porque cada una cubre un agujero distinto:
 *  - el Dockerfile crea las carpetas él mismo antes de `dump-autoload`, y de
 *    nuevo en la etapa final: la imagen no depende de lo que traiga el checkout
 *    ni de lo que deje pasar el .dockerignore (que excluye su contenido);
 *  - cada carpeta viaja en git con su `.gitkeep`, que es lo que el .gitignore
 *    versiona (`!/storage/framework/views/.gitkeep`), para que el checkout del
 *    CI, el de un worktree y el de quien clona tengan el mismo árbol.
 */
const CARPETAS_DE_CACHE_DE_LARAVEL = [
    'storage/framework/views',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/logs',
];

/** @return list<string> las etapas del Dockerfile, cada una desde su FROM */
function etapasDelDockerfile(): array
{
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
    $etapas = preg_split('/^(?=FROM )/m', $dockerfile) ?: [];

    return array_values(array_filter($etapas, fn (string $etapa): bool => str_starts_with($etapa, 'FROM ')));
}

/** La posición del primer `mkdir` que nombra la carpeta de vistas compiladas, o null. */
function posicionDelMkdirDeCache(string $etapa): ?int
{
    $sinComentarios = implode("\n", array_filter(
        explode("\n", $etapa),
        fn (string $linea): bool => ! str_starts_with(trim($linea), '#'),
    ));
    $posicion = preg_match('/mkdir -p[^\n]*storage\/framework\/views/', $sinComentarios, $coincidencia, PREG_OFFSET_CAPTURE);

    return $posicion === 1 ? $coincidencia[0][1] : null;
}

it('la etapa vendor crea las carpetas de caché de Laravel antes de dump-autoload', function () {
    $vendor = array_values(array_filter(etapasDelDockerfile(), fn (string $etapa): bool => str_contains($etapa, ' AS vendor')));
    expect($vendor)->toHaveCount(1, 'el Dockerfile tiene que tener una etapa «AS vendor»');

    $mkdir = posicionDelMkdirDeCache($vendor[0]);
    $dump = strpos($vendor[0], 'composer dump-autoload');

    expect($mkdir)->not->toBeNull('la etapa vendor no crea storage/framework/views: package:discover muere con «Please provide a valid cache path» en un checkout limpio')
        ->and($dump)->not->toBeFalse()
        ->and($mkdir)->toBeLessThan((int) $dump, 'el mkdir tiene que ir ANTES de composer dump-autoload, que es quien arranca la aplicación');

    foreach (CARPETAS_DE_CACHE_DE_LARAVEL as $carpeta) {
        expect($vendor[0])->toContain($carpeta);
    }
});

it('la etapa final crea las carpetas de caché, sin depender del entrypoint', function () {
    // `php artisan` arranca la aplicación igual que package:discover: una
    // imagen cuyo storage/framework/views sólo lo crea el entrypoint falla con
    // cualquier `docker run --entrypoint` (depuración, un job puntual).
    $etapas = etapasDelDockerfile();
    $final = end($etapas);

    expect($final)->toBeString()
        ->and(posicionDelMkdirDeCache((string) $final))->not->toBeNull('la etapa final no crea storage/framework/views');
});

it('cada carpeta de caché viaja en git con su .gitkeep', function (string $carpeta) {
    // En un checkout limpio (CI, un clon nuevo) la carpeta sólo existe si git
    // la versiona, y git sólo versiona archivos: el .gitkeep es la carpeta.
    expect(is_file(base_path($carpeta.'/.gitkeep')))->toBeTrue("falta {$carpeta}/.gitkeep: la carpeta no existe en un checkout limpio");
})->with(CARPETAS_DE_CACHE_DE_LARAVEL);
