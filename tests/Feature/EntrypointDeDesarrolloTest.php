<?php

declare(strict_types=1);

/**
 * El entrypoint de desarrollo corre en cada `make up`, no solo la primera vez.
 */
it('shield:generate no reescribe las policies en cada arranque', function () {
    // Sin --option=permissions, shield:generate REESCRIBE las policies en disco
    // con el formato crudo de su plantilla y borra los métodos que no son los
    // estándar (p. ej. OnboardingStepPolicy, reducida a propósito). Ya pasó en
    // rrhh: se llevó el export() de tres policies sin que nada avisara.
    $entrypoint = (string) file_get_contents(base_path('docker-entrypoint.sh'));

    expect($entrypoint)->toContain('shield:generate')
        ->and($entrypoint)->toContain('--option=permissions');
});

it('el entrypoint de desarrollo no corre Pint en cada arranque', function () {
    // Pint sobre app/Policies en cada arranque reformatea archivos que el
    // desarrollador puede tener a medio editar en el mismo bind mount, y no
    // aporta nada que el CI (Pint --test) no vuelva a comprobar en el PR.
    $entrypoint = (string) file_get_contents(base_path('docker-entrypoint.sh'));

    expect($entrypoint)->not->toContain('vendor/bin/pint');
});

/**
 * Devuelve las órdenes del bloque `( flock N … ) N>archivo` del entrypoint que
 * contiene la orden pedida, o `null` si la orden corre fuera de todo cerrojo.
 * Se leen solo las órdenes (sin comentarios): el propio comentario del cerrojo
 * de APP_KEY nombra «composer install», y una búsqueda de texto se marcaría
 * a sí misma.
 *
 * @param  list<string>  $lineas
 * @return list<string>|null
 */
function bloqueBajoCerrojoDelEntrypoint(array $lineas, string $orden): ?array
{
    $bloque = null;

    foreach ($lineas as $linea) {
        $sinComentario = trim((string) preg_replace('/^\s*#.*$/', '', $linea));

        if (preg_match('/^flock\s+\d+\b/', $sinComentario) === 1) {
            $bloque = [];

            continue;
        }

        if (preg_match('/^\)\s+\d+>/', $sinComentario) === 1) {
            $bloque = null;

            continue;
        }

        if ($bloque === null) {
            if (str_contains($sinComentario, $orden)) {
                return null;
            }

            continue;
        }

        $bloque[] = $sinComentario;

        if (str_contains($sinComentario, $orden)) {
            return $bloque;
        }
    }

    return null;
}

it('composer install y npm ci corren bajo cerrojo, con vendor/autoload.php como marcador', function () {
    // `app` y `worker` arrancan a la vez sobre el mismo bind mount y corren
    // este script. Sin cerrojo los dos veían vendor/ ausente y lanzaban
    // `composer install` a la vez sobre el mismo directorio: se pisaban los
    // zips temporales de vendor/composer/ («unzip: can't open …/tmp-….zip»,
    // «corrupted zip archive (0 bytes)», «Install of X failed»), composer
    // moría en los dos, y como el directorio a medias ya existía, en cada
    // reinicio `-d /app/vendor` lo daba por instalado, nadie reinstalaba y
    // artisan caía en «Failed opening required vendor/autoload.php»: bucle de
    // reinicio permanente (2026-09-14, /tmp/claude/carrera-vendor-logs.txt).
    // APP_KEY ya iba bajo `flock` por la misma razón; este test exige lo mismo
    // para las dependencias, y que el marcador de «ya instalado» se compruebe
    // DENTRO del cerrojo y sea vendor/autoload.php —lo último que escribe
    // composer—, no `-d /app/vendor`, que da por bueno un directorio a medias.
    $lineas = explode("\n", (string) file_get_contents(base_path('docker-entrypoint.sh')));

    foreach (['composer install', 'npm ci', 'npm run build'] as $orden) {
        expect(bloqueBajoCerrojoDelEntrypoint($lineas, $orden))->not->toBeNull(
            "«{$orden}» corre fuera de un flock en docker-entrypoint.sh: app y worker lo ejecutan a la vez sobre el mismo /app",
        );
    }

    $bloqueComposer = implode("\n", bloqueBajoCerrojoDelEntrypoint($lineas, 'composer install') ?? []);

    expect(str_contains($bloqueComposer, 'vendor/autoload.php'))->toBeTrue(
        'el bloque de composer install no comprueba vendor/autoload.php dentro del cerrojo: el segundo contenedor reinstalaría igual',
    );

    // str_contains y no toContain(): toContain es variádico y no acepta mensaje.
    $ordenes = implode("\n", array_map(
        fn (string $linea): string => trim((string) preg_replace('/^\s*#.*$/', '', $linea)),
        $lineas,
    ));

    expect(str_contains($ordenes, '-d "/app/vendor"'))->toBeFalse(
        'docker-entrypoint.sh sigue usando `-d /app/vendor` como marcador: un vendor/ a medias existe igual',
    );
});
