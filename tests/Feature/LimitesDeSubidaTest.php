<?php

use Filament\Forms\Components\FileUpload;

/**
 * Cuánto pesa lo que se puede subir.
 *
 * El contenedor no declaraba «upload_max_filesize», así que regía el valor de
 * fábrica de PHP: 2 MB. Una foto de teléfono pesa entre 8 y 12: subirla fallaba
 * siempre, y fallaba en silencio —PHP descarta el archivo y la aplicación ve el
 * campo vacío—, que es la peor forma de fallar.
 *
 * El tope del formulario y el del servidor tienen que estar de acuerdo: si el
 * formulario acepta más de lo que PHP recibe, el archivo se pierde en el camino
 * sin que nadie lo explique.
 */
it('el contenedor acepta el peso de una foto de teléfono', function () {
    $ini = (string) file_get_contents(base_path('docker/php-seguridad.ini'));

    expect($ini)->toContain('upload_max_filesize')
        ->and($ini)->toContain('post_max_size');

    preg_match('/upload_max_filesize\s*=\s*(\d+)M/', $ini, $subida);
    preg_match('/post_max_size\s*=\s*(\d+)M/', $ini, $envio);

    expect((int) ($subida[1] ?? 0))->toBeGreaterThanOrEqual(12)
        ->and((int) ($envio[1] ?? 0))->toBeGreaterThan((int) ($subida[1] ?? 0));
});

it('el formulario no acepta más de lo que el servidor recibe', function () {
    $ini = (string) file_get_contents(base_path('docker/php-seguridad.ini'));
    preg_match('/upload_max_filesize\s*=\s*(\d+)M/', $ini, $subida);

    $topeDelServidorEnKb = (int) ($subida[1] ?? 0) * 1024;
    $topeDelFormulario = (int) config('seguridad.subida_maxima_kb');

    expect($topeDelFormulario)->toBeGreaterThan(0)
        ->and($topeDelFormulario)->toBeLessThanOrEqual($topeDelServidorEnKb);
});

it('los campos de subida dejan abrir y descargar lo ya guardado', function () {
    $campo = FileUpload::make('archivo');

    expect($campo->isOpenable())->toBeTrue()
        ->and($campo->isDownloadable())->toBeTrue()
        ->and($campo->getMaxSize())->toBe((int) config('seguridad.subida_maxima_kb'));
});
