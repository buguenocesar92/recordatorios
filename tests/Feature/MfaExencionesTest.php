<?php

use App\Http\Middleware\TwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Qué se exceptúa del segundo factor, y qué no.
 *
 * Las exenciones de un control de acceso son el lugar donde más fácil se cuela un
 * agujero, porque se escriben pensando en un caso legítimo y terminan cubriendo más de
 * lo que se pretendía. Acá hay dos que sí hacen falta —la propia pantalla de
 * verificación y la salida— y una tercera que había que quitar.
 *
 * La que se quitó exceptuaba las peticiones de Livewire. No hacía nada, porque
 * comparaba contra «livewire/*» y Livewire usa un prefijo con un hash
 * («livewire-5bccae48/update»), pero el problema no era que estuviera rota: es que
 * arreglarla habría dejado sin cubrir todas las acciones del panel, que es justo lo
 * que viajan por ahí.
 *
 * No hace falta ninguna exención para Livewire. El middleware persistente no recibe la
 * petición al punto de llamada, sino una reconstruida con la ruta que originó el
 * componente, así que la exención de la pantalla de verificación ya la cubre.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
    config()->set('mfa.enabled', true);
});

it('no exceptúa una petición por el solo hecho de parecer de Livewire', function () {
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');
    $this->actingAs($usuario);

    $middleware = new TwoFactorAuthentication;
    $siguienteFueLlamado = false;

    // Se imita el prefijo real de Livewire, con hash incluido: si alguien reintroduce
    // la exención «arreglándole» el patrón, esta comprobación lo detiene.
    $peticion = Request::create('/livewire-5bccae48/update', 'POST');

    $respuesta = $middleware->handle($peticion, function () use (&$siguienteFueLlamado) {
        $siguienteFueLlamado = true;

        return response('llegó al componente');
    });

    expect($siguienteFueLlamado)->toBeFalse(
        'una petición con pinta de Livewire se saltó el segundo factor: '
        .'las acciones del panel quedarían sin cubrir'
    );
    expect($respuesta->getStatusCode())->toBe(302);
});

it('exceptúa la pantalla de verificación, o no habría forma de verificar', function () {
    // Sin esta exención el control se muerde la cola: para pasar el segundo factor hay
    // que entrar a la pantalla que el propio segundo factor bloquea.
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    $this->actingAs($usuario)
        ->get(route('mfa.verify'))
        ->assertSuccessful();
});

it('deja salir sin haber verificado', function () {
    // Quien no puede o no quiere completar la verificación tiene que poder cerrar la
    // sesión; si no, queda atrapado con una sesión abierta que no puede cerrar.
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    $respuesta = $this->actingAs($usuario)->post('/logout');

    expect($respuesta->status())->not->toBe(500);
    expect(auth()->check())->toBeFalse('la sesión siguió abierta tras pedir la salida');
});
