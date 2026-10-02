<?php

/**
 * El despliegue automático.
 *
 * Va por un runner que vive DENTRO del servidor: no hacen falta llaves SSH
 * guardadas en GitHub ni abrir el servidor a internet, porque es el servidor el
 * que sale a preguntar si hay trabajo. Es la diferencia entre una credencial
 * permanente en un tercero y ninguna.
 */
it('el workflow de despliegue existe y es válido', function () {
    $ruta = base_path('.github/workflows/desplegar.yml');

    expect(file_exists($ruta))->toBeTrue('Falta el workflow de despliegue');

    $yaml = yaml_parse_file($ruta);

    expect($yaml)->toBeArray()
        ->and($yaml)->toHaveKey('jobs');
})->skip(! function_exists('yaml_parse_file'), 'La extensión yaml no está instalada.');

it('no lleva ningún secreto escrito dentro', function () {
    // Un workflow es un archivo versionado y público dentro del repositorio:
    // cualquier valor escrito acá viaja con él para siempre.
    $contenido = (string) file_get_contents(base_path('.github/workflows/desplegar.yml'));

    foreach (['ghp_', 'gho_', 'sk-', 'AKIA', '-----BEGIN'] as $marca) {
        // Mismo gotcha que en el resto del repo: toContain() es variádico y no
        // acepta un mensaje como segundo argumento (ver
        // NingunCandadoUsaToContainConMensajeTest, que fue el que encontró
        // este archivo heredado de develop).
        expect(str_contains($contenido, $marca))->toBeFalse("El workflow contiene «{$marca}»");
    }

    // Las contraseñas y los tokens se leen de secrets/vars, nunca literales.
    expect($contenido)->toContain('secrets.GITHUB_TOKEN');
});

it('el despliegue no se dispara hasta que el servidor esté preparado', function () {
    // Sin runner, el job se queda EN COLA para siempre y bloquea todos los runs
    // siguientes del workflow —«timeout-minutes» no corre hasta que el job
    // empieza—. Con la condición, la construcción de la imagen sigue funcionando
    // desde el primer día y el despliegue se enciende definiendo la variable.
    $contenido = (string) file_get_contents(base_path('.github/workflows/desplegar.yml'));

    expect($contenido)->toContain('RUTA_DESPLIEGUE');
});

it('no queda ningún nombre del sistema del que se copió', function () {
    // El scaffold genera sistemas que no son municipales: un nombre propio
    // heredado acá se despliega en el servidor equivocado.
    $contenido = (string) file_get_contents(base_path('.github/workflows/desplegar.yml'));

    foreach (['graneros', 'paginaweb', 'municipal'] as $resto) {
        expect(str_contains(strtolower($contenido), $resto))->toBeFalse("El workflow menciona «{$resto}»");
    }
});
