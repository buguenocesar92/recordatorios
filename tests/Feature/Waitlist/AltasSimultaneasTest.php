<?php

declare(strict_types=1);

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::flushState();
    Cache::flush();
    Mail::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

function altaDe(string $email): TestResponse
{
    return test()->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', [
            'email' => $email, 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ]);
}

function sinNonce(string $html): string
{
    return (string) preg_replace('/nonce="[^"]*"/', '', $html);
}

it('si otra petición del mismo correo va a medio camino, esta responde igual y no manda nada', function () {
    $referencia = altaDe('otra@example.com')->assertOk();
    Mail::fake();
    Cache::flush();

    // La «otra petición» tiene tomado el candado del correo.
    $candado = Cache::lock('waitlist-signup:'.WaitlistSignup::hashEmail('ana@example.com'), 30);
    expect($candado->get())->toBeTrue();

    $respuesta = altaDe('ana@example.com')->assertOk();

    expect(sinNonce($respuesta->getContent()))->toBe(sinNonce($referencia->getContent()))
        ->and(WaitlistSignup::query()->where('email_hash', WaitlistSignup::hashEmail('ana@example.com'))->count())->toBe(0);
    Mail::assertNothingSent();
});

it('si otra petición inserta el mismo correo justo antes (colisión del índice único), 200 genérico y a lo más un correo', function () {
    $referencia = altaDe('otra@example.com')->assertOk();
    Mail::fake();
    Cache::flush();

    // Simula la carrera: entre la consulta de «¿existe?» y el INSERT, la otra
    // petición se adelanta y deja la fila con el mismo email_hash.
    WaitlistSignup::creating(function (WaitlistSignup $signup): void {
        DB::table('waitlist_signups')->insert([
            'id' => (string) Str::ulid(),
            'email' => 'ya-estaba',
            'email_hash' => $signup->email_hash,
            'interests' => '[]',
            'consent_version' => 'v',
            'consented_at' => now(),
            'ip_hash' => str_repeat('c', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $respuesta = altaDe('ana@example.com');

    $respuesta->assertOk();
    expect(sinNonce($respuesta->getContent()))->toBe(sinNonce($referencia->getContent()))
        ->and(WaitlistSignup::query()->where('email_hash', WaitlistSignup::hashEmail('ana@example.com'))->count())->toBe(1);
    Mail::assertSent(WaitlistConfirmationMail::class, 0);
});

it('dos altas seguidas del mismo correo mandan un solo correo (el límite por correo es atómico)', function () {
    altaDe('ana@example.com')->assertOk();
    altaDe('ana@example.com')->assertOk();

    Mail::assertSent(WaitlistConfirmationMail::class, 1);
    expect(WaitlistSignup::count())->toBe(1);
});
