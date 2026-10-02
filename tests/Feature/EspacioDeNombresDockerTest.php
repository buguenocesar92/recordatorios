<?php

/**
 * Colisión real del 06-09: scaffold-laravel-filament-pwa y scaffold-inertia son
 * dos checkouts de este mismo scaffold que conviven en el mismo equipo, y
 * compartían `container_name` fijos (`scaffold-app-*`) y el mismo volumen de
 * MariaDB sin prefijo de proyecto (`mariadb_scaffold_data`).
 *
 * Consecuencia real: no se podían levantar los dos a la vez -el segundo
 * chocaba por nombre de contenedor ya en uso- y, levantados por separado, el
 * volumen era el MISMO directorio en disco para los dos scaffolds: levantar
 * uno corría sus migraciones sobre los datos del otro, sin avisar.
 *
 * Este candado exige que `container_name` y el volumen de MariaDB de ESTE
 * repo lleven su propio prefijo, como ya hacen los sistemas municipales que
 * conviven a la vez en el mismo equipo (licencias-graneros, seguridad-graneros,
 * control-acceso-graneros: `<repo>-app-<servicio>` / `mariadb_<repo>_data`).
 * Si alguien vuelve a copiar `docker-compose.yml` de un scaffold al otro sin
 * cambiar el prefijo, este test lo detecta.
 */
it('container_name en docker-compose.yml lleva el prefijo de ESTE repo', function () {
    $compose = (string) file_get_contents(base_path('docker-compose.yml'));

    preg_match_all('/container_name:\s*(\S+)/', $compose, $nombres);

    expect($nombres[1])->not->toBeEmpty('docker-compose.yml ya no declara ningún container_name');

    foreach ($nombres[1] as $nombre) {
        expect($nombre)->toStartWith(
            'recordatorios-app-',
            "«{$nombre}» no lleva el prefijo de este repo: volvería a chocar con el contenedor de scaffold-inertia"
        );
    }
});

it('el volumen de MariaDB lleva el prefijo de ESTE repo, no el genérico que se compartía', function () {
    $compose = (string) file_get_contents(base_path('docker-compose.yml'));

    // toContain() es variádico y no acepta un mensaje como segundo argumento
    // (ver NingunCandadoUsaToContainConMensajeTest, que fue el que encontró
    // este defecto acá: sin str_contains()+toBeFalse(), el `not` pasaba
    // siempre sin importar si el volumen genérico estaba presente).
    expect(str_contains($compose, 'mariadb_scaffold_data'))->toBeFalse(
        'volumen genérico sin prefijo: es el mismo directorio en disco que usaba scaffold-inertia, y uno le pisa la base al otro'
    );
    expect($compose)->toContain('mariadb_recordatorios_data');
});

it('el volumen de storage de producción lleva el prefijo de ESTE repo', function () {
    $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));

    expect(str_contains($compose, 'scaffold_storage'))->toBeFalse(
        'volumen de storage genérico compartido con scaffold-inertia'
    );
    expect($compose)->toContain('recordatorios_storage');
});
