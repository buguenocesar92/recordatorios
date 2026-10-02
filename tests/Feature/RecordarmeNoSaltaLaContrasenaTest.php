<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * La cookie de «Recordarme» no vale por la contraseña.
 *
 * La caducidad de la sesión medía dos horas; la cookie de recordar, catorce
 * meses. Cuando la sesión vencía, esa cookie volvía a autenticar a la persona
 * sola: como la marca del segundo factor vivía en la sesión perdida, el panel
 * mandaba directo a la pantalla del código —con el correo del último que entró
 * a la vista y un código recién enviado a su casilla— sin haber pedido la
 * contraseña ni una vez.
 *
 * El segundo factor quedaba de único factor: quien tomara ese equipo, o se
 * llevara solo esa cookie, no necesitaba saber la contraseña. Y echaba abajo el
 * corte de las doce horas, que existe justamente para que la sesión olvidada en
 * un equipo compartido no siga viva al día siguiente.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // El rol de administración no se llama igual en todos los sistemas: en
    // feria es «administrador». Se lee de la configuración de Shield en vez de
    // escribirlo, o la prueba da 403 en la mitad del ecosistema.
    $rolAdministrador = config('filament-shield.super_admin.name', 'super_admin');
    Role::findOrCreate($rolAdministrador, 'web');
    config()->set('mfa.enabled', true);

    $this->usuario = User::factory()->create();
    $this->usuario->assignRole($rolAdministrador);

    // El navegador que vuelve cuando la sesión ya venció: sin sesión, con la
    // cookie de recordar todavía guardada.
    $this->usuario->forceFill(['remember_token' => Str::random(60)])->save();
});

/**
 * Dónde vive el panel y su pantalla de acceso.
 *
 * Se le preguntan a Filament en vez de escribirlas: los paneles del ecosistema
 * no se llaman todos «admin» —hay uno «feria» y otro «discapacidad»—, y con el
 * nombre a mano estas pruebas solo servían en la mitad de los sistemas.
 */
function urlDelPanel(): string
{
    return Filament::getCurrentOrDefaultPanel()->getUrl();
}

function urlDeAcceso(): string
{
    return Filament::getCurrentOrDefaultPanel()->getLoginUrl();
}

/** El sobre que el navegador trae de vuelta, tal como lo escribe Laravel. */
function galletaDeRecuerdo(User $usuario): array
{
    return [
        Auth::guard('web')->getRecallerName(),
        $usuario->getAuthIdentifier().'|'.$usuario->getRememberToken().'|'.$usuario->getAuthPassword(),
    ];
}

it('con la sesión vencida, la cookie de recordar no entra al panel', function () {
    [$nombre, $valor] = galletaDeRecuerdo($this->usuario);

    $this->withCookie($nombre, $valor)->get(urlDelPanel())
        ->assertRedirect(urlDeAcceso())
        // Además de no entrar, el navegador se queda sin la cookie: si volviera
        // a traerla, bastaría con que la primera petición cayera en una ruta de
        // fuera del panel para que la sesión se abriera sola.
        ->assertCookieExpired($nombre);

    expect(Auth::check())->toBeFalse('la cookie de recordar dejó la sesión autenticada');
});

it('con la sesión vencida, tampoco llega a la pantalla del código', function () {
    // Es el paso que se veía: el panel saltaba al segundo factor con el correo
    // puesto. Llegar ahí ya es el defecto, aunque no se complete el código.
    //
    // No se fija a qué pantalla de acceso manda: unos sistemas conservan la
    // suelta y otros solo la del panel, y lo que importa acá es que no se
    // quede donde se veía el correo ajeno.
    [$nombre, $valor] = galletaDeRecuerdo($this->usuario);

    $respuesta = $this->withCookie($nombre, $valor)->get(route('mfa.verify'));

    $respuesta->assertRedirect();
    expect($respuesta->headers->get('Location'))->not->toContain('verificar-mfa');
    expect(Auth::check())->toBeFalse();
});

it('la cookie rechazada queda inservible para siempre', function () {
    // No basta con no dejarla pasar esta vez: si el navegador la sigue
    // guardando, la trae mañana y en la próxima ruta que se le escape al
    // middleware. Se le manda vencida para que la borre.
    //
    // Antes esto se conseguía de otra manera: el panel la rechazaba, cerraba
    // la sesión y eso rotaba el testigo. Ya no llega a rechazarla —la cookie
    // se descarta antes de que el guard la vea— así que los testigos viejos
    // los borra de una vez la migración «olvidar_los_testigos_de_recordarme»,
    // y su prueba está más abajo.
    [$nombre, $valor] = galletaDeRecuerdo($this->usuario);

    $this->withCookie($nombre, $valor)->get(urlDelPanel())
        ->assertCookieExpired($nombre);

    // Y en la visita siguiente, ya sin cookie, nada quedó autenticado.
    $this->get(urlDelPanel())->assertRedirect(urlDeAcceso());
    expect(Auth::check())->toBeFalse();
});

it('quien pone la contraseña sí entra', function () {
    // Control: sin esto, un arreglo que cortara todo pasaría las de arriba y
    // dejaría el panel cerrado para todo el mundo.
    $this->actingAs($this->usuario);
    session()->put('auth.two_factor_verified', true);

    $this->get(urlDelPanel())->assertSuccessful();
});
