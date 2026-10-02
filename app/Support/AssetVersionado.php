<?php

declare(strict_types=1);

namespace App\Support;

/**
 * La dirección de un archivo estático, con la huella de su contenido.
 *
 * Los estáticos se sirven con caché de un día —así el visitante no se los baja
 * en cada visita—, y ahí está la trampa: mientras esa caché siga vigente el
 * navegador NO pregunta si el archivo cambió. Un arreglo de estilo del panel
 * llegaba, en el mejor de los casos, al día siguiente; a quien tuviera la
 * pestaña abierta, nunca. Se descubrió midiendo: la prueba de móvil seguía
 * viendo el estilo viejo después de arreglarlo.
 *
 * Con la huella en la dirección, un archivo distinto es una dirección distinta:
 * el cambio se ve al recargar, y la copia vieja puede seguir cacheada sin
 * riesgo. Es lo mismo que hace Vite con lo que compila; esto cubre lo que no
 * pasa por Vite.
 *
 * La huella se calcula una vez por proceso: con Octane la aplicación vive entre
 * peticiones, así que no se toca el disco en cada carga de página.
 */
final class AssetVersionado
{
    /** @var array<string, string> */
    private static array $recordadas = [];

    public static function url(string $ruta): string
    {
        $ruta = ltrim($ruta, '/');

        if (! isset(self::$recordadas[$ruta])) {
            $archivo = public_path($ruta);

            // Si el archivo no está —todavía no se publicó, o cambió de ruta—
            // se devuelve la dirección tal cual en vez de fallar: una hoja de
            // estilos que no carga es un panel feo, no un panel caído.
            self::$recordadas[$ruta] = is_file($archivo)
                ? substr((string) md5_file($archivo), 0, 8)
                : '';
        }

        $huella = self::$recordadas[$ruta];

        return asset($ruta).($huella !== '' ? '?v='.$huella : '');
    }
}
