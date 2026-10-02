<?php

/*
|--------------------------------------------------------------------------
| Servicios de terceros
|--------------------------------------------------------------------------
|
| Este archivo no existía en el proyecto, solo en el esqueleto del framework, así
| que las claves propias del ecosistema no tenían dónde vivir: «config()» las
| devolvía nulas sin avisar y el maestro de personas quedaba desconectado
| pareciendo configurado —el mismo defecto silencioso de un config ausente que ya
| mordió a otros repos hermanos—.
|
| Solo se declara lo propio. Los bloques del framework (postmark, resend, ses,
| slack) siguen llegando de su configuración base, que Laravel fusiona con esta;
| repetirlos acá obligaría a documentar en el ejemplo de entorno variables que
| este sitio no usa.
|
*/

return [

    /*
    | Maestro de personas del ecosistema.
    |
    | «driver» en «local» —lo normal en este sitio— significa que no se habla con
    | el maestro: nada sale de acá. Solo en «http» se empuja, y aun así viaja
    | únicamente quien tiene documento acreditado (ver el Job
    | «SincronizarPersonaAlMaestro»): al padrón central del ecosistema no puede
    | entrar alguien por haber dejado su nombre en un formulario público.
    */
    'personas_api' => [
        'driver' => env('PERSONA_RESOLVER', 'local'),
        'url' => env('PERSONAS_API_URL', 'http://personas-api:8000'),
        'token' => env('PERSONAS_API_TOKEN', env('SERVICIOS_API_TOKEN', '')),
        'sistema' => env('PERSONA_RESOLVER_SISTEMA', 'web'),
        'timeout' => 5,
    ],

];
