<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;

/**
 * Un nonce vive exactamente una petición, y es el MISMO en los tres sitios que lo
 * usan: la cabecera, `@cspNonce` en las vistas y Vite (de donde lo toma Livewire).
 *
 * El gotcha que vigila: en web-graneros (2026-08-20) la página se guardaba en
 * caché ya renderizada, el nonce de la primera visita quedaba pegado al HTML y la
 * cabecera cambiaba en cada petición: guiones bloqueados. Este scaffold no cachea
 * páginas, pero el proceso SÍ sobrevive entre peticiones (Octane), y spatie guarda
 * el nonce como binding «scoped» del contenedor: si alguien lo convierte en
 * singleton, o si el vaciado por petición deja de correr, dos visitas compartirían
 * el nonce. El proceso de tests no vacía los «scoped» solo, así que este caso
 * falla justo en ese escenario.
 */
uses(RefreshDatabase::class);

function nonceDeLaPolitica(string $csp): ?string
{
    preg_match('/nonce-([A-Za-z0-9+\/=]+)/', $csp, $partes);

    return $partes[1] ?? null;
}

it('dos peticiones seguidas reciben nonces distintos', function () {
    $primero = nonceDeLaPolitica((string) $this->get('/')->headers->get('Content-Security-Policy'));
    $segundo = nonceDeLaPolitica((string) $this->get('/')->headers->get('Content-Security-Policy'));

    expect($primero)->not->toBeNull('la política de «/» no declara ningún nonce')
        ->and($segundo)->not->toBeNull()
        ->and($segundo)->not->toBe($primero, 'el nonce sobrevivió de una petición a la siguiente');
});

it('el nonce de la cabecera es el que reparte el contenedor y el que usa Vite', function () {
    $nonce = nonceDeLaPolitica((string) $this->get('/')->headers->get('Content-Security-Policy'));

    expect($nonce)->not->toBeNull()
        ->and(app('csp-nonce'))->toBe($nonce)
        ->and(Vite::cspNonce())->toBe($nonce);
});

it('Livewire etiqueta su guion con el nonce de ESTA petición, también en el panel', function () {
    // Livewire 4 toma el nonce de Vite::cspNonce() al escribir su <script>. Si
    // el nonce se resolviera DESPUÉS de dibujar la página, Vite devolvería null
    // y el guion saldría sin etiquetar: en la política estricta del sitio lo
    // bloquearía el navegador.
    $nonces = [];

    foreach ([1, 2] as $visita) {
        // Livewire escribe su <script> una sola vez por ciclo de vida y lo
        // vuelve a permitir en «flush-state», que Octane dispara entre
        // peticiones. El proceso de tests no lo hace solo: sin esto, la segunda
        // visita sale sin el guion y el caso mediría otra cosa.
        Livewire::flushState();

        $html = (string) $this->get('/admin/login')->assertOk()->getContent();

        preg_match('/<script[^>]*livewire[^>]*nonce="([^"]+)"/', $html, $partes);

        expect($partes)->toHaveCount(2, 'el guion de Livewire salió sin nonce')
            ->and($partes[1])->toBe(app('csp-nonce'), 'Livewire lleva un nonce distinto del de la petición');

        $nonces[] = $partes[1];
    }

    expect($nonces[0])->not->toBe($nonces[1], 'el nonce del guion de Livewire se repitió entre dos peticiones');
});
