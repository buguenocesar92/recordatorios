<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Las cabeceras de seguridad tienen que cubrir el panel, que es donde están los datos.
 *
 * `CabecerasDeEndurecimiento` se añade al grupo «web» (bootstrap/app.php). Filament NO usa ese
 * grupo: el panel declara su propia lista de middleware, así que todo lo que cuelga de
 * /admin se servía sin política de contenido, sin X-Frame-Options, sin nosniff y sin
 * Referrer-Policy. Quedaba protegida la pantalla de login —que no guarda nada— y
 * desprotegido el listado de vecinos.
 *
 * Lo concreto que evita: sin X-Frame-Options el panel es enmarcable, y el borrado
 * masivo de usuarios está a un clic en el recurso de Usuarios. Sin política de
 * contenido desaparece la última red bajo cualquier inyección de script.
 */
uses(RefreshDatabase::class);

$cabecerasEsperadas = [
    'X-Frame-Options',
    'X-Content-Type-Options',
    'Referrer-Policy',
    'Content-Security-Policy',
    'Permissions-Policy',
];

it('sirve el panel con las cabeceras de seguridad', function () use ($cabecerasEsperadas) {
    $respuesta = $this->get('/admin/login')->assertOk();

    foreach ($cabecerasEsperadas as $cabecera) {
        expect($respuesta->headers->has($cabecera))
            ->toBeTrue("el panel se sirve sin la cabecera {$cabecera}");
    }
});

it('sigue sirviendo las rutas web con las cabeceras de seguridad', function () use ($cabecerasEsperadas) {
    // «/» es la portada pública (grupo «sitio», no «web»): lo que este caso
    // comprueba es que las cabeceras no dependen de estar dentro de /admin ni
    // del grupo web.
    $respuesta = $this->get('/')->assertOk();

    foreach ($cabecerasEsperadas as $cabecera) {
        expect($respuesta->headers->has($cabecera))
            ->toBeTrue("la ruta web se sirve sin la cabecera {$cabecera}");
    }
});

it('no permite enmarcar el panel desde otro sitio', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
