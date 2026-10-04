<?php

use App\Support\ProxiesDeConfianza;
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

it('un X-Forwarded-Host forjado no cambia el robots.txt del dominio publicado (la respuesta se cachea en público)', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');
    config()->set('sitio.dominios_indexables', ['sistema.ejemplo.cl']);
    // Aun desde un proxy de confianza: el host lo decide APP_URL, no una cabecera.
    ProxiesDeConfianza::aplicar(['127.0.0.1']);

    $contenido = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
        ->get('https://sistema.ejemplo.cl/robots.txt')
        ->assertOk()
        ->getContent();

    expect($contenido)->toContain('Sitemap: https://sistema.ejemplo.cl/sitemap.xml')
        ->and($contenido)->not->toContain("Disallow: /\n");
});

it('un Host que no es el de APP_URL no se deja indexar aunque esté en la lista', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');
    config()->set('sitio.dominios_indexables', ['sistema.ejemplo.cl', 'otro.ejemplo.cl']);

    $contenido = $this->get('https://otro.ejemplo.cl/robots.txt')->assertOk()->getContent();

    expect($contenido)->toContain("Disallow: /\n")->and($contenido)->not->toContain('Sitemap:');
});
