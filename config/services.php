<?php

/*
|--------------------------------------------------------------------------
| Servicios de terceros
|--------------------------------------------------------------------------
|
| Solo se declara lo propio. Los bloques del framework (postmark, resend, ses,
| slack) siguen llegando de su configuración base, que Laravel fusiona con esta;
| repetirlos acá obligaría a documentar en el ejemplo de entorno variables que
| este sitio no usa.
|
*/

return [

    /*
    | Cloudflare Turnstile: anti-bots de la lista de espera (spec §4, §10).
    |
    | SOLO en `local` y `testing`, sin variables, se usan las claves de PRUEBA que
    | Cloudflare publica (siempre pasan; no son secretos). En cualquier otro
    | entorno (producción, staging, un APP_ENV mal escrito) no hay respaldo:
    | vacías o de prueba, el arranque se niega
    | (App\Support\CredencialesDePlantilla) y la regla Turnstile falla cerrada.
    */
    'turnstile' => [
        'sitekey' => env('TURNSTILE_SITEKEY') ?: (in_array(env('APP_ENV'), ['local', 'testing'], true) ? '1x00000000000000000000AA' : ''),
        'secret' => env('TURNSTILE_SECRET') ?: (in_array(env('APP_ENV'), ['local', 'testing'], true) ? '1x0000000000000000000000000000000AA' : ''),
        'hostname' => env('TURNSTILE_HOSTNAME', ''),
        'url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'connect_timeout' => 2,
        'timeout' => 5,
    ],

];
