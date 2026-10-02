<?php

use Filament\Forms\Components\FileUpload;

/**
 * Nada de lo que sube alguien puede ejecutarse.
 *
 * Comprobado en local antes de existir este arreglo: un archivo .php dejado en
 * storage/app/public se ejecutaba al pedirlo por /storage —devolvió
 * «EJECUTADO-8.5.9»—. Es decir, cualquiera con permiso para subir un archivo
 * podía ejecutar código en el servidor. Es el mismo agujero que ya se cerró una
 * vez en el sitio municipal, y este repo es la plantilla de la que nacen todos
 * los sistemas: acá se multiplica por cada instalación.
 *
 * Se cierra en dos capas a propósito. Un campo concreto puede necesitar aceptar
 * otro tipo de archivo y ampliar su lista; la del servidor no se puede ampliar
 * por descuido desde el código de la aplicación.
 */
it('los campos de subida solo aceptan lo que está en la lista', function () {
    $campo = FileUpload::make('archivo');

    $aceptados = $campo->getAcceptedFileTypes();

    expect($aceptados)->not->toBeEmpty('Sin lista, un campo acepta cualquier extensión');

    // Lista de lo que SE acepta, no de lo que se prohíbe: una lista de
    // prohibidos siempre se queda corta.
    foreach (['application/x-httpd-php', 'text/x-php', 'application/x-sh'] as $peligroso) {
        expect($aceptados)->not->toContain($peligroso);
    }
});

it('el servidor se niega a ejecutar lo subido, aunque el formulario falle', function () {
    $reglas = (string) file_get_contents(base_path('docker/caddy/estaticos.caddyfile'));

    // Verificado en vivo tras aplicarlo: .php, .phtml, .phar, .pht y .sh bajo
    // /storage devuelven 403, y una imagen en la misma carpeta sigue dando 200.
    expect($reglas)->toContain('subidas_ejecutables')
        ->and($reglas)->toContain('403');

    foreach (['php', 'phtml', 'phar', 'pht'] as $extension) {
        expect($reglas)->toContain($extension === 'php' ? 'php[0-9]?' : $extension);
    }
});

it('las fotos se reducen en el navegador antes de subirse', function () {
    // Una foto de teléfono llega con 4000 px y entre 8 y 12 MB, y se muestra a
    // 800 px como mucho. Lo hace el propio Filament con FilePond: no hace falta
    // ninguna dependencia extra, ni WebAssembly, para resolver esto.
    $campo = FileUpload::make('foto');

    expect($campo->getImageResizeMode())->toBe('contain')
        ->and((int) $campo->getImageResizeTargetWidth())->toBeGreaterThanOrEqual(1200)
        // Sin esto, una imagen ya pequeña se estira hasta el ancho objetivo y
        // sale peor de lo que entró.
        ->and($campo->getImageResizeUpscale())->toBeFalse();
});
