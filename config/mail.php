<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Correo saliente
|--------------------------------------------------------------------------
|
| El sistema manda correo a sus propios usuarios: el código del segundo factor,
| el restablecimiento de contraseña y los avisos del panel. No manda correo
| masivo ni a direcciones de fuera, así que basta el relay de la organización.
|
| Dos cosas que se rompen en silencio si se ignoran:
|
|   1. El remitente (MAIL_FROM_ADDRESS) tiene que ser una dirección del dominio
|      que el relay acepta; si no, el servidor rechaza el envío sin explicar.
|   2. Sin MAIL_HOST configurado, el código del segundo factor no llega y el
|      panel queda cerrado para todos.
|
| Los valores de acá son marcadores: cada sistema pone los suyos en el .env.
*/

return [

    // Con qué se manda. En producción: 'smtp'. En desarrollo 'log' escribe los
    // correos en storage/logs en vez de enviarlos.
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',

            // Vacío = STARTTLS (se conecta en claro y sube a TLS). Es lo que
            // corresponde en el puerto 25 y en el 587. 'smtps' es para el 465.
            'scheme' => env('MAIL_SCHEME'),

            // El servidor de correo del dominio municipal. Es el mismo nombre
            // que aparece en el registro MX del dominio.
            'host' => env('MAIL_HOST', '127.0.0.1'),

            // 25 para entrega directa; 587 con credenciales.
            'port' => env('MAIL_PORT', 25),

            // Vacíos en entrega directa. Llenarlos cambia el modo a autenticado.
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),

            // Con cuánto se presenta el servidor al conectarse. Microsoft lo
            // revisa: si acá va el nombre interno del contenedor, la conexión
            // queda mal vista y el correo puede terminar en no deseados.
            'local_domain' => env('MAIL_EHLO_DOMAIN'),

            // Sin límite el envío puede colgarse esperando a un servidor que no
            // responde. Va en cola, así que nadie mira la pantalla, pero un
            // trabajador colgado tampoco procesa los códigos que vienen detrás.
            'timeout' => 15,
        ],

        // El bloque 'graph' lo aporta laravel-muni-shared, que lo fusiona en
        // mail.mailers.graph desde su propio archivo de configuración. Definirlo
        // acá también dejaría dos fuentes para lo mismo, y la de este repo
        // ganaría en silencio.

        // Escribe los correos en el log en vez de enviarlos. Para desarrollar.
        // La pantalla de Salud del sistema avisa en rojo si queda puesto en
        // producción: ahí cada código de acceso quedaría guardado en un archivo.
        'log' => [
            'transport' => 'log',
        ],

        // Los retiene en memoria sin enviarlos. Lo usan las pruebas.
        'array' => [
            'transport' => 'array',
        ],

    ],

    /*
    | El remitente. Tiene que ser una dirección del dominio municipal: con
    | entrega directa, Microsoft rechaza el correo si el remitente es de otro
    | dominio. Además es la dirección que ve el funcionario, así que conviene
    | que se entienda de dónde viene.
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.test'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Sistema')),
    ],

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
