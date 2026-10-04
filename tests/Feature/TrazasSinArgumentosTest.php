<?php

declare(strict_types=1);

/**
 * Las trazas de excepción no llevan los argumentos de las llamadas: una traza
 * que termina en un log, en failed_jobs o en el colector de errores arrastraba
 * correos descifrados, tokens y contraseñas pasados como argumento.
 */
it('php-seguridad.ini apaga los argumentos en las trazas', function () {
    expect((string) file_get_contents(base_path('docker/php-seguridad.ini')))
        ->toMatch('/^zend\.exception_ignore_args\s*=\s*On\s*$/m');
});

it('la imagen copia ese ini a la configuración de PHP', function () {
    expect((string) file_get_contents(base_path('Dockerfile')))->toContain('php-seguridad.ini');
});
