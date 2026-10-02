<?php

/**
 * Que los archivos estáticos lleguen con instrucciones de caché.
 *
 * Los sirve Caddy directamente, sin pasar por PHP, así que ningún middleware de
 * la aplicación puede ponerles cabeceras: van en la configuración del servidor.
 * Y ahí ya se perdieron dos veces en silencio —una por ponerlas fuera del bloque
 * del sitio, otra porque Octane sobreescribe la variable de entorno donde
 * estaban— sin que nada fallara: el sitio andaba igual, solo que cada visitante
 * se volvía a bajar todo en cada visita.
 *
 * Este test mira el archivo de configuración, no una respuesta HTTP: la suite
 * corre sin el servidor levantado. Comprueba las dos cosas que se rompieron.
 */
it('las reglas de caché se inyectan por donde Octane no las pisa', function () {
    // Octane arma su propio Caddyfile y sobreescribe la variable de entorno
    // «CADDY_SERVER_EXTRA_DIRECTIVES» con su configuración de Mercure. Lo único
    // que se aplica después —y por lo tanto manda— es esta clave de config.
    $env = config('octane.caddy.env', []);

    expect($env)->toHaveKey('CADDY_SERVER_EXTRA_DIRECTIVES');
});

it('las reglas van de lo general a lo específico', function () {
    $archivo = base_path('docker/caddy/estaticos.caddyfile');

    expect(file_exists($archivo))->toBeTrue('Falta la configuración de caché de estáticos');

    $reglas = (string) file_get_contents($archivo);

    // Dentro de un «route» de Caddy gana la última coincidencia. Si «@otros»
    // (que agarra todo *.css) quedara después de «@sellados», el paquete de
    // estilos perdería su año de caché y volvería a pedirse cada día, sin que
    // nada avise.
    $posicionOtros = strpos($reglas, '@otros path');
    $posicionSellados = strpos($reglas, '@sellados path');

    expect($posicionOtros)->toBeLessThan($posicionSellados);
});

it('cada tipo de archivo tiene su regla', function () {
    $reglas = (string) file_get_contents(base_path('docker/caddy/estaticos.caddyfile'));

    // Lo que lleva el sello del contenido en el nombre no puede cambiar sin
    // cambiar de nombre: se guarda para siempre. Lo que el editor puede
    // reemplazar conservando el nombre, no.
    expect($reglas)->toContain('*.woff2')
        ->and($reglas)->toContain('*.avif')
        ->and($reglas)->toContain('*.mp4')
        ->and($reglas)->toContain('immutable');
});
