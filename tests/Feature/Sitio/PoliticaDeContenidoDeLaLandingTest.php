<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * La landing hereda la política estricta del sitio: `script-src 'self'
 * 'nonce-…'`, sin unsafe-inline. Y el único <script> que emite —el JSON-LD—
 * lleva el nonce de ESA petición, para que ningún HTML inyectado pueda imitarlo.
 */
uses(RefreshDatabase::class);

it('la portada sale con la política estricta del sitio', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    // El estilo lleva 'unsafe-inline' en TODO el sistema a propósito —Filament
    // y Livewire escriben `style="…"` sin nonce (ver PoliticaBase)—: el
    // candado real es que el SCRIPT no lo tenga, como ya vigila
    // PoliticaDeContenidoTest para el mismo «/». Por eso se busca la frase
    // combinada que sólo aparece en la excepción del panel, no la palabra
    // suelta.
    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->and($csp)->not->toContain("'unsafe-inline' 'unsafe-eval'")
        ->and($csp)->not->toContain("'unsafe-eval'");
});

it('el JSON-LD lleva el nonce de la petición', function () {
    $respuesta = $this->get('/');

    preg_match("/'nonce-([^']+)'/", (string) $respuesta->headers->get('Content-Security-Policy'), $m);
    $nonce = $m[1];

    expect($respuesta->getContent())->toContain('<script type="application/ld+json" nonce="'.$nonce.'">');
});
