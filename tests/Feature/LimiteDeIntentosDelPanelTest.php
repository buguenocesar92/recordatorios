<?php

use App\Filament\Pages\Auth\Ingresar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

/**
 * Cuánto se puede insistir en el formulario del panel.
 *
 * «config/seguridad.php» explica que limitar solo por dirección de origen deja
 * fuera a una municipalidad entera —todos salen a internet por la misma— y que
 * por eso se cuenta por cuenta y por origen a la vez. Eso regía únicamente en
 * «/login»: el formulario de Filament, al que llevan todos los enlaces del
 * panel, contaba solo por dirección y con cinco peticiones se dejaba sin acceso
 * a todo el municipio.
 *
 * El límite viene apagado en la batería (phpunit.xml) porque decenas de accesos
 * desde una sola dirección la bloquearían a sí misma; estos tests lo encienden.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('seguridad.limitar_intentos', true);
    config()->set('mfa.enabled', false);

    $rol = (string) config('filament-shield.super_admin.name', 'super_admin');
    Role::findOrCreate($rol, 'web');

    $this->victima = User::factory()->create(['email' => 'victima@ejemplo.test']);
    $this->victima->assignRole($rol);

    $this->companera = User::factory()->create([
        'email' => 'companera@ejemplo.test',
        'password' => bcrypt('caballo-correcto-42'),
    ]);
    $this->companera->assignRole($rol);
});

afterEach(function () {
    RateLimiter::clear(Ingresar::llaveDelOrigen('127.0.0.1'));

    foreach (range(1, 5) as $i) {
        RateLimiter::clear(Ingresar::llaveDelOrigen("10.0.0.{$i}"));
    }
    RateLimiter::clear(Ingresar::llaveDelOrigen('10.0.0.99'));
});

/** Un intento contra el formulario del panel. */
function intentarEnElPanel(string $correo, string $clave): void
{
    livewire(Ingresar::class)
        ->fillForm(['email' => $correo, 'password' => $clave])
        ->call('authenticate');
}

/**
 * Un intento contra el formulario del panel, simulando otra dirección de
 * origen.
 *
 * No basta con «fillForm()->call('authenticate')»: cada acción de Livewire en
 * la prueba viaja por un cliente HTTP propio de Livewire («RequestBroker»),
 * con su propio REMOTE_ADDR por defecto, así que mutar el singleton de
 * «request()» del test no le llega. Se toma la instancia YA montada —con el
 * formulario ya lleno— y se llama a «authenticate()» como un método PHP
 * normal, en el mismo proceso donde sí vale la mutación de la dirección.
 */
function intentarEnElPanelDesde(string $ip, string $correo, string $clave): void
{
    $componente = livewire(Ingresar::class)
        ->fillForm(['email' => $correo, 'password' => $clave])
        ->instance();

    request()->server->set('REMOTE_ADDR', $ip);

    try {
        $componente->authenticate();
    } catch (ValidationException) {
        // Credenciales inválidas: es el resultado esperado de la mayoría de
        // estos intentos. Lo que se está probando es el candado de intentos,
        // no el mensaje de error.
    }
}

it('insistir contra una cuenta no deja fuera del panel al resto de la oficina', function () {
    // El caso que da nombre al hallazgo: cinco peticiones y la municipalidad
    // entera se queda sin panel, sin explicación y sin haber hecho nada.
    foreach (range(1, 5) as $intento) {
        intentarEnElPanel('victima@ejemplo.test', 'mala');
    }

    intentarEnElPanel('companera@ejemplo.test', 'caballo-correcto-42');

    expect(Auth::check())->toBeTrue('el límite por dirección dejó fuera a una cuenta que no tenía nada que ver');
});

it('sigue cortando al que insiste contra la misma cuenta', function () {
    // La otra mitad: si el arreglo se pasara de permisivo, adivinar una
    // contraseña volvería a salir gratis.
    $this->victima->forceFill(['password' => bcrypt('caballo-correcto-42')])->save();

    foreach (range(1, 5) as $intento) {
        intentarEnElPanel('victima@ejemplo.test', 'mala');
    }

    // La contraseña correcta, ya pasado el tope: no tiene que entrar.
    intentarEnElPanel('victima@ejemplo.test', 'caballo-correcto-42');

    expect(Auth::check())->toBeFalse('el tope por cuenta no cortó');
});

it('también hay un tope por origen para el que prueba cuenta tras cuenta', function () {
    // Contar solo por cuenta deja gratis el barrido: un correo distinto en cada
    // intento y nunca se llega al tope. El tope por origen es holgado a
    // propósito, para que varias personas de la misma oficina se puedan
    // equivocar sin estorbarse.
    config()->set('seguridad.intentos_por_origen', 8);

    foreach (range(1, 9) as $intento) {
        intentarEnElPanel("nadie{$intento}@ejemplo.test", 'mala');
    }

    intentarEnElPanel('companera@ejemplo.test', 'caballo-correcto-42');

    expect(Auth::check())->toBeFalse('el barrido de cuentas desde un mismo origen no se cortó');
});

it('el tope por cuenta corta aunque el atacante cambie de dirección de origen', function () {
    // La llave del tope por cuenta no puede llevar la IP: si la lleva, cada
    // dirección nueva tiene su propio cupo de cinco y el tope «por cuenta» no
    // frena nada distinto del tope por origen.
    $this->victima->forceFill(['password' => bcrypt('caballo-correcto-42')])->save();

    foreach (range(1, 5) as $i) {
        intentarEnElPanelDesde("10.0.0.{$i}", 'victima@ejemplo.test', 'mala');
    }

    // Dirección nueva, la que nunca se usó, y la contraseña correcta: si el
    // tope fuera de verdad por cuenta, esto tampoco tiene que entrar.
    intentarEnElPanelDesde('10.0.0.99', 'victima@ejemplo.test', 'caballo-correcto-42');

    expect(Auth::check())->toBeFalse(
        'el tope por cuenta no cruzó direcciones: cada IP tuvo su propio cupo de intentos',
    );
});

it('los aciertos no cuentan para el tope y no dejan el contador sucio', function () {
    // Arranque de jornada, o reingreso masivo tras vencer la sesión de dos
    // horas: varias personas entran BIEN desde la misma salida a internet. Si
    // los aciertos contaran para el tope por origen, la última se queda fuera
    // sin que nadie se haya equivocado ni una vez.
    config()->set('seguridad.intentos_por_origen', 5);

    $rol = (string) config('filament-shield.super_admin.name', 'super_admin');

    foreach (range(1, 5) as $i) {
        $persona = User::factory()->create([
            'email' => "persona{$i}@ejemplo.test",
            'password' => bcrypt('caballo-correcto-42'),
        ]);
        $persona->assignRole($rol);

        intentarEnElPanel($persona->email, 'caballo-correcto-42');
        Auth::logout();
    }

    $sexta = User::factory()->create([
        'email' => 'sexta@ejemplo.test',
        'password' => bcrypt('caballo-correcto-42'),
    ]);
    $sexta->assignRole($rol);

    intentarEnElPanel('sexta@ejemplo.test', 'caballo-correcto-42');

    expect(Auth::check())->toBeTrue(
        'los aciertos contaron para el tope de origen y dejaron fuera a alguien que no falló nunca',
    );
});

it('con el límite apagado la batería no se bloquea a sí misma', function () {
    // Es lo que permite que las pruebas de extremo a extremo y el grabador de
    // videos entren y salgan decenas de veces.
    config()->set('seguridad.limitar_intentos', false);

    foreach (range(1, 8) as $intento) {
        intentarEnElPanel('victima@ejemplo.test', 'mala');
    }

    intentarEnElPanel('companera@ejemplo.test', 'caballo-correcto-42');

    expect(Auth::check())->toBeTrue('el límite siguió contando con la configuración apagada');
});
