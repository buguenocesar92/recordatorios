<?php

/**
 * El CI publicaba una imagen SIN vendor/, y el VPS la completaba en el
 * arranque con `composer install` como root, con internet y con el token de
 * GitHub en el entorno del proceso (COMPOSER_AUTH). El arranque de producción
 * dependía de github.com y de packagist, `policy.advisories.block false`
 * dejaba pasar avisos de seguridad, y un rollback a la imagen anterior volvía
 * a instalar sin garantizar el mismo `vendor/`.
 *
 * vendor/ ahora se compila en su propia etapa del Dockerfile, con el token
 * SOLO en un mount de secreto de BuildKit (nunca en una capa ni en el
 * historial de la imagen), y el entrypoint de producción falla si no lo
 * encuentra en vez de reinstalar en silencio.
 */
it('vendor/ se compila en su propia etapa del Dockerfile, no en el arranque', function () {
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('AS vendor')
        ->and($dockerfile)->toContain('--mount=type=secret,id=composer_auth')
        ->and($dockerfile)->toContain('composer install --no-dev')
        ->and($dockerfile)->toContain('COPY --from=vendor /app/vendor ./vendor');
});

it('el entrypoint de producción no vuelve a instalar composer al arrancar', function () {
    // Sin comentarios: el propio entrypoint explica en un comentario por qué
    // YA NO instala («…con "composer install" como root con internet…»), y
    // eso confundiría una búsqueda de texto con el comando real.
    $lineas = array_filter(
        explode("\n", (string) file_get_contents(base_path('docker/docker-entrypoint.prod.sh'))),
        fn (string $linea): bool => ! str_starts_with(trim($linea), '#'),
    );

    expect(implode("\n", $lineas))->not->toContain('composer install');
});

it('el compose de producción ya no pasa el token de composer por environment', function () {
    // El token viajaba en COMPOSER_AUTH en el entorno del proceso —visible con
    // `docker inspect` o `/proc/<pid>/environ`— porque el entrypoint lo
    // necesitaba para instalar en el arranque. Ya no lo necesita: vendor/ viene
    // horneado en la imagen.
    $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));

    expect($compose)->not->toContain('COMPOSER_AUTH');
});

it('el workflow de despliegue pasa el token de composer como secreto de build, no como ARG/ENV', function () {
    $workflow = (string) file_get_contents(base_path('.github/workflows/desplegar.yml'));

    expect($workflow)->toContain('secrets:')
        ->and($workflow)->toContain('composer_auth=')
        ->and($workflow)->toContain('secrets.COMPOSER_GH_TOKEN');
});
