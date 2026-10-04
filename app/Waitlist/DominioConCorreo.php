<?php

declare(strict_types=1);

namespace App\Waitlist;

/**
 * Si el dominio de un correo puede recibir correo: tiene MX, o al menos A/AAAA
 * (RFC 5321 §5.1: sin MX se entrega al host). Corre en el trabajo de envío, no
 * en la petición: la consulta DNS es síncrona y sin tope, y en la petición
 * cualquiera la disparaba sin haber pasado Turnstile.
 *
 * No es `final` para poder reemplazarla en las pruebas (sin red).
 */
class DominioConCorreo
{
    public function acepta(string $email): bool
    {
        $dominio = substr((string) strrchr($email, '@'), 1);

        if ($dominio === '') {
            return false;
        }

        // El punto final evita que el resolvedor le pegue el dominio de búsqueda local.
        $fqdn = rtrim($dominio, '.').'.';

        return checkdnsrr($fqdn, 'MX') || checkdnsrr($fqdn, 'A') || checkdnsrr($fqdn, 'AAAA');
    }
}
