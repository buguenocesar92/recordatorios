<?php

declare(strict_types=1);

namespace App\Waitlist;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * La «dirección» que cuentan los límites por IP:
 *
 * - IPv4: la IP exacta (/32).
 * - IPv4 mapeada en IPv6 (`::ffff:a.b.c.d`): se normaliza a la IPv4. Sin eso,
 *   todos los clientes IPv4 que llegan por un socket de doble pila caerían en
 *   el MISMO /64 (`::/64`) y se bloquearían entre sí.
 * - IPv6: su /64, que es lo que un proveedor le asigna a UN cliente; contar por
 *   dirección exacta le regalaba 2^64 cubetas a cualquiera con IPv6.
 * - Vacía o ilegible: NUNCA una clave constante compartida (una sola cubeta para
 *   todos sería un DoS trivial). Clave aleatoria por petición y aviso al log:
 *   es un error de despliegue (proxy mal configurado), no algo que el cliente
 *   pueda provocar con la IP real del socket.
 *
 * Que detrás de un proxy todos compartan la IP del proxy se evita en otro lado:
 * en producción TRUSTED_PROXIES es obligatorio (CredencialesDePlantilla).
 */
final class ClaveDeOrigen
{
    public static function de(?string $ip): string
    {
        $ip = trim((string) $ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            Log::warning('lista de espera: petición sin IP legible; se cuenta aparte (revisar TRUSTED_PROXIES)');

            return 'sin-ip:'.Str::random(32);
        }

        $binaria = (string) inet_pton($ip);

        // ::ffff:a.b.c.d → a.b.c.d
        if (str_starts_with($binaria, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($binaria, 12));
        }

        return inet_ntop(substr($binaria, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
