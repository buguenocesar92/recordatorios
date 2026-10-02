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
    | Fuera de producción, sin variables, se usan las claves de PRUEBA que
    | Cloudflare publica (siempre pasan; no son secretos). En producción no hay
    | respaldo: vacías o de prueba, el arranque se niega
    | (App\Support\CredencialesDePlantilla).
    */
    'turnstile' => [
        'sitekey' => env('TURNSTILE_SITEKEY') ?: (env('APP_ENV') === 'production' ? '' : '1x00000000000000000000AA'),
        'secret' => env('TURNSTILE_SECRET') ?: (env('APP_ENV') === 'production' ? '' : '1x0000000000000000000000000000000AA'),
        'hostname' => env('TURNSTILE_HOSTNAME', ''),
        'url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => 5,
    ],

];
