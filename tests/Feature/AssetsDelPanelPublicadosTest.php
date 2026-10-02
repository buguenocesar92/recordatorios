<?php

/**
 * Los paquetes del ecosistema inyectan sus propios assets en el panel con
 * `renderHook`, pero los ARCHIVOS hay que publicarlos con `vendor:publish`. Si
 * el publish no se corre —o si una versión nueva del paquete agrega un archivo
 * más, como muni-ui 0.16 con `filepond-video.js`— el panel sigue cargando y
 * nadie se entera: el navegador responde 404 en silencio y el panel se queda
 * sin los estilos de identidad municipal.
 *
 * Este test recorre el HTML del panel y exige que cada asset propio que
 * referencia exista en disco. Es la comprobación que faltaba: no depende de
 * acordarse de publicar.
 */
it('todo asset propio que el panel referencia existe en public/', function () {
    // La pantalla de acceso del panel sirve: los render hooks del paquete se
    // aplican a TODAS sus páginas, y así la comprobación no depende de una
    // sesión ni del segundo factor.
    $html = $this->get('/admin/login')->getContent();

    preg_match_all('#(?:src|href)="([^"]*/vendor/[^"]+\.(?:css|js))(?:\?[^"]*)?"#', $html, $coincidencias);

    $faltantes = collect($coincidencias[1])
        ->map(fn (string $url): string => parse_url($url, PHP_URL_PATH) ?: $url)
        ->unique()
        ->reject(fn (string $ruta): bool => file_exists(public_path(ltrim($ruta, '/'))))
        ->values()
        ->all();

    expect($faltantes)->toBe([], 'el panel los pide y responden 404');
});
