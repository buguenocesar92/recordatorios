<?php

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Facades\Gate;

/**
 * Candado de la convención de permisos del panel. Shield genera el permiso y lo muestra
 * como checkbox en la pantalla de Roles, pero NO lo aplica solo:
 *
 *  - una Page sin HasPageShield sigue apareciendo en la navegación sin permiso,
 *  - un Widget sin HasWidgetShield se muestra SIEMPRE,
 *  - un Resource sin Policy es accesible por completo (Filament permite todo).
 *
 * Estos tests recorren lo que haya registrado en el panel, así que siguen protegiendo
 * a los proyectos generados a partir de este scaffold cuando agreguen los suyos.
 */

/**
 * Las clases que Shield tiene registradas para el panel.
 *
 * Shield 3.x devuelve `['page_Hub' => ['class' => Hub::class, ...]]` —la llave es
 * el permiso— y Shield 4.x devuelve la clase como llave. Se normaliza acá para que
 * este candado sirva igual en los dos, que es como está repartido el ecosistema.
 */
function clasesRegistradas(array $entradas): array
{
    return collect($entradas)
        ->map(fn ($valor, $llave) => is_array($valor) ? ($valor['class'] ?? null) : $llave)
        ->filter()
        ->filter(fn (string $clase): bool => class_exists($clase))
        ->values()
        ->all();
}

beforeEach(function () {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
});

it('toda página del panel aplica su permiso (HasPageShield o navegación gateada)', function () {
    $sinControl = collect(clasesRegistradas(FilamentShield::getPages()))
        ->reject(function (string $page): bool {
            $usa = in_array(HasPageShield::class, class_uses_recursive($page), true);
            // Alternativa válida: lógica propia que además oculta el ítem del menú.
            $propio = (new ReflectionMethod($page, 'canAccess'))->getDeclaringClass()->getName() === $page
                && (new ReflectionMethod($page, 'shouldRegisterNavigation'))->getDeclaringClass()->getName() === $page;

            return $usa || $propio;
        })
        ->values()
        ->all();

    expect($sinControl)->toBe([]);
});

it('todo widget del panel aplica su permiso (HasWidgetShield o canView propio)', function () {
    $sinControl = collect(clasesRegistradas(FilamentShield::getWidgets()))
        ->reject(function (string $widget): bool {
            $usa = in_array(HasWidgetShield::class, class_uses_recursive($widget), true);
            $propio = method_exists($widget, 'canView')
                && (new ReflectionMethod($widget, 'canView'))->getDeclaringClass()->getName() === $widget;

            return $usa || $propio;
        })
        ->values()
        ->all();

    expect($sinControl)->toBe([]);
});

it('todo recurso del panel exige permiso (Policy o gating propio)', function () {
    $sinControl = collect(Filament\Facades\Filament::getPanel('admin')->getResources())
        ->reject(function (string $resource): bool {
            // Camino normal: Policy descubierta para el modelo.
            if (Gate::getPolicyFor($resource::getModel()) !== null) {
                return true;
            }

            // Alternativa válida: el propio recurso declara canViewAny(). Es lo que
            // hace ActivityResource, cuyo modelo vive en el vendor
            // (Spatie\Activitylog\Models\Activity) y por eso NO puede descubrir una
            // policy en App\Policies — ojo: un ActivityPolicy ahí es código muerto.
            return (new ReflectionMethod($resource, 'canViewAny'))->getDeclaringClass()->getName() === $resource;
        })
        ->values()
        ->all();

    expect($sinControl)->toBe([]);
});
