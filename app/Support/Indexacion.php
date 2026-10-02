<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Si esta petición llega por un dominio que se deja indexar.
 *
 * Vive aparte porque lo preguntan dos: la cabecera de las respuestas y, el día
 * que exista, el «robots.txt». Con la comprobación repetida en cada uno sería
 * cuestión de tiempo que dijeran cosas distintas, y el desacuerdo se
 * descubriría en Google.
 */
final class Indexacion
{
    public static function permitida(Request $peticion): bool
    {
        /** @var list<string> $permitidos */
        $permitidos = config('sitio.dominios_indexables', []);

        return in_array(strtolower($peticion->getHost()), $permitidos, true);
    }
}
