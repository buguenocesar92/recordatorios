<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Las cabeceras que no son la política de contenido.
 *
 * Al pasar la CSP a spatie/laravel-csp, el resto de lo que emitía el viejo
 * `SecurityHeaders` se movió a `CabecerasDeEndurecimiento`. Estos casos fijan que
 * ninguna se perdió en la mudanza, con su valor exacto, y en las dos superficies:
 * el grupo «web» y el panel, que no pasa por «web».
 */
uses(RefreshDatabase::class);

$fijas = [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Permitted-Cross-Domain-Policies' => 'none',
    'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=()',
    'Cross-Origin-Opener-Policy' => 'same-origin',
];

it('las cabeceras fijas llegan con su valor exacto a la web y al panel', function () use ($fijas) {
    foreach (['/', '/admin/login'] as $url) {
        $respuesta = $this->get($url);

        foreach ($fijas as $cabecera => $valor) {
            expect($respuesta->headers->get($cabecera))->toBe($valor, "{$url} → {$cabecera}");
        }
    }
});

it('HSTS solo se promete por HTTPS', function () {
    // Prometer HSTS por HTTP es un error de configuración que los navegadores
    // ignoran; y en local, sin TLS, dejaría el sitio inaccesible durante un año.
    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains')
        ->and($this->get('http://localhost/')->headers->has('Strict-Transport-Security'))
        ->toBeFalse();
});

it('un dominio declarado como indexable no lleva X-Robots-Tag', function () {
    config()->set('sitio.dominios_indexables', ['ejemplo.cl']);
    // El dominio canónico es el de APP_URL (App\Support\Indexacion).
    config()->set('app.url', 'http://ejemplo.cl');

    expect($this->get('http://ejemplo.cl/')->headers->has('X-Robots-Tag'))->toBeFalse()
        ->and($this->get('http://otro.test/')->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('no queda ninguna política de contenido armada a mano', function () {
    // Dos escritores de la misma cabecera es el estado que produjo dieciséis
    // copias divergentes en el ecosistema. Lo único que escribe
    // Content-Security-Policy es spatie/laravel-csp; el código propio declara
    // presets.
    // Por nombre y no por ::class: importar una clase que no existe es lo que
    // se quiere evitar, no lo que se quiere escribir.
    expect(class_exists('App\Http\Middleware\SecurityHeaders'))
        ->toBeFalse('SecurityHeaders volvió: la CSP tiene un solo dueño, spatie/laravel-csp');

    $artesanales = [];

    $archivos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($archivos as $archivo) {
        if (! str_ends_with((string) $archivo, '.php')) {
            continue;
        }

        if (str_contains((string) file_get_contents((string) $archivo), "'Content-Security-Policy'")) {
            $artesanales[] = str_replace(base_path().'/', '', (string) $archivo);
        }
    }

    expect($artesanales)->toBe([], "escriben la cabecera a mano:\n".implode("\n", $artesanales));
});
