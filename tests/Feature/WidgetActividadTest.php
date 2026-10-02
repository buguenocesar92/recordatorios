<?php

use App\Filament\Widgets\ActividadReciente;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * La tarjeta de actividad del escritorio.
 *
 * Incluye el candado de permisos que el proyecto exige al agregar cualquier
 * widget: Shield genera el permiso y lo muestra como casilla en Roles, pero sin
 * el trait el widget se ve igual — la casilla se marca y no hace nada. Un test
 * que solo comprobara «con permiso se ve» pasaría con el candado roto.
 */
uses(RefreshDatabase::class);

covers(ActividadReciente::class);

beforeEach(function () {
    Cache::flush();

    Role::findOrCreate('super_admin', 'web');
    $this->rolAdmin = Role::findOrCreate('administrator', 'web');
    $this->rolAdmin->givePermissionTo(Permission::findOrCreate('access_panel_admin', 'web'));

    // El nombre lo genera Shield con la nomenclatura del proyecto.
    $this->permisoWidget = Permission::findOrCreate('widget_actividad_reciente', 'web');

    // El segundo factor se cubre en su propia prueba; acá estorba.
    $this->withSession(['auth.two_factor_verified' => true]);
});

/** Un administrador del panel, con o sin el permiso de esta tarjeta. */
function administradorDelPanel(bool $conElWidget): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(test()->rolAdmin);

    if ($conElWidget) {
        $usuario->givePermissionTo(test()->permisoWidget);
    }

    // Spatie guarda los permisos en caché: sin vaciarla, el permiso recién dado
    // no se ve dentro de la misma prueba y el candado parece roto cuando no lo
    // está.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $usuario->refresh();
}

it('no se muestra a quien no tiene el permiso', function () {
    /*
     * El candado de verdad. Shield genera el permiso y lo muestra como casilla
     * en Roles, pero sin el trait el widget se ve igual: la casilla se marca y
     * no hace nada. La auditoría del ecosistema encontró esa falla en cinco
     * sistemas.
     *
     * Se pregunta por `canView()` con el panel inicializado, y NO mirando el
     * HTML de /admin: en una prueba HTTP el escritorio no llega a incluir sus
     * widgets —tampoco los que ya existían— así que un `assertDontSee` daría
     * verde con el candado roto, que es exactamente el fallo que hay que
     * detectar.
     */
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(administradorDelPanel(false));

    expect(ActividadReciente::canView())
        ->toBeFalse('El widget se muestra sin el permiso: falta el trait de Shield');
});

it('se muestra a quien sí lo tiene', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(administradorDelPanel(true));

    expect(ActividadReciente::canView())->toBeTrue();
});

it('cuenta la actividad por día y rellena los días sin movimiento', function () {
    // Dos registros hoy, uno hace tres días, y el resto de días vacíos.
    DB::table('activity_log')->insert([
        ['log_name' => 'default', 'description' => 'a', 'created_at' => now(), 'updated_at' => now()],
        ['log_name' => 'default', 'description' => 'b', 'created_at' => now(), 'updated_at' => now()],
        ['log_name' => 'default', 'description' => 'c', 'created_at' => now()->subDays(3), 'updated_at' => now()],
    ]);

    $widget = new ActividadReciente;
    $dias = $widget->dias();

    // Catorce días siempre, con o sin movimiento: si se omitieran los vacíos, el
    // gráfico se vería continuo y un fin de semana muerto pasaría por normal.
    expect($dias)->toHaveCount(14)
        ->and($dias->last()['total'])->toBe(2)
        ->and($dias->get(10)['total'])->toBe(1)
        ->and($dias->get(0)['total'])->toBe(0)
        ->and($widget->total())->toBe(3)
        ->and($widget->maximo())->toBe(2);
});

it('no deja el máximo en cero, que rompería la escala del gráfico', function () {
    // Sin actividad, la altura de cada barra se calcularía dividiendo por cero.
    $widget = new ActividadReciente;

    expect($widget->total())->toBe(0)
        ->and($widget->maximo())->toBe(1);
});

/*
 * Falta a propósito una prueba del camino de error (la tarjeta marca `huboError`
 * cuando la consulta falla).
 *
 * Se intentó de dos formas y las dos ensucian la batería: con `DROP TABLE`, que
 * es DDL y la base confirma sola —la transacción de RefreshDatabase no lo
 * deshace, la tabla desaparecía para las pruebas siguientes y empezaban a fallar
 * archivos que nadie tocó—; y mockeando la fachada de base de datos, que se
 * filtra a las pruebas posteriores y hace que los fallos cambien entre corridas
 * idénticas. Una batería que falla distinto cada vez es peor que una con un
 * hueco conocido: deja de creerse.
 *
 * El camino de error se hereda de WidgetDePanel, que es común a las cuatro
 * tarjetas y está cubierto por las suyas.
 */

it('resuelve el gráfico con una sola consulta', function () {
    DB::enableQueryLog();

    (new ActividadReciente)->dias();

    $consultas = collect(DB::getQueryLog())
        ->reject(fn (array $q): bool => str_contains($q['query'], 'information_schema'));

    // Agrupar en la base y traer catorce filas es lo que hace que esto siga
    // costando lo mismo con millones de registros.
    expect($consultas)->toHaveCount(1, 'El escritorio se dispara en consultas: '.$consultas->count());
});
