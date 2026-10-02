<?php

declare(strict_types=1);

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistSignup;
use App\Rules\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Flag estático de Livewire (ver PortadaTest): sin esto, un test anterior
    // que renderizó un componente inyecta sus estilos y las respuestas difieren.
    Livewire::flushState();
    Cache::flush();
    Mail::fake();
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

function enviarLista(array $datos = [], array $quitar = []): TestResponse
{
    $cuerpo = array_merge([
        'email' => 'ana@example.com',
        'consent' => '1',
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], $datos);

    foreach ($quitar as $campo) {
        unset($cuerpo[$campo]);
    }

    return test()->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', $cuerpo);
}

function turnstileResponde(bool $exito): void
{
    Http::fake(['challenges.cloudflare.com/*' => Http::response(
        $exito ? ['success' => true, 'hostname' => 'localhost'] : ['success' => false, 'error-codes' => ['invalid-input-response']],
    )]);
}

it('sin el campo de Turnstile (omitido del todo) no se inscribe ni se manda correo', function () {
    turnstileResponde(true);

    enviarLista(quitar: ['cf-turnstile-response'])
        ->assertStatus(422)
        ->assertSee('id="error-turnstile"', false);

    expect(WaitlistSignup::count())->toBe(0);
    Mail::assertNothingOutgoing();
    Http::assertNothingSent();
});

it('con el campo de Turnstile vacío tampoco', function () {
    turnstileResponde(true);

    enviarLista(['cf-turnstile-response' => ''])->assertStatus(422)->assertSee('id="error-turnstile"', false);

    expect(WaitlistSignup::count())->toBe(0);
    Mail::assertNothingOutgoing();
});

it('con un token que Cloudflare rechaza: 422', function () {
    turnstileResponde(false);

    enviarLista()->assertStatus(422)->assertSee('id="error-turnstile"', false);

    expect(WaitlistSignup::count())->toBe(0);
    Mail::assertNothingOutgoing();
});

it('si Cloudflare no responde, falla cerrado', function () {
    Http::fake(['challenges.cloudflare.com/*' => fn () => throw new ConnectionException('timeout')]);

    enviarLista()->assertStatus(422);

    expect(WaitlistSignup::count())->toBe(0);
});

it('verifica en el servidor con la clave secreta de la configuración, nunca del request', function () {
    turnstileResponde(true);
    config()->set('services.turnstile.secret', 'secreto-de-config');

    enviarLista(['secret' => 'secreto-del-atacante'])->assertOk();

    Http::assertSent(fn (PeticionHttp $p): bool => $p->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $p['secret'] === 'secreto-de-config'
        && $p['response'] === 'XXXX.DUMMY.TOKEN.XXXX');
});

it('el sexto POST desde la misma IP en un minuto recibe 429', function () {
    turnstileResponde(true);

    foreach (range(1, 5) as $i) {
        enviarLista(['email' => "p{$i}@example.com"])->assertOk();
    }

    enviarLista(['email' => 'p6@example.com'])->assertStatus(429);
});

it('el mismo correo dos veces en 24 h: misma respuesta y un solo correo', function () {
    turnstileResponde(true);

    $primera = enviarLista()->assertOk();
    Mail::assertQueued(WaitlistConfirmationMail::class, 1);

    $segunda = enviarLista(['email' => 'ANA@example.com '])->assertOk();
    Mail::assertQueued(WaitlistConfirmationMail::class, 1);

    $sinNonce = fn (string $html): string => preg_replace('/nonce="[^"]*"/', '', $html);
    expect($sinNonce($segunda->getContent()))->toBe($sinNonce($primera->getContent()))
        ->and(WaitlistSignup::count())->toBe(1);
});

it('un correo nuevo y uno ya confirmado reciben exactamente la misma respuesta', function () {
    turnstileResponde(true);

    $nuevo = enviarLista(['email' => 'nuevo@example.com'])->assertOk();

    WaitlistSignup::query()->update(['confirmed_at' => now()]);
    Cache::flush();
    Mail::fake();

    $existente = enviarLista(['email' => 'nuevo@example.com'])->assertOk();
    Mail::assertNothingOutgoing();

    $sinNonce = fn (string $html): string => preg_replace('/nonce="[^"]*"/', '', $html);
    expect($sinNonce($existente->getContent()))->toBe($sinNonce($nuevo->getContent()));
});

it('la regla de Turnstile es implícita: falla con el campo ausente aunque nadie ponga «required»', function () {
    turnstileResponde(true);

    $validador = Validator::make([], ['cf-turnstile-response' => [new Turnstile]]);

    expect($validador->fails())->toBeTrue();
    Http::assertNothingSent();
});
