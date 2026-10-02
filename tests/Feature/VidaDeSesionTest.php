<?php

use App\Http\Middleware\AcotarVidaDeSesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * El techo de una sesión abierta: cuánto dura.
 *
 * No lo trae el framework: la caducidad corriente mide inactividad, así que a
 * quien trabaja seguido no lo alcanza nunca y la sesión dura para siempre.
 *
 * Ya NO se ata al prefijo de red (2026-09-09): cortaba la sesión de quien
 * pasaba de wifi a datos móviles, a media pantalla, y el riesgo aceptado de
 * no atarla queda escrito en el docblock de AcotarVidaDeSesion.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
    config()->set('mfa.enabled', false);

    $this->usuario = User::factory()->create();
    $this->usuario->assignRole('super_admin');
});

it('una sesión recién iniciada funciona', function () {
    // Control: sin esto, un middleware que cortara TODO dejaría el resto de las
    // comprobaciones en verde y el panel inservible.
    $this->actingAs($this->usuario)
        ->get('/admin')
        ->assertSuccessful();
});

it('la sesión se corta al pasar las doce horas', function () {
    $this->actingAs($this->usuario)->get('/admin')->assertSuccessful();

    // Se envejece la marca de inicio en vez de esperar doce horas.
    session(['sesion.iniciada_en' => now()->getTimestamp() - AcotarVidaDeSesion::DURACION_MAXIMA - 60]);

    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));

    expect(Auth::check())->toBeFalse('la sesión siguió autenticada tras vencer');
});

it('justo antes de las doce horas la sesión sigue viva', function () {
    // La frontera importa: sin esta comprobación, un middleware que cortara
    // siempre pasaría la de arriba sin distinguir nada.
    $this->actingAs($this->usuario)->get('/admin')->assertSuccessful();

    session(['sesion.iniciada_en' => now()->getTimestamp() - AcotarVidaDeSesion::DURACION_MAXIMA + 120]);

    $this->get('/admin')->assertSuccessful();
});

it('cambiar de red no cierra la sesión', function () {
    // El caso real que motivó el cambio: pasar del wifi de la oficina a datos
    // móviles cambia la IP en cada request y antes cerraba la sesión a media
    // pantalla. Ya no se guarda ni se mira ningún dato de red.
    $this->actingAs($this->usuario)
        ->withServerVariables(['REMOTE_ADDR' => '190.114.38.101'])
        ->get('/admin')
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '181.42.197.77'])
        ->get('/admin')
        ->assertSuccessful();

    expect(Auth::check())->toBeTrue('la sesión se cerró al cambiar de red');
});

it('cortada la sesión se puede volver a entrar sin tropiezos', function () {
    // Al invalidar la sesión hay que rotar el testigo; si no, el primer intento
    // de volver a entrar muere por token inválido y la persona ve un error que
    // no explica nada.
    $this->actingAs($this->usuario)->get('/admin')->assertSuccessful();

    session(['sesion.iniciada_en' => now()->getTimestamp() - AcotarVidaDeSesion::DURACION_MAXIMA - 60]);
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));

    $this->get(route('filament.admin.auth.login'))->assertSuccessful();
});

it('el corte también protege las peticiones de Livewire', function () {
    // Un middleware puesto solo en «authMiddleware» se aplica a la primera carga
    // y a ninguna acción posterior: casi todo lo que ocurre en el panel viaja
    // por Livewire. Así fue como el segundo factor no protegió NADA del panel en
    // nueve sistemas del ecosistema, y nadie se enteró hasta la auditoría.
    $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($provider)->toContain('addPersistentMiddleware')
        ->and($provider)->toContain('AcotarVidaDeSesion::class');
});
