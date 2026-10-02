<?php

use App\Filament\Pages\Auth\Ingresar;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Qué recuerda la casilla «Recordarme».
 *
 * Recordaba la sesión: dejaba la cookie de Laravel, buena por catorce meses,
 * que al vencer la sesión volvía a autenticar sola y mandaba al segundo factor
 * sin pedir la contraseña. Ahora recuerda el correo y nada más: ahorra
 * escribirlo, y no abre ninguna puerta.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // El rol de administración no se llama igual en todos los sistemas: en
    // feria es «administrador». Se lee de la configuración de Shield en vez de
    // escribirlo, o la prueba da 403 en la mitad del ecosistema.
    $rolAdministrador = config('filament-shield.super_admin.name', 'super_admin');
    Role::findOrCreate($rolAdministrador, 'web');
    config()->set('mfa.enabled', true);

    $this->usuario = User::factory()->create([
        'email' => 'funcionaria@graneros.test',
        'password' => bcrypt('una-contrasena-larga-de-prueba'),
    ]);
    $this->usuario->assignRole($rolAdministrador);
});

function ingresar(string $correo, string $clave, bool $recordar): Testable
{
    return Livewire::test(Ingresar::class)
        ->fillForm(['email' => $correo, 'password' => $clave, 'remember' => $recordar])
        ->call('authenticate');
}

it('marcando la casilla no queda la cookie que entra sola', function () {
    $respuesta = ingresar('funcionaria@graneros.test', 'una-contrasena-larga-de-prueba', true);

    $respuesta->assertHasNoFormErrors();
    expect(Auth::check())->toBeTrue('no llegó a entrar, así que la prueba no comprueba nada');

    $recordarDeLaravel = Auth::guard('web')->getRecallerName();
    $emitidas = collect(Cookie::getQueuedCookies())
        ->map(fn ($cookie) => $cookie->getName());

    expect($emitidas)->not->toContain($recordarDeLaravel);
});

it('marcando la casilla se recuerda el correo', function () {
    ingresar('funcionaria@graneros.test', 'una-contrasena-larga-de-prueba', true);

    $galleta = collect(Cookie::getQueuedCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === Ingresar::COOKIE);

    expect($galleta)->not->toBeNull('no se guardó el correo')
        ->and($galleta->getValue())->toBe('funcionaria@graneros.test');
});

it('sin marcarla, el correo no se guarda', function () {
    ingresar('funcionaria@graneros.test', 'una-contrasena-larga-de-prueba', false);

    $galleta = collect(Cookie::getQueuedCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === Ingresar::COOKIE);

    // Se emite, pero vacía y vencida: es el olvido de quien lo tenía marcado
    // antes y lo desmarcó.
    expect($galleta?->getValue())->toBeEmpty();
});

it('con la contraseña equivocada no se guarda nada', function () {
    // Si se guardara igual, la cookie diría qué correos se probaron en ese
    // equipo, y eso es justo lo que no tiene que quedar escrito.
    ingresar('funcionaria@graneros.test', 'la-que-no-es', true);

    $galleta = collect(Cookie::getQueuedCookies())
        ->firstWhere(fn ($cookie) => $cookie->getName() === Ingresar::COOKIE);

    expect($galleta)->toBeNull();
    expect(Auth::check())->toBeFalse();
});

it('el formulario llega con el correo recordado', function () {
    // Se pide la página de verdad y no «Livewire::test», porque la cookie llega
    // con la petición del navegador y ese atajo no pasa por ahí.
    $this->withCookie(Ingresar::COOKIE, 'funcionaria@graneros.test')
        ->get(Filament::getCurrentOrDefaultPanel()->getLoginUrl())
        ->assertSuccessful()
        ->assertSee('funcionaria@graneros.test', escape: false);
});

it('sin cookie, el formulario llega vacío', function () {
    // Control: sin esto, un «fill» que pusiera siempre algo pasaría la de
    // arriba sin distinguir nada.
    $this->get(Filament::getCurrentOrDefaultPanel()->getLoginUrl())
        ->assertSuccessful()
        ->assertDontSee('funcionaria@graneros.test');
});
