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
 * El RateLimiter de verificar() solo frena a quien intenta ADIVINAR el
 * código. La generación de uno nuevo, en mount(), no tenía tope: quien ya
 * pasó usuario y contraseña puede dejar que el código expire (diez minutos),
 * recargar la pantalla, y forzar un correo nuevo indefinidamente, usando la
 * casilla de la víctima como buzón propio. Este test comprueba el tope: un
 * máximo de envíos dentro de una ventana corta.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);

    Notification::fake();

    $this->usuario = User::factory()->create();
    $this->actingAs($this->usuario);

    RateLimiter::clear('mfa-envio:'.$this->usuario->getAuthIdentifier());

    // El tope viene apagado en la batería —una automatización que entra y sale
    // varias veces se bloquearía a sí misma—, así que este test, que es
    // justamente el que lo comprueba, lo enciende a mano.
    config()->set('seguridad.limitar_intentos', true);
});

it('limita los envíos de código nuevo a unos pocos seguidos', function () {
    // Cinco pedidos de código, cada uno con el anterior ya vencido: simula a
    // alguien recargando la pantalla cada diez minutos.
    for ($i = 0; $i < 5; $i++) {
        $this->usuario->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

        livewire(VerificarMfa::class);
    }

    Notification::assertSentToTimes($this->usuario, MfaCodeNotification::class, 5);

    // El sexto pedido, dentro de la misma ventana, no genera ni manda nada nuevo.
    $this->usuario->forceFill([
        'two_factor_code' => null,
        'two_factor_expires_at' => null,
    ])->save();

    $componente = livewire(VerificarMfa::class);

    Notification::assertSentToTimes($this->usuario, MfaCodeNotification::class, 5);
    expect($this->usuario->fresh()->two_factor_code)->toBeNull();
    $componente->assertHasErrors('code');
});
