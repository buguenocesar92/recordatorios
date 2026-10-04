<?php

declare(strict_types=1);

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistSignup;
use App\Waitlist\ConfirmationToken;
use App\Waitlist\EnviarConfirmacionDeLista;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

function altaRobusta(string $email = 'ana@example.com'): TestResponse
{
    return test()->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', ['email' => $email, 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);
}

function trabajoConUuid(WaitlistSignup $signup, string $uuid): EnviarConfirmacionDeLista
{
    $trabajo = new EnviarConfirmacionDeLista($signup);
    $cola = Mockery::mock(Job::class);
    $cola->shouldReceive('uuid')->andReturn($uuid);
    $trabajo->setJob($cola);

    return $trabajo;
}

function inscripcionSinEnviar(): WaitlistSignup
{
    return WaitlistSignup::create([
        'email' => 'ana@example.com', 'interests' => [], 'consent_version' => 'v', 'consented_at' => now(),
        'ip_hash' => str_repeat('a', 64), 'confirm_expires_at' => now()->addDay(),
    ]);
}

it('si el candado del correo está tomado un momento, reintenta y manda el correo', function () {
    // Otra petición lo tiene, pero termina en 1 s.
    expect(Cache::lock('waitlist-signup:'.WaitlistSignup::hashEmail('ana@example.com'), 1)->get())->toBeTrue();

    altaRobusta()->assertOk();

    Mail::assertSent(WaitlistConfirmationMail::class, 1);
});

it('un reintento del mismo trabajo reusa el token: el enlace del primer intento sigue sirviendo', function () {
    $signup = inscripcionSinEnviar();
    $tokens = [];

    trabajoConUuid($signup, 'uuid-del-trabajo')->handle();
    trabajoConUuid($signup->fresh(), 'uuid-del-trabajo')->handle();

    Mail::assertSent(WaitlistConfirmationMail::class, function (WaitlistConfirmationMail $correo) use (&$tokens): bool {
        $tokens[] = $correo->token;

        return true;
    });

    expect($tokens)->toHaveCount(2)
        ->and($tokens[0])->toBe($tokens[1])
        ->and(ConfirmationToken::looksValid($tokens[0]))->toBeTrue()
        ->and($signup->fresh()->confirm_token_hash)->toBe(ConfirmationToken::hash($tokens[0]));
});

it('otro envío (otro trabajo) sí genera un token distinto', function () {
    $signup = inscripcionSinEnviar();
    $tokens = [];

    trabajoConUuid($signup, 'uuid-uno')->handle();
    trabajoConUuid($signup->fresh(), 'uuid-dos')->handle();

    Mail::assertSent(WaitlistConfirmationMail::class, function (WaitlistConfirmationMail $correo) use (&$tokens): bool {
        $tokens[] = $correo->token;

        return true;
    });

    expect($tokens[0])->not->toBe($tokens[1]);
});

it('si no se pudo encolar, no se aplica el enfriamiento de 24 h: la persona puede reintentar', function () {
    $real = app(Dispatcher::class);
    $roto = Mockery::mock(Dispatcher::class);
    $roto->shouldReceive('dispatch')->andThrow(new RuntimeException('cola caída'));
    app()->instance(Dispatcher::class, $roto);

    altaRobusta()->assertOk();
    Mail::assertNothingSent();

    app()->instance(Dispatcher::class, $real);

    altaRobusta()->assertOk();
    Mail::assertSent(WaitlistConfirmationMail::class, 1);
});
