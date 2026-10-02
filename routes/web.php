<?php

use App\Http\Middleware\SoloDesdeLaVpn;
use App\Livewire\VerificarMfa;
use App\Support\Csp\PoliticaDelPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Csp\AddCspHeaders;

/*
|--------------------------------------------------------------------------
| Una sola puerta de acceso
|--------------------------------------------------------------------------
|
| Acá vivieron, en dos tiempos, las dos mitades de una puerta paralela a la del
| panel: primero un «GET/POST /login» propio —pantalla con fondo institucional
| incluida— que entraba al mismo panel dando un rodeo; y, cuando ese se retiró,
| se dejó la recuperación de contraseña («/olvide-contrasena» y
| «/restablecer-contrasena») «porque Filament no la montaba en este panel». Era
| una verdad a medias: el panel la monta con una línea («->passwordReset()»),
| solo que nadie la había encendido.
|
| Dos puertas no dan el doble de comodidad, dan el doble de superficie que
| mantener igual de dura, y este repositorio es el MOLDE del que sale cada
| sistema municipal: lo que acá se duplica, se duplica once veces. La
| recuperación propia limitaba solo por dirección («throttle:6,1»), tenía sus
| propias vistas con su propio CSS, cargaba una librería 3D de 189 kB para el
| fondo, y era lo único por lo que la política de contenido tenía que autorizar
| a Google Fonts y a cdnjs —dos terceros que recibían la IP de cada funcionario
| sin base de licitud para esa cesión (Ley 21.719)—.
|
| La que queda es la del panel («/admin/login», App\Filament\Pages\Auth\Ingresar)
| porque el panel de Filament no funciona sin ella: «Authenticate» y
| «AcotarVidaDeSesion» mandan ahí a quien no tiene sesión. Y la recuperación
| también es la del panel: su pantalla exige un enlace firmado, cuenta los
| intentos por su cuenta y usa la MISMA tipografía y el mismo tema que el resto.
| El correo sigue siendo el institucional: AppServiceProvider reemplaza la
| notificación de fábrica de Filament en el contenedor.
|
| No quedan redirecciones desde las direcciones retiradas, y es deliberado:
| nunca fueron públicas —el panel lo usa personal municipal— y dejarlas vivas
| solo mantendría rutas fantasma que hay que seguir manteniendo. Quien las tenga
| guardadas llega por «/», que desde 2026-09-11 es la portada pública
| (routes/sitio.php) y enlaza al ingreso. Es la misma decisión que tomaron
| web-graneros y rrhh-graneros; «tests/Feature/UnaSolaPuertaDeAccesoTest.php» la
| vigila.
*/

// Salida del sistema fuera del panel: es lo único que puede hacer quien se quedó
// atascado en la pantalla del segundo factor. «TwoFactorAuthentication» la exime
// a propósito; si dejara de existir, esa persona no tendría forma de cerrar la
// sesión sin borrar cookies a mano.
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('filament.admin.auth.login');
})->name('salir');

// MFA Verification
// Lleva «sesion.acotada» y no solo «auth»: es la pantalla a la que el panel
// manda cuando falta el segundo factor, así que sin esto se llegaba igual
// escribiendo la dirección, y era justo donde se veía el correo de quien entró
// la vez pasada.
//
// «sin-cache» porque es una pantalla con sesión: sin ella el navegador puede
// guardarla en disco y restaurarla con «Atrás» después de cerrar sesión, con el
// correo enmascarado y el estado del segundo factor a la vista.
//
// Y lleva la política de contenido DEL PANEL: es un componente Livewire con la
// misma necesidad de 'unsafe-eval' que Filament; con la estricta del sitio nadie
// podría terminar de entrar. Al ir en la ruta, esta cabecera se pone antes que
// la del grupo «web», y spatie respeta la que ya viene puesta.
Route::get('/verificar-mfa', VerificarMfa::class)
    ->middleware([
        SoloDesdeLaVpn::class,
        'auth',
        'sesion.acotada',
        'sin-cache',
        AddCspHeaders::class.':'.PoliticaDelPanel::class,
    ])
    ->name('mfa.verify');
