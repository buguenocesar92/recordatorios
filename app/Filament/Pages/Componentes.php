<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

/**
 * Muestrario de los componentes que este proyecto ya tiene instalados.
 *
 * Existe por un problema concreto y medible: el paquete de interfaz del
 * ecosistema trae más de cincuenta componentes —gráficos, estados vacíos,
 * líneas de tiempo, pasos, anillos de progreso— y este proyecto no usaba
 * ninguno. Nadie los escondió: sencillamente no hay forma de enterarse de que
 * existen sin leer el `vendor/`.
 *
 * El costo de no saberlo no es teórico. Al agregar el gráfico del escritorio se
 * escribió uno a mano existiendo `x-muni::chart-bar`, y solo se descubrió
 * después. Con veinte sistemas naciendo de este scaffold, ese trabajo repetido
 * se multiplica, y cada copia se ve un poco distinta.
 *
 * SOLO en desarrollo. En un panel municipal en producción esto sería ruido para
 * quien atiende público, y una pantalla más que mantener y proteger sin que
 * ningún vecino se beneficie.
 */
class Componentes extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.componentes';

    protected static ?string $title = 'Componentes disponibles';

    protected static ?string $navigationLabel = 'Componentes';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 98;

    /**
     * Fuera de desarrollo y de pruebas no existe.
     *
     * «testing» va incluido porque ahí corre la batería de accesibilidad del CI
     * (el job E2E levanta la aplicación con APP_ENV=testing). Solo con «local»
     * la pantalla devolvía un 403 en CI y la prueba de contraste en oscuro medía
     * la página de error de Laravel en vez del muestrario: el único lugar donde
     * los componentes del paquete se revisaban de verdad era el portátil de
     * quien los abría a mano.
     *
     * Se combina con el permiso de Shield en vez de reemplazarlo: el entorno
     * decide si la pantalla tiene sentido, y el permiso sigue decidiendo quién
     * entra. Sustituir el `canAccess()` del trait por esta condición dejaría la
     * casilla de Roles sin efecto, que es el defecto que este proyecto persigue
     * en todas partes.
     */
    public static function canAccess(): bool
    {
        return app()->environment(['local', 'testing']) && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
