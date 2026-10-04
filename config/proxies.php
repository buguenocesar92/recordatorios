<?php

use App\Support\ProxiesDeConfianza;

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Direcciones desde las que se acepta lo que digan las cabeceras
    | «X-Forwarded-For» y «X-Forwarded-Proto» (las únicas que se creen; ver
    | App\Support\ProxiesDeConfianza). Esas cabeceras las escribe quien quiera,
    | así que solo valen si el único que puede ponerlas es un intermediario
    | propio.
    |
    | Por omisión NO se confía en nadie (falla cerrado). Antes se confiaba en
    | los rangos privados y eso abría /admin: con el userland-proxy de Docker
    | todo lo que entra por un puerto publicado llega desde la puerta de enlace
    | del bridge (172.x.0.1), que también es privada, y bastaba mandar
    | «X-Forwarded-For: <IP de la VPN>».
    |
    | En producción TRUSTED_PROXIES es OBLIGATORIO: la IP (o subred ≥ /16) del
    | Caddy en la red de Docker, y Caddy tiene que SOBREESCRIBIR
    | X-Forwarded-For, no añadirle. El arranque rechaza la lista vacía, «*»,
    | 0.0.0.0/0, ::/0, REMOTE_ADDR y rangos anchos (CredencialesDePlantilla).
    |
    */

    'confiables' => ProxiesDeConfianza::desde((string) env('TRUSTED_PROXIES', '')),

];
