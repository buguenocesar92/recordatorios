<?php

declare(strict_types=1);

use App\Models\WaitlistSignup;
use App\Support\CredencialesDePlantilla;
use App\Waitlist\VersionDelConsentimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Ley 21.719: el aviso promete «si confirmas, hasta el lanzamiento más seis
 * meses». Sin fecha ni proceso, lo confirmado quedaba para siempre.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

function filaRetencion(array $extra = []): WaitlistSignup
{
    return WaitlistSignup::create(array_merge([
        'email' => 'ana'.uniqid().'@example.com',
        'interests' => [],
        'consent_version' => 'v',
        'consented_at' => now(),
        'ip_hash' => str_repeat('b', 64),
        'confirm_token_hash' => hash('sha256', uniqid('', true)),
        'confirm_expires_at' => now()->addDay(),
    ], $extra));
}

describe('WAITLIST_PURGE_AFTER', function () {
    it('pasada la fecha, maintenance:daily borra las inscripciones confirmadas', function () {
        config()->set('waitlist.purge_confirmed_after', now()->subDay()->toDateString());
        filaRetencion(['confirmed_at' => now()->subMonths(7)]);

        $this->artisan('maintenance:daily')->assertSuccessful();

        expect(WaitlistSignup::count())->toBe(0);
    });

    it('antes de la fecha, o sin fecha, se conservan', function (?string $fecha) {
        config()->set('waitlist.purge_confirmed_after', $fecha);
        filaRetencion(['confirmed_at' => now()->subMonths(7)]);

        $this->artisan('maintenance:daily')->assertSuccessful();

        expect(WaitlistSignup::count())->toBe(1);
    })->with(['futura' => fn () => now()->addMonth()->toDateString(), 'sin fecha' => null]);

    it('una fecha ilegible no borra nada', function () {
        config()->set('waitlist.purge_confirmed_after', 'el-lanzamiento');
        filaRetencion(['confirmed_at' => now()->subMonths(7)]);

        $this->artisan('maintenance:daily')->assertSuccessful();

        expect(WaitlistSignup::count())->toBe(1);
    });

    it('en producción no arranca sin una fecha de purga válida', function (?string $fecha) {
        app()['env'] = 'production';
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb.password', 'una-de-verdad');
        config()->set('backup.backup.password', 'otra-de-verdad');
        config()->set('mfa.enabled', true);
        config()->set('mfa.show_code', false);
        config()->set('waitlist.hash_salt', str_repeat('s', 32));
        config()->set('services.turnstile.sitekey', '0x4AAAAAAAreal');
        config()->set('services.turnstile.secret', '0x4AAAAAAAsecretoreal');
        config()->set('services.turnstile.hostname', 'recordatorios.example');
        config()->set('proxies.confiables', ['172.31.251.1']);
        config()->set('waitlist.purge_confirmed_after', $fecha);

        CredencialesDePlantilla::comprobar();
    })->with(['vacía' => null, 'ilegible' => 'pronto'])->throws(RuntimeException::class, 'WAITLIST_PURGE_AFTER');
});

it('al confirmar se borra el seudónimo de la IP (solo servía contra abusos del alta)', function () {
    $signup = filaRetencion();

    $signup->markConfirmed();

    expect($signup->fresh()->ip_hash)->toBeNull();
});

it('el hash del token se borra 72 h después de confirmar (antes sirve para responder 410)', function () {
    $vieja = filaRetencion(['confirmed_at' => now()->subHours(73)]);
    $reciente = filaRetencion(['confirmed_at' => now()->subHours(2)]);

    $this->artisan('maintenance:daily')->assertSuccessful();

    expect($vieja->fresh()->confirm_token_hash)->toBeNull()
        ->and($reciente->fresh()->confirm_token_hash)->not->toBeNull();
});

describe('versión del consentimiento', function () {
    it('sale del texto del aviso: si el texto cambia, cambia la versión', function () {
        expect(VersionDelConsentimiento::de('texto A'))->not->toBe(VersionDelConsentimiento::de('texto B'))
            ->and(VersionDelConsentimiento::de('texto A'))->toBe(VersionDelConsentimiento::de('texto A'))
            ->and(strlen(VersionDelConsentimiento::actual()))->toBeLessThanOrEqual(32)
            ->and(VersionDelConsentimiento::actual())
            ->toBe(VersionDelConsentimiento::de((string) file_get_contents(resource_path('views/sitio/legal/privacidad.blade.php'))));
    });

    it('cada alta guarda la versión del texto que se le mostró, y la página la publica', function () {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
            ->post('/lista-de-espera', ['email' => 'ana@example.com', 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])
            ->assertOk();

        expect(WaitlistSignup::sole()->consent_version)->toBe(VersionDelConsentimiento::actual());
        $this->get('/privacidad')->assertOk()->assertSee(VersionDelConsentimiento::actual());
    });
});

it('la página de baja no promete lo que los respaldos no cumplen', function () {
    $html = (string) file_get_contents(resource_path('views/lista/baja.blade.php'));

    expect($html)->not->toContain('No guardamos tu correo')
        ->and($html)->toContain('respaldos');
});
