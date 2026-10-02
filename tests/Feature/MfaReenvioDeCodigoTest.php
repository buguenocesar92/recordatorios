<?php

declare(strict_types=1);

use App\Livewire\VerificarMfa;
use App\Models\User;
use App\Notifications\Auth\MfaCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

/**
 * `VerificarMfa::mount()` no mandaba un código nuevo mientras el anterior
 * siguiera vigente: la única forma de pedir otro era esperar los diez
 * minutos completos (o recargar, topado por `mfa-envio`). `reenviar()`
 * invalida el código anterior y manda uno nuevo, con un tope por cuenta para
 * que no se use el sistema como manguera de correos.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);

    // El tope viene apagado en la batería —una automatización que entra y sale
    // varias veces se bloquearía a sí misma—: este test, que es justamente el
    // que lo comprueba, lo enciende a mano.
    config()->set('seguridad.limitar_intentos', true);

    $this->usuario = User::factory()->create(['email' => 'encargada@municipalidadgraneros.cl']);

    RateLimiter::clear('mfa-reenvio:'.$this->usuario->id);
    RateLimiter::clear('mfa-envio:'.$this->usuario->id);
});

it('reenviar() con un código vigente manda una notificacion nueva y cambia el código', function (): void {
    // Un código conocido y vigente: mount() lo reutiliza y no manda nada, así
    // que la única notificación de esta prueba la manda `reenviar()`.
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    Notification::fake();
    $this->actingAs($this->usuario);

    $codigoAnterior = $this->usuario->two_factor_code;

    livewire(VerificarMfa::class)->call('reenviar');

    Notification::assertSentToTimes($this->usuario, MfaCodeNotification::class, 1);

    $usuarioFresco = $this->usuario->fresh();

    expect($usuarioFresco->two_factor_code)->not->toBe($codigoAnterior)
        // Por ambos lados: en Carbon 3 `diffInSeconds` lleva signo, así que
        // acotar solo «menor que» dejaría pasar un código que viviera una hora.
        ->and($usuarioFresco->two_factor_expires_at->between(now()->addSeconds(595), now()->addSeconds(605)))->toBeTrue();
})->group('seguridad');

it('el código anterior deja de servir después de reenviar', function (): void {
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    Notification::fake();
    $this->actingAs($this->usuario);

    $componente = livewire(VerificarMfa::class)->call('reenviar');

    $componente->set('code', '654321')->call('verificar')->assertHasErrors(['code']);

    expect(session()->get('auth.two_factor_verified'))->not->toBe(true);
})->group('seguridad');

it('el cuarto reenvio dentro de 5 minutos no manda nada y avisa que hay que esperar', function (): void {
    // Vigente desde el principio: mount() no manda nada, así que las tres
    // notificaciones de esta prueba son, sin ambigüedad, las de `reenviar()`.
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    Notification::fake();
    $this->actingAs($this->usuario);

    $componente = livewire(VerificarMfa::class);

    $componente->call('reenviar');
    $componente->call('reenviar');
    $componente->call('reenviar');
    $componente->call('reenviar');

    Notification::assertSentToTimes($this->usuario, MfaCodeNotification::class, 3);

    // El aviso cuelga del botón, no del campo `code`: el error queda limpio.
    $componente->assertHasNoErrors();
    expect($componente->get('avisoDeReenvio'))->toContain('Espera');
})->group('seguridad');

it('tras reenviar, codigoReutilizado queda en false y la vista avisa que hay un código nuevo', function (): void {
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    Notification::fake();
    $this->actingAs($this->usuario);

    $componente = livewire(VerificarMfa::class)->call('reenviar');

    expect($componente->get('codigoReutilizado'))->toBeFalse()
        ->and($componente->get('codigoReenviado'))->toBeTrue();

    $componente->assertSee('código nuevo');
})->group('seguridad');

it('el tope de reenvíos es por cuenta: agotar el de una no toca el de otra', function (): void {
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    $otra = User::factory()->create(['email' => 'otra@municipalidadgraneros.cl']);
    $otra->forceFill([
        'two_factor_code' => '111111',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();
    RateLimiter::clear('mfa-reenvio:'.$otra->id);

    Notification::fake();

    $this->actingAs($this->usuario);
    $componente = livewire(VerificarMfa::class);
    foreach (range(1, 4) as $intento) {
        $componente->call('reenviar');
    }
    Notification::assertSentToTimes($this->usuario, MfaCodeNotification::class, 3);

    $this->actingAs($otra);
    livewire(VerificarMfa::class)->call('reenviar')->assertHasNoErrors();

    Notification::assertSentToTimes($otra, MfaCodeNotification::class, 1);
})->group('seguridad');

it('una sesión que ya pasó el segundo factor no puede seguir pidiendo códigos', function (): void {
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    Notification::fake();
    $this->actingAs($this->usuario);

    // El caso real: la pantalla quedó abierta (snapshot vivo), la persona ya
    // pasó el segundo factor en otra pestaña, y alguien pulsa el botón viejo.
    // Se monta ANTES de marcar la sesión, porque con la marca puesta `mount()`
    // redirige y no habría componente al que llamarle nada.
    $componente = livewire(VerificarMfa::class);
    session()->put('auth.two_factor_verified', true);

    $componente->call('reenviar')->assertRedirect();

    Notification::assertNothingSent();
    expect($this->usuario->fresh()->two_factor_code)->toBe($this->usuario->two_factor_code);
})->group('seguridad');

it('reenviar limpia el error y el código viejo del campo', function (): void {
    $this->usuario->forceFill([
        'two_factor_code' => '654321',
        'two_factor_expires_at' => now()->addMinutes(9),
    ])->save();

    Notification::fake();
    $this->actingAs($this->usuario);

    $componente = livewire(VerificarMfa::class)
        ->set('code', '000000')
        ->call('verificar')
        ->assertHasErrors(['code']);

    $componente->call('reenviar')
        ->assertHasNoErrors()
        ->assertSet('code', '')
        ->assertSet('avisoDeReenvio', null)
        ->assertDispatched('mfa-reenviado');
})->group('seguridad');
