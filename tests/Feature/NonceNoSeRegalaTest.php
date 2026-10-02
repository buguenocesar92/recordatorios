<?php

use Illuminate\Support\Facades\Route;

/**
 * El nonce no se le regala a quien logre inyectar HTML.
 *
 * La política de contenido existe justamente para el día en que se cuele un
 * script donde no debe. El middleware sustituía en todo el cuerpo un marcador
 * fijo y conocido; ese día, al atacante le bastaba escribir el marcador como
 * valor de su atributo «nonce» y el servidor se lo convertía en válido.
 * Comprobado antes del arreglo: el cuerpo salía con el mismo nonce que declaraba
 * la cabecera.
 */
function nonceDeLaCabecera(string $csp): ?string
{
    preg_match('/nonce-([A-Za-z0-9+\/=]+)/', $csp, $partes);

    return $partes[1] ?? null;
}

it('un contenido inyectado con el marcador viejo no obtiene un nonce válido', function () {
    Route::middleware('web')->get('/prueba-nonce', fn () => response(
        '<script nonce="__NONCE_DE_LA_POLITICA__">alert(1)</script>',
        200,
        ['Content-Type' => 'text/html'],
    ));

    $respuesta = $this->get('/prueba-nonce');

    $nonce = nonceDeLaCabecera((string) $respuesta->headers->get('Content-Security-Policy'));

    expect($nonce)->not->toBeNull('la política tiene que declarar un nonce');
    expect((string) $respuesta->getContent())
        ->toContain('__NONCE_DE_LA_POLITICA__')
        ->and((string) $respuesta->getContent())
        ->not->toContain((string) $nonce);
});

it('las vistas propias sí reciben el nonce real', function () {
    // La otra mitad: quitar el reemplazo no puede dejar sin nonce a los guiones
    // legítimos, o la política los bloquearía a ellos. Se mide en «/» —la única
    // ruta fuera del panel que se pide sin sesión— porque lo que se compara es
    // lo que las vistas reciben (`@cspNonce` lee `app('csp-nonce')`) contra lo
    // que declara la cabecera.
    $respuesta = $this->get('/');

    $nonce = nonceDeLaCabecera((string) $respuesta->headers->get('Content-Security-Policy'));

    expect($nonce)->not->toBeNull()
        ->and(app('csp-nonce'))->toBe($nonce);
});
