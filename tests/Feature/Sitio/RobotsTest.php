<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('robots.txt cierra el panel y anuncia el sitemap solo en el dominio publicado', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');
    config()->set('sitio.dominios_indexables', ['sistema.ejemplo.cl']);

    $propio = $this->get('https://sistema.ejemplo.cl/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->getContent();

    expect($propio)->toContain("User-agent: *\n")
        ->and($propio)->toContain("Disallow: /admin\n")
        ->and($propio)->toContain("Disallow: /livewire\n")
        ->and($propio)->toContain("Sitemap: https://sistema.ejemplo.cl/sitemap.xml\n");

    $ajeno = $this->get('http://pruebas.interno/robots.txt')->assertOk()->getContent();

    expect($ajeno)->not->toContain('Sitemap:')
        ->and($ajeno)->toContain("Disallow: /\n");
});
