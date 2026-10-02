<?php

use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;

/**
 * Candado: la tipografía del panel se sirve desde este origen, no desde un tercero.
 *
 * `->font('Inter')` sin `provider:` hace que Filament resuelva la familia con
 * BunnyFontProvider (HasFont::getFontProvider() cae a Bunny en cuanto hay una
 * familia custom sin proveedor propio), que en CADA carga del panel pide
 * fonts.bunny.net y le entrega la IP de cada funcionario a un tercero (Ley
 * 21.719), además de dejar el panel sin tipografía en la LAN municipal
 * filtrada o sin internet. Filament ya publica «Inter Variable» self-hosted en
 * public/fonts/filament/filament/inter/: no hace falta instalar nada, basta con
 * no llamar a ->font() o con pasarle `provider: LocalFontProvider::class`.
 */
it('el panel Filament resuelve su tipografía con el proveedor local, no con Bunny', function () {
    expect(Filament::getPanel('admin')->getFontProvider())->toBe(LocalFontProvider::class);
});

it('la CSP del panel ya no abre ninguna excepción a fonts.bunny.net', function () {
    $csp = (string) test()->get('/admin/login')->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeEmpty();
    // OJO: `toContain()` con un segundo argumento de mensaje NO falla en esta
    // versión de Pest/PHPUnit — el "mensaje" se traga la aserción y el test
    // queda siempre en verde. Se comprueba con str_contains() a mano.
    expect(str_contains($csp, 'fonts.bunny.net'))->toBeFalse('La CSP todavía nombra a fonts.bunny.net');
});
