<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/**
 * La portada pública: lo primero que ve cualquiera y lo único que indexa un
 * buscador. Blade puro, sin sesión y sin JavaScript.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // `SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest` es un
    // flag ESTÁTICO de Livewire: si un test anterior de la suite renderizó un
    // componente, la portada saldría con el script de Livewire inyectado y el
    // caso «no lleva Livewire» fallaría por orden, no por la vista. En
    // producción lo vacía Octane en cada petición (PrepareLivewireForNextOperation);
    // acá lo vaciamos nosotros.
    Livewire::flushState();
});

it('la raíz responde la portada con el nombre del sistema', function () {
    config()->set('sitio.nombre', 'Trámites de Prueba');

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Inicio · Trámites de Prueba</title>', false)
        ->assertSee('<html lang="es"', false);
});

it('tiene un solo h1 y es el claim de la portada', function () {
    config()->set('sitio.portada.claim', 'Un claim de prueba');

    $html = $this->get('/')->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('Un claim de prueba');
});

it('no lleva Livewire, Alpine ni ningún script ejecutable', function () {
    $html = $this->get('/')->getContent();

    expect($html)->not->toContain('wire:')
        ->and($html)->not->toContain('livewire')
        ->and($html)->not->toContain('x-data')
        ->and($html)->not->toContain('resources/js/');

    // El único <script> permitido es el JSON-LD, que el navegador no ejecuta.
    preg_match_all('/<script\b([^>]*)>/i', $html, $scripts);
    foreach ($scripts[1] as $atributos) {
        expect($atributos)->toContain('application/ld+json');
    }
});

it('no arranca sesión ni emite cookies', function () {
    $respuesta = $this->get('/');

    expect($respuesta->headers->get('set-cookie'))->toBeNull();
});

it('enlaza a la única puerta de ingreso', function () {
    // Relativa a propósito (ver PortadaController): un enlace interno no
    // necesita host.
    $this->get('/')->assertSee(route('filament.admin.auth.login', [], false), false);
});

it('la única respuesta que vence una cookie vieja de «Recordarme» no es cacheable', function () {
    // Un caché compartido que guardara esa respuesta serviría su Set-Cookie a
    // otros visitantes. Se marca no-store y se retira el ETag; el resto de las
    // visitas —sin cookie— siguen siendo públicas y cacheables.
    $nombre = Auth::guard('web')->getRecallerName();

    $respuesta = $this->withUnencryptedCookie($nombre, 'valor-viejo')->get('/')->assertOk();

    expect($respuesta->headers->getCookies())->not->toBeEmpty()
        ->and((string) $respuesta->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $respuesta->headers->get('Cache-Control'))->not->toContain('public')
        ->and($respuesta->headers->has('ETag'))->toBeFalse();
});

it('trae lo que un buscador necesita, desde app.url y no desde el host', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');
    config()->set('sitio.nombre', 'Trámites');
    config()->set('sitio.descripcion', 'Descripción de prueba para buscadores.');

    $html = $this->get('http://10.0.0.5:8097/')->getContent();

    expect($html)->toContain('<meta name="description" content="Descripción de prueba para buscadores.">')
        ->and($html)->toContain('<link rel="canonical" href="https://sistema.ejemplo.cl/">')
        ->and($html)->toContain('<meta property="og:type" content="website">')
        ->and($html)->toContain('<meta property="og:site_name" content="Trámites">')
        ->and($html)->toContain('<meta property="og:title" content="Inicio · Trámites">')
        ->and($html)->toContain('<meta property="og:description" content="Descripción de prueba para buscadores.">')
        ->and($html)->toContain('<meta property="og:url" content="https://sistema.ejemplo.cl/">')
        ->and($html)->toContain('<meta property="og:locale" content="es_CL">')
        ->and($html)->toContain('<meta name="twitter:card" content="summary">')
        ->and($html)->not->toContain('10.0.0.5');
});

it('el JSON-LD describe a la entidad según su tipo', function () {
    config()->set('sitio.entidad', [
        'tipo' => 'municipalidad',
        'nombre' => 'Municipalidad de Prueba',
        'url' => 'https://prueba.cl',
        'direccion' => 'Calle 1, Comuna',
        'telefono' => '+56 9 1234 5678',
        'correo' => 'contacto@prueba.cl',
    ]);

    $html = $this->get('/')->getContent();

    preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $m);
    expect($m)->not->toBeEmpty('La portada no lleva JSON-LD');

    $datos = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

    expect($datos['@context'])->toBe('https://schema.org')
        ->and($datos['@type'])->toBe('GovernmentOrganization')
        ->and($datos['name'])->toBe('Municipalidad de Prueba')
        ->and($datos['url'])->toBe('https://prueba.cl')
        ->and($datos['telephone'])->toBe('+56 9 1234 5678')
        ->and($datos['email'])->toBe('contacto@prueba.cl')
        ->and($datos['address']['streetAddress'])->toBe('Calle 1, Comuna');

    config()->set('sitio.entidad.tipo', 'empresa');
    preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $this->get('/')->getContent(), $m);
    expect(json_decode($m[1], true)['@type'])->toBe('Organization');
});

it('el JSON-LD omite los datos de contacto que no están configurados', function () {
    config()->set('sitio.entidad', ['tipo' => 'organizacion', 'nombre' => 'X', 'url' => 'https://x.cl', 'direccion' => '', 'telefono' => '', 'correo' => '']);

    preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $this->get('/')->getContent(), $m);
    $datos = json_decode($m[1], true);

    expect($datos)->not->toHaveKeys(['telephone', 'email', 'address'])
        ->and($datos['@type'])->toBe('Organization');
});

it('con imagen de portada, Open Graph y twitter la anuncian y se precarga', function () {
    config()->set('app.url', 'https://sistema.ejemplo.cl');
    config()->set('sitio.portada.imagen', ['src' => 'imagenes/portada.webp', 'ancho' => 1600, 'alto' => 900, 'alt' => 'Vista de prueba']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('<meta property="og:image" content="https://sistema.ejemplo.cl/imagenes/portada.webp">')
        ->and($html)->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->and($html)->toContain('<link rel="preload" as="image" href="/imagenes/portada.webp"')
        ->and($html)->toContain('fetchpriority="high"')
        ->and($html)->toContain('width="1600" height="900"')
        ->and($html)->toContain('alt="Vista de prueba"');
});

it('tiene skip link, landmarks y navegación con nombre', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('href="#contenido"')
        ->and($html)->toContain('<header')
        ->and($html)->toContain('<nav aria-label="Principal"')
        ->and($html)->toContain('<main id="contenido"')
        ->and($html)->toContain('<footer');
});

it('lista los servicios configurados y enlaza cada uno si tiene ruta', function () {
    config()->set('sitio.portada.servicios', [
        ['titulo' => 'Permisos', 'descripcion' => 'Pedir un permiso.', 'ruta' => 'filament.admin.auth.login'],
        ['titulo' => 'Consultas', 'descripcion' => 'Ver el estado.', 'ruta' => null],
    ]);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('id="servicios"')
        ->and($html)->toContain('Permisos')->toContain('Pedir un permiso.')
        ->and($html)->toContain('Consultas')->toContain('Ver el estado.');

    // Con ruta, el título es un enlace; sin ruta, no hay un <a> vacío.
    // Enlace relativo a propósito (ver PortadaController): el HTML no lleva el host.
    expect(substr_count($html, 'href="'.route('filament.admin.auth.login', [], false).'"'))->toBeGreaterThanOrEqual(2);
});

it('el pie muestra solo los datos de contacto configurados', function () {
    config()->set('sitio.entidad', ['tipo' => 'organizacion', 'nombre' => 'Entidad de Prueba', 'url' => 'https://x.cl', 'direccion' => '', 'telefono' => '+56 9 0000 0000', 'correo' => '']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('Entidad de Prueba')
        ->and($html)->toContain('+56 9 0000 0000')
        ->and($html)->toContain('Ley 21.719')
        ->and($html)->not->toContain('mailto:');
});
