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

/**
 * Ejecuta el tramo de decisión del entrypoint de desarrollo con un APP_ENV dado
 * y devuelve si habría migrado y sembrado. Se sustituye `php artisan` por un eco
 * para no tocar la base.
 */
function decisionDelEntrypointDev(?string $appEnv): string
{
    $script = (string) file_get_contents(base_path('docker-entrypoint.sh'));

    preg_match('/^APP_ENV_EFECTIVO=.*?^esac$/ms', $script, $decision);
    preg_match('/^# Producción nunca migra sola.*?(?=^# --option=permissions)/ms', $script, $migrar);
    preg_match('/^# Los seeders cargan.*?^fi$/ms', $script, $sembrar);

    expect($decision)->not->toBeEmpty()
        ->and($migrar)->not->toBeEmpty()
        ->and($sembrar)->not->toBeEmpty();

    $cuerpo = str_replace('php artisan', 'echo EJECUTA', $decision[0]."\n".$migrar[0]."\n".$sembrar[0]);
    // Sin /app/.env real: se prueba solo la variable del proceso.
    $cuerpo = str_replace('/app/.env', '/nonexistent/.env', $cuerpo);

    $prefijo = $appEnv === null ? 'unset APP_ENV;' : 'APP_ENV='.escapeshellarg($appEnv).'; export APP_ENV;';

    return (string) shell_exec('sh -c '.escapeshellarg($prefijo."\n".$cuerpo).' 2>&1');
}

it('el entrypoint de desarrollo no migra ni siembra con APP_ENV=production', function () {
    expect(decisionDelEntrypointDev('production'))
        ->not->toContain('EJECUTA migrate')
        ->not->toContain('EJECUTA db:seed');
});

it('el entrypoint de desarrollo no migra ni siembra con APP_ENV vacío, ausente o desconocido', function (?string $valor) {
    expect(decisionDelEntrypointDev($valor))
        ->not->toContain('EJECUTA migrate')
        ->not->toContain('EJECUTA db:seed');
})->with([[''], [null], ['prod'], ['staging'], ['Production']]);

it('el entrypoint de desarrollo sí migra y siembra con APP_ENV=local', function () {
    expect(decisionDelEntrypointDev('local'))
        ->toContain('EJECUTA migrate --force')
        ->toContain('EJECUTA db:seed --force');
});

it('todo servicio del compose de producción usa el entrypoint de producción o lleva command', function () {
    $prod = (string) file_get_contents(base_path('docker-compose.prod.yml'));
    $base = (string) file_get_contents(base_path('docker-compose.yml'));

    preg_match_all('/^  (\w+):\R/m', explode('volumes:', $prod)[0], $nombres);

    foreach ($nombres[1] as $servicio) {
        $bloqueProd = bloqueDeServicioProd($servicio);
        // Solo importan los servicios que corren la imagen de la aplicación.
        if (! str_contains($bloqueProd, 'APP_IMAGE')) {
            continue;
        }

        $conEntrypointProd = (bool) preg_match('#^\s*entrypoint:\s*\["/app/docker/docker-entrypoint\.prod\.sh"\]#m', $bloqueProd);
        preg_match('/^  '.preg_quote($servicio, '/').':\R(.*?)(?=^  \S|\z)/ms', $base, $m);
        $conCommand = preg_match('/^\s*command:/m', $m[1] ?? '') === 1 || str_contains($bloqueProd, 'command:');

        expect($conEntrypointProd || $conCommand)
            ->toBeTrue("El servicio {$servicio} de producción podría llegar a migrate/seed");
        // Y en producción el entorno está fijado, para que el entrypoint de la imagen no dude.
        expect($bloqueProd)->toContain('APP_ENV=production');
    }
});
