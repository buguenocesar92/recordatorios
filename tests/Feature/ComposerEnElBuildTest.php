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

it('fija install-php-extensions en la etapa vendor antes de ejecutarlo', function () {
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
    $lineas = preg_split('/\R/', $dockerfile) ?: [];

    $comentario = '# composer:2 ya no incluye install-php-extensions; se fija desde la imagen oficial.';
    $copy = 'COPY --from=mlocati/php-extension-installer:2.13.1@sha256:35821db0661308f64aa18483bb3af56bedc3b40090ff441ed961327d1ccbceb8 /usr/bin/install-php-extensions /usr/local/bin/';
    $run = 'RUN install-php-extensions pdo_mysql pcntl redis zip gd intl';

    $indiceVendor = array_search('FROM composer:2 AS vendor', $lineas, true);
    $indiceRuntime = array_search('FROM dunglas/frankenphp:1.13.2-php8.5-alpine', $lineas, true);
    $indiceComentario = array_search($comentario, $lineas, true);
    $indiceCopy = array_search($copy, $lineas, true);
    $indiceRun = array_search($run, $lineas, true);

    expect($indiceVendor)->not->toBeFalse()
        ->and($indiceRuntime)->not->toBeFalse()
        ->and($indiceComentario)->not->toBeFalse()
        ->and($indiceCopy)->not->toBeFalse()
        ->and($indiceRun)->not->toBeFalse()
        ->and(substr_count($dockerfile, $copy))->toBe(1)
        ->and($indiceComentario)->toBe($indiceCopy - 1)
        ->and($indiceCopy)->toBe($indiceRun - 1)
        ->and($indiceCopy)->toBeGreaterThan($indiceVendor)
        ->and($indiceCopy)->toBeLessThan($indiceRuntime)
        ->and(implode(chr(10), array_slice($lineas, (int) $indiceRuntime)))->not->toContain($copy);
});
