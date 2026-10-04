<?php

declare(strict_types=1);

use App\Models\WaitlistSignup;
use Database\Seeders\ComunasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Cualquiera puede escribir el correo de otro en el formulario. Volver a
 * inscribirlo cada 24 h alargaba sin fin la vida de una inscripción que su
 * dueño nunca confirmó, y pisaba su comuna, intereses y consentimiento.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Mail::fake();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    config()->set('waitlist.hash_salt', 'sal-de-prueba-larga');
    $this->seed(ComunasSeeder::class);
});

function reinscribir(array $datos, string $ip = '198.51.100.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->post('/lista-de-espera', array_merge([
            'email' => 'ana@example.com', 'consent' => '1', 'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ], $datos));
}

it('una reinscripción de un tercero no pisa la comuna, los intereses ni el consentimiento vigentes', function () {
    $this->travelTo(now()->startOfHour());
    reinscribir(['comuna' => '01101', 'interests' => ['permiso_circulacion']])->assertOk();
    $original = WaitlistSignup::sole();

    $this->travel(25)->hours();
    reinscribir(['comuna' => '13101', 'interests' => []], '203.0.113.9')->assertOk();

    $despues = $original->fresh();
    expect($despues->comuna_id)->toBe($original->comuna_id)
        ->and($despues->interests)->toBe($original->interests)
        ->and($despues->consented_at->equalTo($original->consented_at))->toBeTrue()
        ->and($despues->consent_version)->toBe($original->consent_version)
        ->and($despues->ip_hash)->toBe($original->ip_hash);
});

it('reinscribir una y otra vez no alarga la vida de lo no confirmado más allá del tope desde created_at', function () {
    $this->travelTo(now()->startOfHour());
    reinscribir([])->assertOk();
    $creada = WaitlistSignup::sole()->created_at->copy();
    $tope = $creada->copy()->addHours((int) config('waitlist.unconfirmed_max_lifetime_hours'));

    foreach (range(1, 8) as $vuelta) {
        $this->travel(25)->hours();
        reinscribir([], "203.0.113.{$vuelta}");

        $fila = WaitlistSignup::query()->first();
        if ($fila !== null && $fila->created_at->equalTo($creada)) {
            expect($fila->confirm_expires_at->lessThanOrEqualTo($tope))->toBeTrue();
        }
    }

    // Pasado el tope, la purga la alcanza aunque la hayan «refrescado».
    $this->travelTo($tope->copy()->addMinute());
    WaitlistSignup::query()->where('created_at', $creada)->update(['confirm_expires_at' => now()->addDay()]);
    $this->artisan('maintenance:daily')->assertSuccessful();

    expect(WaitlistSignup::query()->where('created_at', $creada)->exists())->toBeFalse();
});

it('el tope de vida es de unos días, no semanas', function () {
    expect(config('waitlist.unconfirmed_max_lifetime_hours'))->toBeInt()->toBeLessThanOrEqual(168);
});
