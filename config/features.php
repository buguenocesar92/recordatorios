<?php

/*
|--------------------------------------------------------------------------
| Interruptores de features
|--------------------------------------------------------------------------
|
| Cada entrada es una feature y el valor con el que NACE: lo que rige mientras
| nadie la haya encendido ni apagado a mano. Encender o apagar en producción
| NO pasa por este archivo ni por el .env —eso sería un redeploy con otro
| nombre—: pasa por la base de datos, con un comando que surte efecto en la
| petición siguiente:
|
|   php artisan feature asistente-ia apagada      # apaga para todo el sistema
|   php artisan feature asistente-ia encendida    # enciende para todo el sistema
|   php artisan feature asistente-ia por-defecto  # borra la fila y vuelve acá
|
| Son interruptores del SISTEMA (scope global), no experimentos por usuario:
| FeaturesServiceProvider fija el scope nulo por defecto. Un sistema que quiera
| despliegue gradual usa Feature::for($usuario) explícito para esa feature.
|
| En el código: `Feature::active('asistente-ia')` o `@feature('asistente-ia')`.
| Una feature que se consulta y no está declarada acá devuelve false y deja un
| aviso en el registro; el comando la rechaza antes de crear una fila fantasma.
|
*/

return [

    // Asistente con modelo de lenguaje sobre los datos del panel. Apagado
    // hasta que cada sistema decida su base de licitud y su proveedor.
    'asistente-ia' => false,

];
