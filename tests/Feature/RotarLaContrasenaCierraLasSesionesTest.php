<?php

use Kraftdo\Shared\Onboarding\OnboardingTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Rotar la contraseña tiene que cerrar TODAS las sesiones abiertas, no solo las
 * del panel.
 *
 * Es la respuesta de incidente estándar: se sospecha que una cookie de sesión se
 * filtró —un equipo compartido del mesón, una captura de pantalla, un registro
 * con la cookie dentro— y lo primero que hace el operador es cambiar la
 * contraseña. Filament declara `AuthenticateSession` a mano en el panel, así que
 * `/admin` sí se cerraba; el grupo «web» no lo llevaba, y todo lo autenticado que
 * vive fuera del panel —hoy «/verificar-mfa» y `routes/api.php`, que va con
 * ['web','auth','mfa']— seguía abierto con la contraseña vieja.
 *
 * El daño no es teórico: el operador cree haber revocado el acceso y sigue
 * pudiéndose leer y escribir por esas rutas. Y este repositorio es el MOLDE del
 * que sale cada sistema municipal, donde `routes/web.php` deja de tener dos rutas
 * para tener cuarenta.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);

    Role::findOrCreate('operador', 'web');

    $this->usuario = User::factory()->create(['password' => Hash::make('la-que-se-filtro')]);
    $this->usuario->assignRole('operador');

    // El hash con el que quedó atada la sesión robada.
    $this->hashViejo = $this->usuario->getAuthPassword();

    OnboardingTour::create([
        'name' => 'Tour del panel',
        'system' => 'admin',
        'role' => 'operador',
        'surface' => 'panel',
        'active' => true,
    ]);
});

/** Lo que hace el operador al enterarse del incidente. */
function rotarLaContrasena(User $usuario): void
{
    $usuario->forceFill(['password' => Hash::make('la-nueva-de-verdad')])->save();
}

it('cierra la pantalla del segundo factor cuando la contraseña ya se rotó', function () {
    rotarLaContrasena($this->usuario);

    $this->actingAs($this->usuario)
        ->withSession(['password_hash_web' => $this->hashViejo])
        ->get(route('mfa.verify'))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('cierra las rutas de api cuando la contraseña ya se rotó', function () {
    rotarLaContrasena($this->usuario);

    $this->actingAs($this->usuario)
        ->withSession([
            'password_hash_web' => $this->hashViejo,
            'auth.two_factor_verified' => true,
        ])
        ->getJson(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertUnauthorized();
});

it('no molesta a la sesión legítima, que sigue atada al hash vigente', function () {
    $this->actingAs($this->usuario)
        ->withSession([
            'password_hash_web' => $this->hashViejo,
            'auth.two_factor_verified' => true,
        ])
        ->getJson(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertOk();
});

it('el primer ingreso no necesita traer el hash en la sesión', function () {
    // Nadie sembró «password_hash_web»: es la sesión recién creada. El
    // middleware la adopta en vez de cerrarla, o nadie podría entrar nunca.
    $this->actingAs($this->usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->getJson(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertOk();
});

it('deja pasar a quien no ha iniciado sesión: sin usuario no hay nada que comprobar', function () {
    // La raíz es la landing pública: quien no entró la ve igual, sin sesión.
    $this->get('/')->assertOk();

    $this->get(route('filament.admin.auth.login'))->assertOk();
});

it('candado: el grupo web declara el control de sesión', function () {
    $middlewareDeWeb = app('router')->getMiddlewareGroups()['web'] ?? [];

    expect($middlewareDeWeb)->toContain('auth.session');
});

it('candado: ninguna ruta autenticada de web queda sin el control de sesión', function () {
    // «gatherMiddleware()» devuelve los NOMBRES de grupo sin expandir, así que
    // «auth.session» no aparece ahí aunque el grupo «web» lo lleve: hay que
    // resolver los grupos o el candado no mide nada.
    $grupos = app('router')->getMiddlewareGroups();

    $expandir = static function (array $middleware) use ($grupos): array {
        $resuelto = [];

        foreach ($middleware as $nombre) {
            $resuelto = array_merge($resuelto, $grupos[$nombre] ?? [$nombre]);
        }

        return $resuelto;
    };

    $sinControl = collect(Route::getRoutes())
        ->filter(fn ($ruta): bool => in_array('auth', $ruta->gatherMiddleware(), true))
        ->reject(function ($ruta) use ($expandir): bool {
            $middleware = $expandir($ruta->gatherMiddleware());

            return in_array('auth.session', $middleware, true)
                || in_array(AuthenticateSession::class, $middleware, true);
        })
        ->map(fn ($ruta): string => $ruta->uri())
        ->values()
        ->all();

    expect($sinControl)->toBe([], 'rutas autenticadas sin control de sesión: '.implode(', ', $sinControl));
});
