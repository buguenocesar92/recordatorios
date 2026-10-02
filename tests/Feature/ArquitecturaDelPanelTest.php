<?php

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\PaginaSinEscudo;
use Tests\Fixtures\WidgetSinEscudo;

/**
 * Las páginas de una lista que no controlan su acceso.
 *
 * @param  array<int, class-string>  $paginas
 * @return array<int, class-string>
 */
function paginasSinEscudo(array $paginas): array
{
    return collect($paginas)
        ->reject(fn (string $pagina): bool => in_array(HasPageShield::class, class_uses_recursive($pagina), true))
        // El escritorio va excluido a propósito: es la pantalla a la que llega
        // todo el mundo al identificarse, y gatearla con un permiso deja a quien
        // no lo tenga sin ningún sitio al que entrar.
        ->reject(fn (string $pagina): bool => is_a($pagina, Dashboard::class, true))
        ->values()
        ->all();
}

/**
 * Los widgets de una lista que no controlan su visibilidad.
 *
 * @param  array<int, class-string>  $widgets
 * @return array<int, class-string>
 */
function widgetsSinEscudo(array $widgets): array
{
    return collect($widgets)
        ->reject(fn (string $widget): bool => in_array(HasWidgetShield::class, class_uses_recursive($widget), true))
        ->values()
        ->all();
}

/**
 * Reglas del panel que no dependen de que alguien se acuerde.
 *
 * Shield genera el permiso y lo muestra como casilla en la pantalla de Roles,
 * pero NO lo aplica solo: sin el trait, la casilla se marca y no hace nada. La
 * auditoría del ecosistema encontró esa falla propagada en cinco sistemas, y la
 * causa siempre fue la misma —alguien agregó una página o un widget y no se
 * acordó del trait—.
 *
 * Estas comprobaciones cierran esa puerta de una vez: un sistema nacido de este
 * scaffold no puede olvidarse, porque la batería se pone roja.
 */
it('toda página del panel controla su acceso', function () {
    $sinTrait = paginasSinEscudo(Filament::getPanel('admin')->getPages());

    expect($sinTrait)->toBeEmpty(
        'Estas páginas no usan HasPageShield, así que su permiso se togglea sin efecto: '.implode(', ', $sinTrait),
    );
});

it('la regla de páginas rechaza una que olvide el trait', function () {
    // Sin esto, la comprobación de arriba pasa en verde solo porque el panel
    // todavía no tiene páginas propias: el día que alguien agregue una sin
    // trait, nadie se enteraría. Acá se demuestra que la regla rechaza.
    expect(paginasSinEscudo([PaginaSinEscudo::class]))->toBe([PaginaSinEscudo::class]);
});

it('todo widget del panel controla su visibilidad', function () {
    $sinTrait = widgetsSinEscudo(Filament::getPanel('admin')->getWidgets());

    expect($sinTrait)->toBeEmpty(
        'Estos widgets no usan HasWidgetShield, así que se muestran siempre ignorando el permiso: '.implode(', ', $sinTrait),
    );
});

it('la regla de widgets rechaza uno que olvide el trait', function () {
    expect(widgetsSinEscudo([WidgetSinEscudo::class]))->toBe([WidgetSinEscudo::class]);
});

it('todo recurso del panel controla quién entra', function () {
    // Filament permite TODO cuando el recurso no tiene policy: no es que
    // deniegue por defecto, es que deja pasar.
    //
    // Valen las dos formas de controlarlo, y la segunda no es un atajo: un
    // recurso que viene de un paquete puede no tener modelo propio sobre el que
    // colgar una policy —el de ARCOP lo explica en su propio código— y resuelve
    // con overrides explícitos de canViewAny() contra sus permisos. Lo que esta
    // regla persigue es el recurso que no comprueba NADA.
    $sinControl = collect(Filament::getPanel('admin')->getResources())
        ->filter(fn (string $recurso): bool => is_subclass_of($recurso, Resource::class))
        ->reject(fn (string $recurso): bool => Gate::getPolicyFor($recurso::getModel()) !== null)
        ->reject(function (string $recurso): bool {
            // ¿Declara el propio recurso su control de acceso, en vez de
            // heredarlo de la clase base de Filament, que dice que sí a todo?
            $metodo = new ReflectionMethod($recurso, 'canViewAny');

            return $metodo->getDeclaringClass()->getName() === $recurso;
        })
        ->values();

    expect($sinControl)->toBeEmpty(
        'Estos recursos no comprueban nada: ni policy, ni canViewAny propio. Filament permite todo sobre ellos: '
        .$sinControl->implode(', '),
    );
});

it('la regla rechaza un recurso que no comprueba nada', function () {
    // Sin esto, la comprobación de arriba pasaría en verde el día que alguien
    // agregue un recurso sin control, que es justo el día que debe fallar.
    $sinNada = new class extends Resource
    {
        protected static ?string $model = User::class;
    };

    $clase = $sinNada::class;

    $heredaTodo = (new ReflectionMethod($clase, 'canViewAny'))
        ->getDeclaringClass()
        ->getName() !== $clase;

    expect($heredaTodo)->toBeTrue('Un recurso sin canViewAny propio hereda el «sí a todo» de Filament');
});
