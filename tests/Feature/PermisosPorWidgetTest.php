<?php

use App\Filament\Widgets\WidgetDePanel;
use Filament\Facades\Filament;

/**
 * Cada tarjeta del escritorio pide SU permiso, no el de otra.
 *
 * El trait de Shield guarda el permiso resuelto en una propiedad estática. Como
 * se aplica en la clase base y no en cada widget, esa propiedad era una sola
 * para todas las subclases: el primer widget evaluado fijaba el permiso y los
 * demás pedían el suyo. Quien tenía el permiso de esa primera tarjeta las veía
 * todas; quien no, no veía ninguna aunque tuviera las propias.
 *
 * No daba ningún error. El escritorio simplemente mostraba lo que no
 * correspondía, que es la peor forma de fallar en un control de acceso.
 */
it('cada tarjeta del escritorio pide su propio permiso', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $widgets = collect(glob(app_path('Filament/Widgets/*.php')))
        ->map(fn (string $ruta): string => 'App\\Filament\\Widgets\\'.basename($ruta, '.php'))
        ->filter(fn (string $clase): bool => class_exists($clase)
            && is_subclass_of($clase, WidgetDePanel::class));

    expect($widgets)->not->toBeEmpty('No se encontró ningún widget: la prueba no comprobaría nada');

    $permisos = $widgets->mapWithKeys(function (string $clase): array {
        $metodo = new ReflectionMethod($clase, 'getWidgetPermission');

        return [$clase => $metodo->invoke(null)];
    });

    // Ninguno puede quedarse sin permiso: sin él, el trait cae en el acceso por
    // defecto y la casilla de Roles deja de tener efecto.
    foreach ($permisos as $clase => $permiso) {
        expect($permiso)->not->toBeNull(class_basename($clase).' no resuelve ningún permiso');
    }

    // Y sobre todo: no pueden compartirlo. Es lo que estaba roto.
    expect($permisos->unique()->count())->toBe(
        $permisos->count(),
        'Dos tarjetas piden el mismo permiso: '.$permisos->toJson()
    );
});
