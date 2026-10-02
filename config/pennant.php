<?php

/*
|--------------------------------------------------------------------------
| Interruptores de features (laravel/pennant)
|--------------------------------------------------------------------------
|
| Qué feature existe y con qué valor NACE se declara en config/features.php.
| Acá solo se dice dónde se guarda lo que alguien encendió o apagó a mano.
|
| Siempre «database», y sin env() a propósito: el driver «array» vive en
| memoria y solo cambia tocando código o el .env, que es justo lo que un
| interruptor tiene que evitar. Con la base, apagar una feature en producción
| es un comando (`php artisan feature <nombre> apagada`) y surte efecto en la
| petición siguiente: Pennant vacía su caché en memoria en cada RequestReceived
| de Octane (PennantServiceProvider::listenForEvents), así que ningún worker se
| queda con el valor viejo. InterruptoresDeFeaturesTest lo comprueba.
|
*/

return [

    'default' => 'database',

    'stores' => [

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'features',
        ],

    ],
];
