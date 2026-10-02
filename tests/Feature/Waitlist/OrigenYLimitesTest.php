<?php

declare(strict_types=1);

use App\Models\WaitlistSignup;
use App\Waitlist\ConfirmationToken;
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
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

/** El origen de la aplicación tal como lo ve la petición de prueba (APP_URL). */
function origenDeLaApp(): string
{
    return rtrim((string) config('app.url'), '/');
}

function altaConCabeceras(array $cabeceras, array $datos = []): TestResponse
{
    return test()->withHeaders($cabeceras)->post('/lista-de-espera', array_merge([
        'email' => 'ana@example.com',
        'consent' => '1',
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], $datos));
}

describe('VerificarOrigenDelFormulario (CSRF sin sesión)', function () {
    beforeEach(fn () => Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]));

    it('rechaza Sec-Fetch-Site distinto de same-origin aunque el Origin sea el correcto', function (string $sitio) {
        altaConCabeceras(['Sec-Fetch-Site' => $sitio, 'Origin' => origenDeLaApp()])->assertForbidden();

        expect(WaitlistSignup::count())->toBe(0);
    })->with(['same-site' => 'same-site', 'none' => 'none', 'cross-site' => 'cross-site']);

    it('sin Sec-Fetch-Site, pasa con el Origin exacto de la aplicación', function () {
        altaConCabeceras(['Origin' => origenDeLaApp()])->assertOk();

        expect(WaitlistSignup::count())->toBe(1);
    });

    it('rechaza Origin: null (documento aislado, iframe con sandbox, redirección entre sitios)', function () {
        altaConCabeceras(['Origin' => 'null'])->assertForbidden();
    });

    it('rechaza un Origin que se parece pero no es el de la aplicación', function (string $origen) {
        altaConCabeceras(['Origin' => $origen])->assertForbidden();
    })->with([
        'otro host' => 'http://evil.example',
        'otro esquema' => 'https://localhost:8211',
        'otro puerto' => 'http://localhost:8080',
        'prefijo' => 'http://localhost:8211.evil.example',
    ]);

    it('sin ninguna de las dos cabeceras falla cerrado', function () {
        altaConCabeceras([])->assertForbidden();
    });
});

describe('Turnstile: el hostname del desafío tiene que ser el nuestro', function () {
    it('un token resuelto en otro sitio (hostname distinto) no inscribe', function () {
        config()->set('services.turnstile.hostname', 'recordatorios.example');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'sitio-del-atacante.example'])]);

        altaConCabeceras(['Sec-Fetch-Site' => 'same-origin'])->assertStatus(422)->assertSee('id="error-turnstile"', false);

        expect(WaitlistSignup::count())->toBe(0);
        Mail::assertNothingSent();
    });

    it('si Cloudflare no informa hostname y se espera uno, tampoco', function () {
        config()->set('services.turnstile.hostname', 'recordatorios.example');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        altaConCabeceras(['Sec-Fetch-Site' => 'same-origin'])->assertStatus(422);

        expect(WaitlistSignup::count())->toBe(0);
    });

    it('con el hostname esperado, pasa', function () {
        config()->set('services.turnstile.hostname', 'recordatorios.example');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'recordatorios.example'])]);

        altaConCabeceras(['Sec-Fetch-Site' => 'same-origin'])->assertOk();

        expect(WaitlistSignup::count())->toBe(1);
    });
});

describe('límite waitlist-token: 30 por minuto y por IP', function () {
    function abrirEnlace(string $ip, string $token): TestResponse
    {
        return test()->withServerVariables(['REMOTE_ADDR' => $ip])->get('/lista-de-espera/confirmar/'.$token);
    }

    it('la petición 31 desde la misma IP recibe 429; otra IP no se ve afectada', function () {
        $token = ConfirmationToken::generate();

        foreach (range(1, 30) as $i) {
            abrirEnlace('203.0.113.7', $token)->assertNotFound();
        }

        abrirEnlace('203.0.113.7', $token)->assertStatus(429);
        abrirEnlace('198.51.100.9', $token)->assertNotFound();
    });

    it('pasado el minuto vuelve a responder', function () {
        $token = ConfirmationToken::generate();

        foreach (range(1, 31) as $i) {
            abrirEnlace('203.0.113.7', $token);
        }
        abrirEnlace('203.0.113.7', $token)->assertStatus(429);

        $this->travel(61)->seconds();

        abrirEnlace('203.0.113.7', $token)->assertNotFound();
    });
});
