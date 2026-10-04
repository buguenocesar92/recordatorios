<?php

declare(strict_types=1);

use App\Waitlist\ClaveDeOrigen;
use App\Waitlist\ClavesDeLimite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        ->and(ClaveDeOrigen::de('198.51.100.7'))->toBe('198.51.100.7');
});

it('una IPv4 mapeada en IPv6 cuenta como su IPv4, no como un /64 compartido por todo IPv4', function () {
    expect(ClaveDeOrigen::de('::ffff:198.51.100.7'))->toBe('198.51.100.7')
        ->and(ClaveDeOrigen::de('::ffff:198.51.100.8'))->toBe('198.51.100.8');
});

it('IPv4 se cuenta por /32: dos vecinos no comparten cubeta', function () {
    expect(ClaveDeOrigen::de('198.51.100.7'))->not->toBe(ClaveDeOrigen::de('198.51.100.8'));
});

it('sin IP o con una ilegible no cae en una clave constante compartida, y avisa', function (?string $ip) {
    Log::spy();

    $a = ClaveDeOrigen::de($ip);
    $b = ClaveDeOrigen::de($ip);

    expect($a)->not->toBe($b)->and($a)->toStartWith('sin-ip:');
    Log::shouldHaveReceived('warning')->twice();
})->with(['nula' => null, 'vacía' => '', 'basura' => 'no-es-ip', 'con puerto' => '198.51.100.7:443']);

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

it('el límite global es un techo alto que solo cuentan las altas que ya pasaron los límites por IP y dominio', function () {
    expect(config('waitlist.global_attempts_per_minute'))->toBeGreaterThanOrEqual(300);

    config()->set('waitlist.global_attempts_per_minute', 7);

    // Un solo atacante: 5 pasan su límite por IP; los 20 siguientes los frena
    // el por-IP y NO gastan el cupo global.
    foreach (range(1, 25) as $n) {
        altaDesde('203.0.113.66', "x{$n}@d{$n}.example");
    }

    altaDesde('198.51.100.1', 'legitima1@ejemplo.example')->assertOk();
    altaDesde('198.51.100.2', 'legitima2@ejemplo.example')->assertOk();
});

it('al tocar el techo global responde la página amable y deja un aviso en el log', function () {
    config()->set('waitlist.global_attempts_per_minute', 1);
    Log::spy();

    altaDesde('198.51.100.1', 'a@uno.example')->assertOk();
    altaDesde('198.51.100.2', 'b@dos.example')->assertStatus(429)->assertSee('unos minutos');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m): bool => str_contains($m, 'techo global'))->once();
});

it('la clave por dominio no crece con lo que mande el cliente: se recorta y se hashea', function () {
    expect((string) file_get_contents(app_path('Providers/AppServiceProvider.php')))->toContain("'waitlist-dominio:'.hash('sha256'")
        ->and(ClavesDeLimite::dominio(str_repeat('a', 5_000_000).'@victima.example'))->toBe('');
});

describe('normalización de las claves (sin atajos para esquivar los límites)', function () {
    it('el dominio se normaliza: mayúsculas, punto final, subdominios e IDN cuentan como el mismo', function () {
        $base = ClavesDeLimite::dominio('x@victima.example');

        expect(ClavesDeLimite::dominio('x@Victima.EXAMPLE.'))->toBe($base)
            ->and(ClavesDeLimite::dominio('x@sub.victima.example'))->toBe($base)
            ->and(ClavesDeLimite::dominio('x@a.b.c.victima.example'))->toBe($base)
            ->and(ClavesDeLimite::dominio('x@ñandú.cl'))->toBe(ClavesDeLimite::dominio('x@'.idn_to_ascii('ñandú.cl', IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)))
            ->and(ClavesDeLimite::dominio('x@ÑANDÚ.cl'))->toBe(ClavesDeLimite::dominio('x@ñandú.cl'));
    });

    it('respeta los sufijos de segundo nivel comunes (no junta a todo .co.uk ni .gob.cl)', function () {
        expect(ClavesDeLimite::dominio('x@mail.victima.co.uk'))->toBe('victima.co.uk')
            ->and(ClavesDeLimite::dominio('x@victima.gob.cl'))->toBe('victima.gob.cl')
            ->and(ClavesDeLimite::dominio('x@empresa.cl'))->not->toBe(ClavesDeLimite::dominio('x@otra.cl'));
    });

    it('el buzón se canoniza: mayúsculas y +etiqueta en todos, puntos y googlemail en Gmail', function () {
        expect(ClavesDeLimite::buzon('Ana+promo@Example.com'))->toBe(ClavesDeLimite::buzon('ana@example.com'))
            ->and(ClavesDeLimite::buzon('a.n.a+x@gmail.com'))->toBe(ClavesDeLimite::buzon('ana@googlemail.com'))
            ->and(ClavesDeLimite::buzon('a.na@otro.example'))->not->toBe(ClavesDeLimite::buzon('ana@otro.example'));
    });

    it('variantes +etiqueta del mismo buzón no reciben un segundo correo dentro de las 24 h', function () {
        altaDesde('198.51.100.1', 'ana+1@example.com')->assertOk();
        altaDesde('198.51.100.2', 'ana+2@example.com')->assertOk();
        altaDesde('198.51.100.3', 'A.N.A@gmail.com')->assertOk();
        altaDesde('198.51.100.4', 'ana+z@googlemail.com')->assertOk();

        Mail::assertSentCount(2);
    });

    it('rotar subdominios no esquiva el límite por dominio', function () {
        config()->set('waitlist.domain_attempts_per_minute', 2);

        altaDesde('198.51.100.1', 'uno@a.victima.example')->assertOk();
        altaDesde('198.51.100.2', 'dos@b.victima.example')->assertOk();
        altaDesde('198.51.100.3', 'tres@C.Victima.Example')->assertStatus(429);
    });

    it('IPv6 entre corchetes o con zona se agrupa igual por /64', function () {
        $base = ClaveDeOrigen::de('2001:db8:1:2::1');

        expect(ClaveDeOrigen::de('[2001:db8:1:2::7]'))->toBe($base)
            ->and(ClaveDeOrigen::de('2001:db8:1:2::9%eth0'))->toBe($base)
            ->and(ClaveDeOrigen::de('[::ffff:198.51.100.7]'))->toBe('198.51.100.7');
    });
});
