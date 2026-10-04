<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Si esta petición llega por un dominio que se deja indexar.
 *
 * Vive aparte porque lo preguntan dos: la cabecera de las respuestas y el
 * «robots.txt». Con la comprobación repetida en cada uno sería cuestión de
 * tiempo que dijeran cosas distintas, y el desacuerdo se descubriría en Google.
 *
 * El dominio canónico es el de APP_URL, no el que trae la petición: el
 * robots.txt sale con caché pública, y un host decidido por el cliente (o por
 * un X-Forwarded-Host reenviado) servía para dejar cacheado un «Disallow: /»
 * en el dominio real. Se indexa solo si el host de la petición ES el de
 * APP_URL y ese host está en la lista.
 */
final class Indexacion
{
    public static function permitida(Request $peticion): bool
    {
        /** @var list<string> $permitidos */
        $permitidos = config('sitio.dominios_indexables', []);

        $canonico = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $canonico !== ''
            && strtolower($peticion->getHost()) === $canonico
            && in_array($canonico, $permitidos, true);
    }
}
