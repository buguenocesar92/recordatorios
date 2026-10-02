<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Direcciones desde las que se acepta lo que digan las cabeceras
    | «X-Forwarded-*». Importa más de lo que parece: esas cabeceras las escribe
    | quien quiera, así que solo valen si el único que puede ponerlas es un
    | intermediario propio.
    |
    | Confiando en todos («*»), cualquiera declara la dirección de origen que se
    | le antoje. Eso no rompe solo los registros: el conteo de intentos de
    | acceso y cualquier límite por IP se evaden cambiando una cabecera.
    |
    | Por omisión se confía en los rangos privados, que es donde vive el
    | intermediario en Docker y en el servidor: cubre el caso real —detectar
    | HTTPS detrás del proxy— sin creerle a nadie de fuera. Si el intermediario
    | está en otra parte, se acota con TRUSTED_PROXIES en el .env.
    |
    */

    'confiables' => array_map(
        trim(...),
        explode(',', (string) env(
            'TRUSTED_PROXIES',
            '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'
        ))
    ),

];
