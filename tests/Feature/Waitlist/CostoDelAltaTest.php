<?php

declare(strict_types=1);

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistSignup;
use App\Waitlist\DominioConCorreo;
use App\Waitlist\EnviarConfirmacionDeLista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * Lo caro del alta (Turnstile por red, DNS del dominio) no se paga antes de
 * que pase lo barato. `email:rfc,dns` hacía una consulta DNS síncrona y sin
 * tope en CADA envío del formulario, aun con el captcha inválido: bastaban
 * envíos con dominios lentos para ocupar los workers de Octane.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::flushState();
    Cache::flush();
    Mail::fake();
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
});

function altaBarata(array $datos = [], string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', array_merge([
            'email' => 'ana@example.com',
            'consent' => '1',
            'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ], $datos));
}

it('en producción, con Turnstile inválido, no se evalúa el DNS del correo', function () {
    app()['env'] = 'production';
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    altaBarata(['email' => 'ana@dominio-que-no-existe.invalid'])
        ->assertStatus(422)
        ->assertSee('id="error-turnstile"', false)
        ->assertDontSee('no parece válido');
});

it('en producción, la validación del pedido no consulta DNS: el MX se comprueba en el trabajo', function () {
    app()['env'] = 'production';
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    altaBarata(['email' => 'ana@dominio-que-no-existe.invalid'])->assertOk();
});

it('si las reglas locales fallan no se le pregunta a Cloudflare', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    altaBarata(['email' => 'no-es-un-correo'])->assertStatus(422)->assertSee('no parece válido');
    altaBarata(['consent' => '0'])->assertStatus(422);

    Http::assertNothingSent();
});

it('Turnstile tiene tope de conexión además del tope total', function () {
    expect(config('services.turnstile.connect_timeout'))->toBeInt()->toBeLessThanOrEqual(3)
        ->and((string) file_get_contents(app_path('Rules/Turnstile.php')))->toContain('->connectTimeout(');
});

it('hay un límite global del formulario, no solo por IP', function () {
    config()->set('waitlist.global_attempts_per_minute', 2);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    altaBarata(['email' => 'a1@example.com'], '198.51.100.1')->assertOk();
    altaBarata(['email' => 'a2@example.com'], '198.51.100.2')->assertOk();
    altaBarata(['email' => 'a3@example.com'], '198.51.100.3')->assertStatus(429);
});

describe('comprobación del dominio en el trabajo de envío', function () {
    beforeEach(function () {
        config()->set('waitlist.verify_mx', true);
    });

    it('sin MX ni A no se manda nada y la inscripción se borra (no hay a quién escribirle)', function () {
        app()->instance(DominioConCorreo::class, new class extends DominioConCorreo
        {
            public function acepta(string $email): bool
            {
                return false;
            }
        });

        $signup = WaitlistSignup::create([
            'email' => 'ana@sin-correo.example', 'interests' => [], 'consent_version' => 'v', 'consented_at' => now(),
            'ip_hash' => str_repeat('a', 64), 'confirm_expires_at' => now()->addDay(),
        ]);

        (new EnviarConfirmacionDeLista($signup))->handle();

        Mail::assertNothingOutgoing();
        expect(WaitlistSignup::count())->toBe(0);
    });

    it('con dominio que recibe correo, se manda', function () {
        app()->instance(DominioConCorreo::class, new class extends DominioConCorreo
        {
            public function acepta(string $email): bool
            {
                return true;
            }
        });

        $signup = WaitlistSignup::create([
            'email' => 'ana@example.com', 'interests' => [], 'consent_version' => 'v', 'consented_at' => now(),
            'ip_hash' => str_repeat('a', 64), 'confirm_expires_at' => now()->addDay(),
        ]);

        (new EnviarConfirmacionDeLista($signup))->handle();

        Mail::assertSent(WaitlistConfirmationMail::class);
    });
});

it('la comprobación de MX va encendida solo en producción por omisión', function () {
    expect(config('waitlist.verify_mx'))->toBeFalse()
        ->and((string) file_get_contents(config_path('waitlist.php')))->toContain("'verify_mx' => env('APP_ENV') === 'production'");
});
