<?php

/*
|--------------------------------------------------------------------------
| Panel del dueño: solo por VPN
|--------------------------------------------------------------------------
|
| Subredes (CIDR) de WireGuard desde las que se acepta el panel, separadas por
| coma en ADMIN_VPN_CIDR. La barrera principal es la allowlist del proxy
| (Caddy/Coolify, tarea 0.8); esto es la segunda cerradura dentro de la app.
|
| Vacía en producción = panel cerrado para todos (falla cerrado). Fuera de
| producción, vacía = sin restricción, para poder desarrollar.
|
*/

return [

    'vpn_cidr' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('ADMIN_VPN_CIDR', '')),
    ))),

];
