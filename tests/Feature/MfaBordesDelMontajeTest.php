<?php

use App\Livewire\VerificarMfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

/**
 * Los tres caminos de la pantalla del segundo factor que nadie recorría.
 *
 * Son casos de borde, y por eso mismo son los que importan: la pantalla del
 * segundo factor es la última puerta antes del panel, y lo que decide en estos
 * caminos —a quién manda al login, a quién deja pasar, cuándo genera un código
 * nuevo— no se ve nunca en el uso normal.
 */
uses(RefreshDatabase::class);

covers(VerificarMfa::class);

beforeEach(function () {
    config(['mfa.enabled' => true]);
    Notification::fake();
});

it('a quien no ha iniciado sesión lo manda al login, no a pedirle un código', function () {
    // Sin esto, la pantalla se montaría sin usuario y trabajaría contra null.
    // Pedirle un código a alguien que no dijo quién es no tiene sentido, y el
    // sitio correcto para mandarlo es el ingreso.
    livewire(VerificarMfa::class)->assertRedirect(route('filament.admin.auth.login'));

    Notification::assertNothingSent();
});

it('a quien ya pasó el segundo factor no se lo vuelve a pedir', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario);
    session()->put('auth.two_factor_verified', true);

    livewire(VerificarMfa::class)->assertRedirect();

    // Y sobre todo: NO se genera ni se manda un código nuevo. Si se generara,
    // cada visita a esta ruta dispararía un correo, y quedaría un código válido
    // dando vueltas sin que nadie lo haya pedido.
    Notification::assertNothingSent();

    expect($usuario->fresh()->two_factor_code)->toBeNull();
});

it('con el segundo factor apagado deja pasar y marca la sesión', function () {
    config(['mfa.enabled' => false]);

    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    livewire(VerificarMfa::class)->assertRedirect();

    expect(session()->get('auth.two_factor_verified'))->toBeTrue();

    Notification::assertNothingSent();
});

it('un correo sin arroba no se enmascara: se prefiere no mostrar nada', function () {
    // La pantalla dice a qué dirección se mandó el código, enmascarada. Si el
    // dato guardado no es una dirección, es mejor no mostrar nada que mostrar
    // un texto raro que parezca un error del sistema.
    $metodo = new ReflectionMethod(VerificarMfa::class, 'enmascararCorreo');

    expect($metodo->invoke(new VerificarMfa, 'esto-no-es-un-correo'))->toBeNull()
        ->and($metodo->invoke(new VerificarMfa, ''))->toBeNull();

    // Y con una dirección de verdad sí enmascara, sin entregar el nombre entero.
    $enmascarado = $metodo->invoke(new VerificarMfa, 'veronica@municipalidad.cl');

    expect($enmascarado)
        ->toContain('@municipalidad.cl')
        ->and($enmascarado)->not->toContain('veronica');
});
