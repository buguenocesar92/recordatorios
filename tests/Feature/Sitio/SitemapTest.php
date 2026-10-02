<?php

use App\Support\Sitio\Sitemap;
use App\Support\Sitio\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/**
 * El sitemap se genera desde un registro al que cada módulo público agrega sus
 * URLs (la portada acá; el portal, en la Fase 2). Solo se sirve en un dominio
 * indexable: publicarlo en un dominio de pruebas invita a indexar lo que no
 * debe.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');
    config()->set('sitio.dominios_indexables', ['sistema.ejemplo.cl']);
    Sitemap::reiniciar();
});

it('es XML válido e incluye la portada con la dirección publicada', function () {
    $respuesta = $this->get('https://sistema.ejemplo.cl/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string($respuesta->getContent());

    expect($xml)->not->toBeFalse('El sitemap no es XML válido');

    $locs = array_map('strval', iterator_to_array($xml->url->loc ? $xml->xpath('//*[local-name()="loc"]') : [], false));

    expect($locs)->toContain('https://sistema.ejemplo.cl/');
});

it('incluye lo que otros módulos registran', function () {
    Sitemap::agregar(fn (): iterable => [new Url('/tramites', changefreq: 'weekly', priority: 0.8)]);

    $xml = $this->get('https://sistema.ejemplo.cl/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>https://sistema.ejemplo.cl/tramites</loc>')
        ->and($xml)->toContain('<changefreq>weekly</changefreq>')
        ->and($xml)->toContain('<priority>0.8</priority>');
});

it('fuera de un dominio indexable no existe', function () {
    $this->get('http://pruebas.interno/sitemap.xml')->assertNotFound();
});

it('se sirve desde la caché', function () {
    Cache::spy();
    $this->get('https://sistema.ejemplo.cl/sitemap.xml')->assertOk();
    Cache::shouldHaveReceived('remember')->withArgs(fn ($clave) => $clave === 'sitio:sitemap')->once();
});
