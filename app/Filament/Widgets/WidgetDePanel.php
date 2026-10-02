<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

/**
 * Lo común a las tarjetas del escritorio.
 *
 * Dos decisiones que valen para todas y por eso viven acá:
 *
 * - **Sin carga diferida.** El hueco de carga existe para widgets caros. Para
 *   los del escritorio costaba una petición extra por visita y un salto al
 *   sustituir el hueco por el contenido: 0,14 de CLS medido en un widget y 0,27
 *   en otro. Se renderizan con la página.
 * - **Un error no se lleva la página.** El escritorio es lo primero que se ve al
 *   entrar: si una consulta falla, la tarjeta lo dice y ofrece reintentar.
 */
abstract class WidgetDePanel extends Widget
{
    // Sin este trait, Shield genera el permiso y lo muestra como casilla en la
    // pantalla de Roles, pero el widget se muestra igual: la casilla se marca y
    // no hace nada. La auditoría del ecosistema encontró esa falla propagada en
    // cinco sistemas. Va en la clase base para que ningún widget del paquete
    // pueda olvidarlo.
    use HasWidgetShield;

    /**
     * Cada widget pide SU permiso.
     *
     * El trait de Shield guarda el permiso resuelto en una propiedad estática.
     * Como el trait se aplica en esta clase base y no en cada widget, esa
     * propiedad es una sola para todas las subclases: el primer widget que se
     * evaluaba fijaba el permiso, y a partir de ahí los demás pedían el suyo.
     *
     * El efecto era que el permiso por widget no servía de nada — quien tenía el
     * del primero los veía todos, y quien no lo tenía no veía ninguno aunque
     * tuviera los propios. No daba ningún error: simplemente el escritorio
     * mostraba lo que no correspondía.
     *
     * Se resuelve en cada llamada. Es una búsqueda en un array ya construido en
     * memoria; el ahorro de guardarlo no compensa que el control de acceso sea
     * incorrecto.
     */
    protected static function getWidgetPermission(): ?string
    {
        $widget = FilamentShield::getWidgets()[static::class] ?? null;

        return $widget ? array_key_first($widget['permissions']) : null;
    }

    protected static bool $isLazy = false;

    /**
     * Cuánto ocupa cada tarjeta.
     *
     * El escritorio reparte en seis columnas justamente para que no todas
     * ocupen el ancho completo: apiladas a lo ancho, tres tarjetas ya obligan a
     * bajar la vista, y el escritorio se mira cincuenta veces al día. Cada
     * widget declara el suyo; este es solo el que rige si nadie dice nada.
     */
    protected int|string|array $columnSpan = 'full';

    /** Se encendió algo que no se pudo leer. */
    public bool $huboError = false;

    /** Volver a intentarlo sin recargar la página. */
    public function reintentar(): void
    {
        $this->huboError = false;

        $this->olvidarLoCalculado();
    }

    abstract protected function olvidarLoCalculado(): void;
}
