<?php

use App\Http\Middleware\TwoFactorAuthentication;
use App\Livewire\VerificarMfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Livewire\livewire;

/**
 * El segundo factor tiene que cubrir lo que el panel HACE, no solo lo que muestra.
 *
 * Filament es una aplicación de Livewire: abrir una página es una petición normal y sí
 * pasa por el middleware del panel, pero borrar, editar o cambiar un estado viajan por
 * el punto de llamada de Livewire, que Livewire monta por su cuenta y con su propio
 * grupo de middleware. Colgar el segundo factor del panel protege la navegación y deja
 * las acciones al descubierto: quien tenga la contraseña —robada o reutilizada— no ve
 * el panel, pero puede operarlo.
 *
 * Cómo lo resuelve Livewire: al declarar un middleware «persistente», lo reaplica en
 * cada petición de actualización, tomándolo de la ruta que originó el componente. Por
 * eso NO aparece en el middleware del punto de llamada —comprobarlo ahí daría un rojo
 * engañoso—: lo que hay que comprobar es la lista de persistentes y, sobre todo, el
 * comportamiento.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
    config()->set('mfa.enabled', true);
});

it('el segundo factor está declarado como persistente en Livewire', function () {
    $persistentes = app(PersistentMiddleware::class)->getPersistentMiddleware();

    // Ojo con la forma de escribirlo: toContain() admite varios valores y los busca
    // TODOS, así que un segundo argumento pensado como mensaje se convierte en otra
    // cosa que buscar y el test falla por un motivo que no es el suyo.
    expect(in_array(TwoFactorAuthentication::class, $persistentes, true))
        ->toBeTrue(
            'el segundo factor no es persistente: Livewire no lo reaplicará en las '
            .'acciones del panel (borrar, editar, cambiar estado)'
        );
});

it('una sesión sin verificar el segundo factor no llega al panel', function () {
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    // Autenticada con la contraseña pero SIN pasar el segundo factor: es la situación
    // de quien tiene una contraseña robada.
    $this->actingAs($usuario)
        ->get('/admin')
        ->assertRedirect(route('mfa.verify'));
});

it('sin verificar el segundo factor no se obtiene el snapshot para operar', function () {
    // Toda acción de Livewire necesita el snapshot que la página entrega al cargarse.
    // Si la página no se sirve, no hay snapshot válido con el que operar: es la otra
    // mitad de la protección, y conviene dejarla comprobada y no supuesta.
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    $respuesta = $this->actingAs($usuario)->get('/admin');

    expect(str_contains($respuesta->getContent(), 'wire:snapshot'))
        ->toBeFalse('la página entregó un snapshot de Livewire a una sesión sin segundo factor');
});

it('verificado el segundo factor, el panel sigue funcionando', function () {
    // Contraprueba imprescindible: sin ella, un middleware que rechazara TODO dejaría
    // verdes las comprobaciones de arriba y rompería el panel entero.
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    $this->actingAs($usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->get('/admin')
        ->assertSuccessful();
});

// ─────────────────────────────────────────────────────────────────────────────
// La otra mitad: el ENDPOINT de actualización, no la carga de la página.
//
// Todo lo de arriba comprueba la lista de persistentes y lo que devuelve un GET.
// Ninguna de esas comprobaciones pasa por «/livewire-{hash}/update», que es por
// donde viaja de verdad cada acción del panel: borrar un usuario, cambiar un
// estado, confirmar un modal. Un «addPersistentMiddleware» borrado deja todo lo
// anterior en verde —la navegación se sigue protegiendo— y abre las acciones.
//
// Dos cosas que no se pueden hacer «a ojo»:
//
// 1. El endpoint NO es «/livewire/update». Livewire 4 deriva un prefijo con hash
//    a partir de la APP_KEY, así que una petición a la ruta literal daría 404
//    siempre, dijera lo que dijera el segundo factor, y el test pasaría en verde
//    sin haber comprobado nada.
// 2. El snapshot va firmado con el MISMO checksum que usa el navegador. El
//    servidor rechaza cualquier snapshot que no cuadre ANTES de ejecutar el
//    middleware: un payload inventado daría verde por el motivo equivocado.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Una actualización de Livewire real y firmada, como si el componente se
 * hubiera montado en $path.
 *
 * Lo que mira el middleware persistente es «memo.path» —de qué ruta salió el
 * componente—, no qué componente viaja: por eso alcanza con reetiquetar un
 * snapshot legítimo y volver a firmarlo.
 *
 * @param  array<string, mixed>  $snapshotBase
 */
function actualizacionLivewireDesde(array $snapshotBase, string $path): TestResponse
{
    // El middleware persistente se reaplica tomándolo de LA RUTA de la que salió
    // el componente. Si «$path» no resuelve contra ninguna ruta registrada no hay
    // de dónde tomarlo, Livewire no reaplica nada y la petición pasa: los casos
    // que esperan 200 quedarían en verde sin haber ejercitado el segundo factor.
    // Pasa solo con renombrar un recurso del panel, y en silencio, así que se
    // comprueba acá y no se supone.
    try {
        app('router')->getRoutes()->match(Request::create($path, 'GET'));
    } catch (NotFoundHttpException) {
        throw new RuntimeException(
            "«{$path}» no resuelve contra ninguna ruta de este proyecto. Livewire no "
            .'reaplicaría ningún middleware desde ahí y estas comprobaciones darían '
            .'verde sin probar nada: usa una ruta que exista (php artisan route:list).'
        );
    }

    $snapshot = $snapshotBase;
    $snapshot['memo']['path'] = ltrim($path, '/');
    $snapshot['memo']['method'] = 'GET';
    unset($snapshot['checksum']);
    $snapshot['checksum'] = Checksum::generate($snapshot);

    return test()->withHeaders(['X-Livewire' => 'true'])
        ->postJson(app(HandleRequests::class)->getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => [],
                'calls' => [],
            ]],
        ]);
}

/**
 * Una sesión con la contraseña puesta y el segundo factor SIN pasar, más un
 * snapshot con el que operar.
 *
 * El snapshot se toma de la propia pantalla de verificación porque es la única
 * que se sirve en ese estado: cualquier página detrás de «mfa» redirige en su
 * montaje, y una respuesta que redirige no trae snapshot.
 *
 * @return array<string, mixed>
 */
function snapshotDeSesionSinVerificar(): array
{
    $usuario = User::factory()->create();
    $usuario->assignRole('super_admin');

    test()->actingAs($usuario);

    $snapshot = livewire(VerificarMfa::class)->snapshot;

    expect($snapshot)->toBeArray(
        'no se pudo tomar un snapshot: sin él las peticiones de abajo fallarían por un '
        .'motivo distinto del que este archivo prueba'
    );

    return $snapshot;
}

it('sin el segundo factor, una acción real del panel no llega a ejecutarse', function () {
    // Es el caso que importa: quien tiene la contraseña —robada o reutilizada—
    // no ve el panel, pero podía OPERARLO, que es lo que de verdad hace daño.
    $snapshot = snapshotDeSesionSinVerificar();

    actualizacionLivewireDesde($snapshot, '/admin/usuarios')
        ->assertRedirect(route('mfa.verify'));
});

it('verificado el segundo factor, la acción real del panel sí pasa', function () {
    // Contraprueba imprescindible: sin ella, un middleware que rechazara TODO
    // dejaría verde la comprobación de arriba mientras rompe el panel entero.
    $snapshot = snapshotDeSesionSinVerificar();

    session()->put('auth.two_factor_verified', true);

    // 200 y no «cualquier cosa que no sea 302»: un 500 tampoco es 302, y con esa
    // comprobación laxa el test daría verde mientras la petición revienta.
    actualizacionLivewireDesde($snapshot, '/admin/usuarios')->assertOk();
});

it('la propia pantalla de verificación nunca queda bloqueada', function () {
    // No se puede exigir el segundo factor en la pantalla que sirve para
    // completarlo: si esto se bloqueara, no habría forma de entrar al sistema.
    $snapshot = snapshotDeSesionSinVerificar();

    actualizacionLivewireDesde($snapshot, '/verificar-mfa')->assertOk();
});
