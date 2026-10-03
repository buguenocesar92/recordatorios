<?php

use App\Enums\ObligationTypeCode;
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

    // Solo dos <script>: el JSON-LD (el navegador no lo ejecuta) y el widget
    // de Cloudflare Turnstile, autorizado por host en la política de la portada.
    preg_match_all('/<script\b([^>]*)>/i', $html, $scripts);
    foreach ($scripts[1] as $atributos) {
        expect(str_contains($atributos, 'application/ld+json')
            || str_contains($atributos, 'src="https://challenges.cloudflare.com/turnstile/v0/api.js"'))->toBeTrue("script no permitido: {$atributos}");
    }
    expect(count($scripts[1]))->toBe(2);
});

it('no arranca sesión ni emite cookies', function () {
    $respuesta = $this->get('/');

    expect($respuesta->headers->get('set-cookie'))->toBeNull();
});

it('no enlaza al panel: el panel del dueño es solo por VPN', function () {
    $this->get('/')->assertDontSee(route('filament.admin.auth.login', [], false), false);
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

it('ofrece una casilla por cada tipo de obligación', function () {
    $html = $this->get('/')->getContent();

    foreach (ObligationTypeCode::cases() as $tipo) {
        expect($html)->toContain($tipo->label());
    }
});

it('el pie muestra solo los datos de contacto configurados', function () {
    config()->set('sitio.entidad', ['tipo' => 'organizacion', 'nombre' => 'Entidad de Prueba', 'url' => 'https://x.cl', 'direccion' => '', 'telefono' => '+56 9 0000 0000', 'correo' => '']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('Entidad de Prueba')
        ->and($html)->toContain('+56 9 0000 0000')
        ->and($html)->toContain('No es un servicio del Estado ni de ningún municipio')
        ->and($html)->not->toContain('mailto:');
});

it('el skip link usa el anillo claro de la barra: el acento sobre el acento profundo medía 1,81:1', function () {
    $html = $this->get('/')->getContent();

    preg_match('/<a href="#contenido"[^>]*class="([^"]*)"/', $html, $m);

    expect($m)->not->toBeEmpty()
        ->and($m[1])->toContain('focus-visible:outline-(--marca-realce-claro)');
});

it('el resumen de errores enlaza a un control enfocable y no a un fieldset', function () {
    $html = $this->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', ['email' => 'ana@example.com', 'consent' => '1', 'interests' => ['no-existe']])
        ->getContent();

    expect($html)->toContain('href="#campo-interests"')
        ->and($html)->toMatch('/<input\s+id="campo-interests"\s+type="checkbox"/')
        ->and($html)->not->toMatch('/<fieldset id="campo-interests"/');
});

it('el desplazamiento suave respeta prefers-reduced-motion', function () {
    $css = (string) file_get_contents(resource_path('css/sitio.css'));

    preg_match_all('/scroll-behavior:\s*smooth/', $css, $todas, PREG_OFFSET_CAPTURE);
    expect($todas[0])->toHaveCount(1);

    $antes = substr($css, 0, $todas[0][0][1]);
    expect(strrpos($antes, '@media (prefers-reduced-motion: no-preference)'))->not->toBeFalse();
});

it('al responder 422 el foco va al resumen de errores y las casillas miden 24 px', function () {
    $html = $this->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', [])
        ->getContent();

    // Sin JavaScript, `autofocus` es la única forma de mover el foco tras una respuesta de servidor.
    expect($html)->toMatch('/<div[^>]*id="errores"[^>]*autofocus/')
        ->and($html)->not->toContain('size-5');
});

it('las casillas y opciones las dibuja el sitio con tokens: sin accent-color nativo (3,04:1 en oscuro)', function () {
    $css = (string) file_get_contents(resource_path('css/sitio.css'));

    expect($css)->toContain("input[type='checkbox']")
        ->and($css)->toMatch('/appearance:\s*none/')
        ->and($css)->toContain('border: 2px solid var(--sitio-atenuado)');

    foreach (['sitio/portada', 'lista/confirmada'] as $vista) {
        expect((string) file_get_contents(resource_path("views/{$vista}.blade.php")))->not->toContain('accent-(');
    }
});

it('el widget de Turnstile se reduce en teléfonos angostos para no desbordar a 320 px', function () {
    $css = (string) file_get_contents(resource_path('css/sitio.css'));

    expect($css)->toMatch('/@media \(max-width: 23\.5rem\)\s*\{\s*\.cf-turnstile > \*\s*\{[^}]*scale\(0\.8\)/');
});
