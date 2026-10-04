<?php

declare(strict_types=1);

use App\Waitlist\ClaveDeOrigen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * Un /64 de IPv6 es lo que un ISP le da a UN cliente: contar por dirección
 * exacta le regalaba 2^64 cubetas de 5 intentos. Y el límite por correo no
 * frenaba a quien manda miles de direcciones distintas del mismo dominio
 * (bombardeo a una organización).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::flushState();
    Cache::flush();
    Mail::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

function altaDesde(string $ip, string $email): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', ['email' => $email, 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);
}

it('agrupa IPv6 por /64 y deja IPv4 tal cual', function () {
    expect(ClaveDeOrigen::de('2001:db8:1:2::1'))->toBe(ClaveDeOrigen::de('2001:db8:1:2:ffff:ffff:ffff:ffff'))
        ->and(ClaveDeOrigen::de('2001:db8:1:2::1'))->not->toBe(ClaveDeOrigen::de('2001:db8:1:3::1'))
        ->and(ClaveDeOrigen::de('198.51.100.7'))->toBe('198.51.100.7')
        ->and(ClaveDeOrigen::de(null))->toBe('');
});

it('seis altas desde un mismo /64 de IPv6: la sexta recibe 429', function () {
    foreach (range(1, 5) as $n) {
        altaDesde("2001:db8:1:2::{$n}", "p{$n}@dominio{$n}.example")->assertOk();
    }

    altaDesde('2001:db8:1:2::6', 'p6@dominio6.example')->assertStatus(429);
    // Otro /64 sigue pudiendo.
    altaDesde('2001:db8:1:3::1', 'p7@dominio7.example')->assertOk();
});

it('el límite de los enlaces con token también agrupa por /64', function () {
    foreach (range(1, 30) as $n) {
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:9:9::'.dechex($n)])
            ->get('/lista-de-espera/confirmar/'.str_repeat('a', 43))->assertNotFound();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:9:9::ff'])
        ->get('/lista-de-espera/confirmar/'.str_repeat('a', 43))->assertStatus(429);
});

it('límite por dominio del correo, aunque cambien la IP y la dirección', function () {
    config()->set('waitlist.domain_attempts_per_minute', 2);

    altaDesde('198.51.100.1', 'uno@victima.example')->assertOk();
    altaDesde('198.51.100.2', 'dos@VICTIMA.example')->assertOk();
    altaDesde('198.51.100.3', 'tres@victima.example')->assertStatus(429);
    altaDesde('198.51.100.4', 'otra@otro.example')->assertOk();
});
