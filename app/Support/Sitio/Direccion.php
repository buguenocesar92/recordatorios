<?php

declare(strict_types=1);

namespace App\Support\Sitio;

use Illuminate\Http\Request;

/**
 * Direcciones públicas del sitio, siempre desde `app.url`.
 *
 * `url()->current()` y `$request->getHost()` reflejan cómo LLEGÓ la petición:
 * detrás de un proxy o de una caché, eso puede ser una IP interna o un dominio
 * de pruebas, y lo que se escriba en un canonical o en un sitemap se queda ahí
 * para todos los visitantes siguientes. `app.url` es la dirección que la
 * institución decidió publicar; es la única que un buscador debe ver.
 */
final class Direccion
{
    public static function canonica(Request $peticion): string
    {
        return self::absoluta($peticion->path());
    }

    public static function absoluta(string $ruta): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $ruta = ltrim($ruta, '/');

        return $base.'/'.$ruta;
    }
}
