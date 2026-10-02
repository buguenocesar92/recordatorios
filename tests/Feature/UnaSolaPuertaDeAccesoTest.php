<?php

use App\Filament\Pages\Auth\Ingresar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Spatie\Permission\Models\Role;

/**
 * El sistema tiene UNA sola puerta de acceso.
 *
 * Tuvo dos durante meses: «/login», una pantalla propia con el fondo
 * institucional, y «/admin/login», el formulario del panel de Filament. Las dos
 * pedían correo y contraseña, y las dos entraban al mismo sitio —«homePath()»
 * devolvía «/admin» pasara lo que pasara, así que la pantalla propia entregaba
 * al panel igual que la del panel—.
 *
 * Dos puertas no es el doble de comodidad: es el doble de superficie que hay que
 * mantener igual de dura. Cada arreglo de seguridad había que escribirlo dos
 * veces, y este ecosistema ya vivió tres veces la deriva de que una de las dos
 * se quedara atrás —el segundo factor sin «addPersistentMiddleware», el panel
 * fuera del grupo «web» sin «CabecerasDeEndurecimiento», el límite de intentos por IP que
 * solo se corrigió en una—. Existía incluso una prueba, «DosPuertasDeAcceso»,
 * cuyo único trabajo era vigilar que no divergieran: el candado que hace falta
 * cuando la decisión de fondo está aplazada.
 *
 * La que sobrevive es la del panel, y no por gusto: el panel de Filament necesita
 * su propia ruta de acceso para funcionar —«Authenticate» y «AcotarVidaDeSesion»
 * mandan ahí a quien no tiene sesión—, así que retirarla obligaría a sustituir el
 * mecanismo de autenticación del panel entero. La pantalla propia, en cambio, no
 * llevaba a ninguna parte que el panel no tuviera ya.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', false);
    config()->set('seguridad.limitar_intentos', true);

    $this->rol = (string) config('filament-shield.super_admin.name', 'super_admin');
    Role::findOrCreate($this->rol, 'web');
});

/**
 * Todas las rutas GET que sirven un formulario de acceso.
 *
 * Se buscan por la forma de la URI y no por el nombre: un formulario nuevo lo
 * puede montar cualquiera, con el nombre que quiera, y lo que hay que detectar
 * es que APAREZCA otra puerta, no que se llame de una manera concreta.
 *
 * @return list<string>
 */
function urisDeAcceso(): array
{
    $uris = [];

    foreach (Route::getRoutes() as $ruta) {
        if (! in_array('GET', $ruta->methods(), true)) {
            continue;
        }

        $uri = $ruta->uri();

        if (preg_match('#(^|/)(login|ingresar|acceso|iniciar-sesion)$#i', $uri) === 1) {
            $uris[] = $uri;
        }
    }

    sort($uris);

    return array_values(array_unique($uris));
}

it('hay un solo formulario de acceso en todo el sistema', function () {
    // Antes del arreglo esto es ROJO y devuelve dos: «admin/login» y «login».
    expect(urisDeAcceso())->toBe(['admin/login']);
});

it('la puerta retirada ya no responde, ni con otro nombre', function () {
    // Quitarle el nombre a la ruta y dejarla montada no cierra nada: lo que hay
    // que comprobar es que la dirección deje de existir de verdad.
    $this->get('/login')->assertNotFound();
    $this->post('/login', ['email' => 'quien@ejemplo.test', 'password' => 'lo-que-sea'])
        ->assertNotFound();
});

it('la raíz es la portada pública y NO anuncia la puerta del panel', function () {
    // «/» es la landing (routes/sitio.php). En este producto el panel es solo
    // por VPN: la página pública no enlaza al ingreso, que solo le mostraría la
    // puerta a quien no debe tocarla. La puerta sigue siendo una sola.
    $this->get('/')->assertOk()->assertDontSee(route('filament.admin.auth.login', [], false), false);
});

it('a quien no ha iniciado sesión se lo manda al formulario que sí existe', function () {
    // «redirectGuestsTo» apuntaba a la puerta retirada. Es el camino que recorre
    // cualquier ruta con «auth» del grupo «web».
    Route::middleware(['web', 'auth'])->get('/prueba-invitado', fn () => 'nunca');

    $this->get('/prueba-invitado')->assertRedirect(route('filament.admin.auth.login'));
});

// ─────────────────────────────────────────────────────────────────────────────
// El comportamiento de la puerta que queda, atacada por donde se la ataca de
// verdad.
//
// «Livewire::test()» monta el componente EN PROCESO: no pasa por
// «/livewire-{hash}/update», que es la ruta por la que viaja cada pulsación del
// botón «Entrar» del panel. Un test que solo llama al método en proceso da verde
// aunque la ruta esté rota o sin middleware. Estas comprobaciones van por el
// endpoint real, con el snapshot que la propia página entrega y firmado con el
// checksum que usa el navegador, para que la petición sea indistinguible de una
// legítima.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Toma el snapshot del formulario tal como lo entrega la página del panel.
 *
 * No se fabrica a mano: el servidor rechaza cualquier snapshot cuyo checksum no
 * cuadre ANTES de ejecutar nada, así que un payload sintético daría verde sin
 * haber probado el formulario.
 *
 * @return array<string, mixed>
 */
function snapshotDelFormularioDeAcceso(): array
{
    $html = (string) test()->get('/admin/login')->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]*)"/', $html, $partes);

    expect($partes)->toHaveCount(2, 'la pantalla de acceso no entregó ningún snapshot de Livewire');

    /** @var array<string, mixed> $snapshot */
    $snapshot = json_decode(html_entity_decode($partes[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);

    return $snapshot;
}

/**
 * Un intento de acceso REAL: la misma petición que manda el navegador al pulsar
 * «Entrar», por «/livewire-{hash}/update».
 *
 * El prefijo lleva un hash derivado de la APP_KEY, así que la ruta literal
 * «/livewire/update» daría 404 siempre y el test pasaría en verde sin haber
 * tocado el formulario. Se le pide la URI al propio Livewire.
 */
function intentarAcceder(string $correo, string $clave, ?string $desde = null): TestResponse
{
    $snapshot = snapshotDelFormularioDeAcceso();

    if ($desde !== null) {
        test()->withServerVariables(['REMOTE_ADDR' => $desde]);
    }

    return test()->withHeaders(['X-Livewire' => 'true'])
        ->postJson(app(HandleRequests::class)->getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => ['data.email' => $correo, 'data.password' => $clave],
                'calls' => [['path' => '', 'method' => 'authenticate', 'params' => []]],
            ]],
        ]);
}

it('un barrido contra una cuenta no deja fuera del sistema al resto de la oficina', function () {
    // En una municipalidad todos salen a internet por la misma dirección. Un
    // límite por IP a secas convierte el ataque contra UNA casilla en una caída
    // del sistema para TODOS: quien insiste contra la alcaldesa deja sin panel a
    // las cien personas que comparten la salida.
    $victima = User::factory()->create(['email' => 'victima@ejemplo.test']);
    $victima->assignRole($this->rol);

    $companera = User::factory()->create([
        'email' => 'companera@ejemplo.test',
        'password' => bcrypt('caballo-correcto-42'),
    ]);
    $companera->assignRole($this->rol);

    // Cinco fallos contra la víctima, todos desde la MISMA dirección.
    foreach (range(1, 5) as $intento) {
        intentarAcceder('victima@ejemplo.test', 'mala', '200.28.14.10');
    }

    expect(Auth::check())->toBeFalse('el barrido no debería haber autenticado a nadie');

    // La compañera, desde esa misma dirección y con su contraseña correcta:
    // tiene que entrar. Lo que se comprueba es el EFECTO —la sesión queda
    // autenticada y con su identidad—, no que la pantalla se pinte de una forma.
    intentarAcceder('companera@ejemplo.test', 'caballo-correcto-42', '200.28.14.10');

    expect(Auth::check())->toBeTrue('el límite por dirección dejó fuera a quien no tenía nada que ver')
        ->and(Auth::id())->toBe($companera->id);
});

it('insistir contra la misma cuenta sí corta, aunque después se acierte la contraseña', function () {
    // La otra mitad: que dejar de castigar a la oficina no puede volver gratis
    // adivinar una contraseña. El sexto intento va con la contraseña CORRECTA a
    // propósito: si entrara, el corte no existiría; si no entra, el corte es el
    // que lo impide y no un error de tecleo.
    $victima = User::factory()->create([
        'email' => 'victima@ejemplo.test',
        'password' => bcrypt('caballo-correcto-42'),
    ]);
    $victima->assignRole($this->rol);

    foreach (range(1, 5) as $intento) {
        intentarAcceder('victima@ejemplo.test', 'mala', '200.28.14.10');
    }

    intentarAcceder('victima@ejemplo.test', 'caballo-correcto-42', '200.28.14.10');

    expect(Auth::check())->toBeFalse('el tope por cuenta no cortó: adivinar una contraseña sale gratis');
});

it('cambiar de dirección de origen no le abre un cupo nuevo al que insiste', function () {
    // El cupo por cuenta no puede llevar la IP en la llave: si la lleva, el
    // barrido más simple —una dirección distinta en cada intento— estrena cinco
    // intentos cada vez y el tope «por cuenta» no frena nada.
    $victima = User::factory()->create([
        'email' => 'victima@ejemplo.test',
        'password' => bcrypt('caballo-correcto-42'),
    ]);
    $victima->assignRole($this->rol);

    foreach (range(1, 5) as $i) {
        intentarAcceder('victima@ejemplo.test', 'mala', "200.28.14.{$i}");
    }

    intentarAcceder('victima@ejemplo.test', 'caballo-correcto-42', '200.28.14.99');

    expect(Auth::check())->toBeFalse('cada dirección nueva estrenó su propio cupo de intentos');

    RateLimiter::clear(Ingresar::llaveDeCuenta('victima@ejemplo.test'));
});

// ─────────────────────────────────────────────────────────────────────────────
// La segunda mitad de la puerta: la recuperación de contraseña.
//
// Al retirar «/login» se dejó fuera del panel la recuperación de contraseña
// —«/olvide-contrasena» y «/restablecer-contrasena»— con su pantalla propia,
// porque el panel no la montaba. Era la misma duplicación con otro nombre: un
// formulario público más, con su propio límite de intentos (solo por IP), sus
// propias vistas y los dos dominios de terceros que la política de contenido
// tenía que autorizar solo por ellas. Este molde se copia a cada sistema
// nuevo: lo que acá sobra, sobra once veces.
//
// Ahora la recupera el panel («->passwordReset()»). No quedan redirecciones:
// esas direcciones nunca fueron públicas y dejarlas vivas mantendría rutas
// fantasma. Es la misma decisión que tomaron web-graneros y rrhh-graneros.
// ─────────────────────────────────────────────────────────────────────────────

it('no queda ninguna ruta de recuperación de contraseña fuera del panel', function () {
    $fantasmas = ['password.request', 'password.email', 'password.reset', 'password.update', 'ingresar', 'dashboard'];

    $vivas = array_values(array_filter($fantasmas, fn (string $n): bool => Route::has($n)));

    expect($vivas)->toBe([], 'siguen vivas rutas del andamio: '.implode(', ', $vivas));
});

it('las direcciones de recuperación del andamio ya no responden', function () {
    // Quitarle el nombre a la ruta y dejarla montada no cierra nada: lo que hay
    // que comprobar es que la dirección deje de existir de verdad, también por
    // POST, que es por donde viajaba el formulario.
    $this->get('/olvide-contrasena')->assertNotFound();
    $this->post('/olvide-contrasena', ['email' => 'quien@ejemplo.test'])->assertNotFound();
    $this->get('/restablecer-contrasena/un-testigo')->assertNotFound();
    $this->post('/restablecer-contrasena', ['token' => 'x', 'email' => 'quien@ejemplo.test'])->assertNotFound();
});

it('el panel ofrece la recuperación de contraseña', function () {
    // Al quitar la del andamio hay que encender la del panel: si no, no queda
    // NINGUNA forma de recuperar una contraseña y el enlace que el propio panel
    // dibuja debajo del formulario se cae con «Route not defined».
    expect(Route::has('filament.admin.auth.password-reset.request'))->toBeTrue(
        'se quitó la recuperación del andamio sin encender la del panel: nadie puede recuperar su contraseña'
    );
    expect(Route::has('filament.admin.auth.password-reset.reset'))->toBeTrue(
        'el panel pide el enlace pero no tiene pantalla donde canjearlo'
    );
});

it('la pantalla de acceso enlaza a la recuperación del panel, no a la retirada', function () {
    $html = (string) $this->get('/admin/login')->assertOk()->getContent();

    expect(str_contains($html, route('filament.admin.auth.password-reset.request')))
        ->toBeTrue('el enlace «¿Olvidaste tu contraseña?» no apunta a la recuperación del panel');
});

it('la pantalla de recuperación del panel se sirve sin sesión', function () {
    $this->get(route('filament.admin.auth.password-reset.request'))->assertOk();
});

it('ninguna vista del sistema pide tipografías ni iconos a un tercero', function () {
    // Las vistas del andamio eran las únicas por las que la política tenía que
    // autorizar Google Fonts y cdnjs. Cada carga le entregaba la IP de un
    // funcionario a dos empresas que no pintan nada acá (Ley 21.719:
    // minimización, y no hay base de licitud para esa cesión).
    $externas = [];

    $vistas = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($vistas as $vista) {
        if (! str_ends_with((string) $vista, '.blade.php')) {
            continue;
        }

        $contenido = (string) file_get_contents((string) $vista);

        foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdnjs.cloudflare.com'] as $dominio) {
            if (str_contains($contenido, $dominio)) {
                $externas[] = basename((string) $vista).' → '.$dominio;
            }
        }
    }

    expect($externas)->toBe([], "vistas que cargan de un tercero:\n".implode("\n", $externas));
});

it('la política de contenido ya no autoriza a los terceros que nadie pide', function () {
    // Que la lista sea corta no es cosmético: cada dominio de la política es
    // permiso para ejecutar o pintar algo servido por un tercero. Se mide en el
    // panel, que es la pantalla que más excepciones tenía.
    //
    // fonts.bunny.net salió de acá el mismo día que el AdminPanelProvider dejó
    // de llamar a ->font('Inter') sin proveedor: esa llamada era lo único que
    // obligaba a esta excepción (SinCdnDeTercerosTest la vigila en detalle).
    $csp = (string) $this->get('/admin/login')->assertOk()->headers->get('Content-Security-Policy');

    foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdnjs.cloudflare.com', 'fonts.bunny.net'] as $dominio) {
        expect(str_contains($csp, $dominio))
            ->toBeFalse("la política sigue autorizando {$dominio}, y ya ninguna vista ni el panel lo piden");
    }
});
