<?php

declare(strict_types=1);

namespace App\Waitlist;

/**
 * La «dirección» que cuentan los límites por IP. En IPv4, la IP tal cual; en
 * IPv6, su /64: es lo que un proveedor le asigna a UN cliente, y contar por
 * dirección exacta le regalaba 2^64 cubetas a cualquiera con IPv6.
 */
final class ClaveDeOrigen
{
    public static function de(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $binaria = (string) inet_pton($ip);
        $red = substr($binaria, 0, 8).str_repeat("\0", 8);

        return inet_ntop($red).'/64';
    }
}
