<?php

/**
 * Regla del dueño: las migraciones NUNCA se aplican solas a producción. Se genera
 * el SQL (`make migraciones-sql`), él lo revisa y lo aplica a mano (README, 0.8).
 *
 * El arranque de producción corría `artisan migrate --force` en cada inicio del
 * contenedor: un despliegue cualquiera, o un simple reinicio, podía alterar el
 * esquema real sin que nadie viera el SQL. Estos candados fallan si vuelve.
 */

/**
 * Líneas ejecutables de un script o workflow: sin comentarios, que sí pueden
 * mencionar «migrate» para explicar por qué ya no se corre.
 */
function lineasEjecutables(string $ruta): string
{
    $contenido = file_get_contents(base_path($ruta));
    expect($contenido)->not->toBeFalse("No se pudo leer {$ruta}");

    return collect(preg_split('/\R/', (string) $contenido))
        ->reject(fn (string $linea): bool => str_starts_with(ltrim($linea), '#'))
        ->implode("\n");
}

/** Bloque de un servicio dentro de docker-compose.prod.yml (de «  nombre:» al siguiente servicio). */
function bloqueDeServicioProd(string $servicio): string
{
    $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));

    expect(preg_match('/^  '.preg_quote($servicio, '/').':\R(.*?)(?=^  \S|\z)/ms', $compose, $m))->toBe(1);

    return $m[1];
}

it('el entrypoint de producción nunca corre migrate', function () {
    expect(lineasEjecutables('docker/docker-entrypoint.prod.sh'))
        ->not->toMatch('/\bmigrate\b/');
});

it('el compose de producción arranca la app con el entrypoint de producción', function () {
    expect(file_exists(base_path('docker/docker-entrypoint.prod.sh')))->toBeTrue();

    expect(bloqueDeServicioProd('app'))
        ->toMatch('#^\s*entrypoint:\s*\["/app/docker/docker-entrypoint\.prod\.sh"\]#m');
});

it('el worker de producción solo pasa por el entrypoint de desarrollo con un comando, antes de migrar', function () {
    // El worker de producción no redefine el entrypoint: hereda el de la imagen
    // (docker-entrypoint.sh, de desarrollo, que SÍ migra al arrancar la app). Es
    // inofensivo únicamente porque el worker trae `command:` y ese script sale por
    // la rama de «comando personalizado» antes de llegar a `migrate`.
    expect(bloqueDeServicioProd('worker'))->not->toContain('entrypoint:');

    $base = (string) file_get_contents(base_path('docker-compose.yml'));
    expect(preg_match('/^  worker:\R(.*?)(?=^  \S|\z)/ms', $base, $m))->toBe(1);
    expect($m[1])->toMatch('/^\s*command:/m');

    $dev = lineasEjecutables('docker-entrypoint.sh');
    $posComando = strpos($dev, 'if [ $# -gt 0 ]');
    $posMigrate = strpos($dev, 'artisan migrate');

    expect($posComando)->not->toBeFalse()
        ->and($posMigrate)->not->toBeFalse()
        ->and($posComando)->toBeLessThan($posMigrate);
});

it('el workflow de despliegue no migra la base de producción', function () {
    expect(lineasEjecutables('.github/workflows/desplegar.yml'))
        ->not->toMatch('/artisan\s+migrate/');
});

it('existe make migraciones-sql para generar el SQL a revisar', function () {
    $makefile = (string) file_get_contents(base_path('Makefile'));

    expect($makefile)->toMatch('/^migraciones-sql:/m')
        ->and(file_exists(base_path('tools/migraciones-sql.sh')))->toBeTrue()
        ->and(glob(base_path('docs/migraciones/*.sql')))->not->toBeEmpty();
});
