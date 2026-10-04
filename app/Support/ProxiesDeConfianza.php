<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Qué intermediarios pueden declarar el origen de una petición y qué cabeceras
 * se les creen. Por omisión, ninguno: falla cerrado.
 *
 * Confiar en los rangos privados abría /admin: con el userland-proxy de Docker
 * lo que entra por el puerto publicado llega desde la puerta de enlace del
 * bridge (172.x.0.1), que es RFC1918, y cualquiera de Internet mandaba
 * «X-Forwarded-For: <IP de la VPN>» para que el panel le creyera.
 *
 * De un proxy de confianza se aceptan SOLO `X-Forwarded-For` (la IP real, para
 * los límites) y `X-Forwarded-Proto` (detectar HTTPS). `X-Forwarded-Host`,
 * `-Port`, `-Prefix` y la de AWS no: el host lo decide `APP_URL`, y un host
 * reenviado sin filtrar envenenaba la caché pública del robots.txt.
 *
 * En producción TRUSTED_PROXIES es obligatorio y acotado (lo exige
 * CredencialesDePlantilla al arrancar).
 */
final class ProxiesDeConfianza
{
    /** Prefijo mínimo aceptado en producción: más ancho ya no es «un proxy». */
    private const int PREFIJO_MINIMO_IPV4 = 16;

    private const int PREFIJO_MINIMO_IPV6 = 64;

    public const int CABECERAS = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;

    /** @return list<string> */
    public static function desde(string $valor): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $valor)), fn (string $v): bool => $v !== ''));
    }

    /**
     * Aplica la lista. Además de `TrustProxies::at()`, fija
     * `trustedproxy.proxies`: con la lista vacía, Laravel cae a confiar en
     * TODOS si el Host —que lo escribe el cliente— termina en .on-forge.com u
     * .on-vapor.com. Con un arreglo vacío en esa clave no hay caída.
     *
     * @param  list<string>  $lista
     */
    public static function aplicar(array $lista): void
    {
        config(['trustedproxy.proxies' => $lista]);
        TrustProxies::at($lista);
        TrustProxies::withHeaders(self::CABECERAS);
    }

    /**
     * Si todas las entradas son IPs exactas o subredes acotadas (≥ /16 en IPv4,
     * ≥ /64 en IPv6). Rechaza el comodín, «REMOTE_ADDR», rangos anchos y basura.
     *
     * @param  list<string>  $lista
     */
    public static function acotadas(array $lista): bool
    {
        foreach ($lista as $entrada) {
            if (! self::esAcotada($entrada)) {
                return false;
            }
        }

        return true;
    }

    private static function esAcotada(string $entrada): bool
    {
        [$ip, $prefijo] = array_pad(explode('/', $entrada, 2), 2, null);

        $esV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $esV6 = ! $esV4 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (! $esV4 && ! $esV6) {
            return false;
        }

        if ($prefijo === null) {
            return true;
        }

        if (! ctype_digit($prefijo)) {
            return false;
        }

        $maximo = $esV4 ? 32 : 128;
        $minimo = $esV4 ? self::PREFIJO_MINIMO_IPV4 : self::PREFIJO_MINIMO_IPV6;

        return (int) $prefijo >= $minimo && (int) $prefijo <= $maximo;
    }
}
