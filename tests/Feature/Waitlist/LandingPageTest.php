<?php

declare(strict_types=1);

use App\Enums\ObligationTypeCode;
use App\Models\WaitlistSignup;
use App\Waitlist\VersionDelConsentimiento;
use Database\Seeders\ComunasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::flushState();
    Cache::flush();
    Mail::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'localhost'])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
    $this->seed(ComunasSeeder::class);
});

it('la portada dice qué es, qué no es, y trae el formulario de la lista de espera', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('No es un servicio del Estado ni de ningún municipio')
        ->toContain('No pedimos ClaveÚnica')
        ->toMatch('#<form[^>]*method="POST"[^>]*action="/lista-de-espera"#')
        ->toContain('href="/privacidad"');

    // La casilla de consentimiento existe y NO viene marcada.
    preg_match('#<input[^>]*name="consent"[^>]*>#', $html, $consent);
    expect($consent[0])->toContain('type="checkbox"')->not->toContain('checked');

    // 346 comunas en el selector, una casilla por tipo de obligación.
    preg_match('#<select[^>]*name="comuna".*?</select>#s', $html, $selector);
    expect(preg_match_all('#<option value="\d{5}"#', $selector[0]))->toBe(346);

    foreach (ObligationTypeCode::values() as $codigo) {
        expect($html)->toMatch('#<input[^>]*type="checkbox" name="interests\[\]" value="'.$codigo.'"#');
    }
});

it('cada campo tiene su etiqueta asociada', function () {
    $html = $this->get('/')->getContent();

    foreach (['email', 'comuna', 'consent'] as $campo) {
        preg_match('#<(?:input|select)\b[^>]*\bname="'.$campo.'"[^>]*>#', $html, $etiqueta);
        expect($etiqueta)->not->toBeEmpty("{$campo} no está");
        preg_match('#\bid="([^"]+)"#', $etiqueta[0], $m);
        expect($m)->not->toBeEmpty("{$campo} sin id")
            ->and($html)->toContain('for="'.$m[1].'"');
    }
});

it('las páginas legales responden', function (string $ruta) {
    $this->get($ruta)->assertOk()->assertSee('Borrador — pendiente de revisión de abogado');
})->with(['/privacidad', '/terminos', '/que-no-hacemos']);

it('la portada no enlaza al panel: es solo por VPN', function () {
    $this->get('/')->assertDontSee('/admin', false);
});

function postLista(array $datos): TestResponse
{
    return test()->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', array_merge([
            'email' => 'ana@example.com',
            'consent' => '1',
            'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ], $datos));
}

it('sin consentimiento no se inscribe: 422 con el error explicado en texto', function () {
    $respuesta = postLista(['consent' => null])->assertStatus(422);

    expect($respuesta->getContent())->toContain('id="error-consent"')
        ->and(WaitlistSignup::count())->toBe(0);
});

it('con un correo inválido responde 422', function () {
    postLista(['email' => 'no-es-un-correo'])->assertStatus(422)->assertSee('id="error-email"', false);
    expect(WaitlistSignup::count())->toBe(0);
});

it('una comuna o un tipo inventados se rechazan', function () {
    postLista(['comuna' => '99999'])->assertStatus(422);
    postLista(['interests' => ['inventado']])->assertStatus(422);
    expect(WaitlistSignup::count())->toBe(0);
});

it('sin intereses también se inscribe: son opcionales', function () {
    postLista(['interests' => null])->assertOk();

    expect(WaitlistSignup::count())->toBe(1);
});

it('guarda comuna, intereses, versión del consentimiento y la IP solo como hash', function () {
    postLista(['comuna' => '06106', 'interests' => ['soap', 'contribuciones']])->assertOk();

    $signup = WaitlistSignup::sole();

    expect($signup->comuna->code)->toBe('06106')
        ->and($signup->interests)->toBe(['soap', 'contribuciones'])
        ->and($signup->consent_version)->toBe(VersionDelConsentimiento::actual())
        ->and($signup->confirmed_at)->toBeNull()
        ->and($signup->ip_hash)->toHaveLength(64)
        ->and($signup->ip_hash)->not->toContain('127.0.0.1');
});

it('un POST desde otro sitio se rechaza (CSRF sin sesión: verificación de origen)', function () {
    $this->withHeaders(['Origin' => 'https://atacante.example', 'Sec-Fetch-Site' => 'cross-site'])
        ->post('/lista-de-espera', ['email' => 'ana@example.com', 'consent' => '1'])
        ->assertForbidden();

    // Sin cabeceras de origen tampoco: falla cerrado.
    $this->post('/lista-de-espera', ['email' => 'ana@example.com', 'consent' => '1'])->assertForbidden();

    expect(WaitlistSignup::count())->toBe(0);
});

it('ni la portada ni la respuesta del formulario emiten cookies de sesión', function () {
    expect($this->get('/')->headers->get('set-cookie'))->toBeNull()
        ->and(postLista([])->headers->get('set-cookie'))->toBeNull();
});

it('la respuesta del formulario no se guarda en ninguna caché', function () {
    expect((string) postLista([])->headers->get('Cache-Control'))->toContain('no-store');
});
