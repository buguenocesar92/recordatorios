<?php

declare(strict_types=1);

use App\Livewire\VerificarMfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

/**
 * El segundo factor no sirve si el código se puede adivinar a la fuerza: son
 * seis dígitos (900.000 combinaciones) y valen diez minutos. Sin un tope de
 * intentos, quien ya pasó la contraseña prueba códigos hasta acertar.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);
    config()->set('mfa.show_code', false);

    $this->usuario = User::factory()->create();
    $this->usuario->forceFill([
        'two_factor_code' => '123456',
        'two_factor_expires_at' => now()->addMinutes(10),
    ])->save();

    RateLimiter::clear('mfa:'.$this->usuario->getAuthIdentifier());
});

it('corta los intentos antes de que se pueda barrer el espacio', function () {
    $this->actingAs($this->usuario);
    $c = livewire(VerificarMfa::class);

    for ($i = 0; $i < 15; $i++) {
        $c->set('code', str_pad((string) $i, 6, '0', STR_PAD_LEFT))->call('verificar');
    }

    // Tras el bloqueo, ni el código correcto entra.
    $c->set('code', '123456')->call('verificar');

    expect(session()->get('auth.two_factor_verified'))->not->toBe(true);
})->group('seguridad');

it('deja entrar con el código correcto dentro del margen', function () {
    $this->actingAs($this->usuario);
    $c = livewire(VerificarMfa::class);

    $c->set('code', '000000')->call('verificar'); // un error de tipeo
    $c->set('code', '123456')->call('verificar'); // el correcto

    expect(session()->get('auth.two_factor_verified'))->toBe(true);
})->group('seguridad');
