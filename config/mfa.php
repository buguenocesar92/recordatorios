<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MFA habilitada (interruptor maestro)
    |--------------------------------------------------------------------------
    |
    | Cuando es false, el middleware `mfa` se omite por completo y nadie ve la
    | pantalla de verificación. Útil para DEMO/testing sin correo ni SMS.
    | En PRODUCCIÓN debe quedar en true.
    |
    */

    'enabled' => env('MFA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Mostrar el código MFA en pantalla
    |--------------------------------------------------------------------------
    |
    | Cuando es true, el código de verificación de dos factores se muestra en
    | la propia pantalla de verificación (y se escribe en los logs). Es útil en
    | DEMO/desarrollo, donde no hay correo ni SMS configurados para entregarlo.
    |
    | En PRODUCCIÓN real debe quedar en false: de lo contrario cualquiera con
    | credenciales válidas vería el código y la MFA no protegería nada.
    |
    */

    'show_code' => env('MFA_SHOW_CODE', false),

];
