<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco por omisión
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Discos
    |--------------------------------------------------------------------------
    |
    | Este archivo existe por una razón concreta: Laravel trae por omisión
    | «'url' => env('APP_URL').'/storage'», que le pega el host a la dirección
    | de cada archivo subido. Con APP_URL en «http://localhost:8011», una foto
    | del panel sale como «http://localhost:8011/storage/uploads/...», y quien
    | abra el panel desde otro equipo de la red —por la IP— le pide esa foto a
    | SU propia máquina. Ahí no hay nada: marcos vacíos, o un 403 de lo que sea
    | que tenga corriendo en ese puerto.
    |
    | Con la dirección relativa, el navegador la resuelve contra el host por el
    | que entró, y la misma foto sirve para «localhost», para la IP de la red y
    | para el dominio, sin tocar configuración.
    |
    | Si algún día hace falta servir los archivos desde otro dominio (una CDN),
    | se pone ese dominio en ASSET_URL y estas direcciones lo toman solas.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('ASSET_URL', '').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Enlaces simbólicos
    |--------------------------------------------------------------------------
    |
    | «php artisan storage:link» los crea. El arranque del contenedor lo corre
    | en cada despliegue (ver docker-entrypoint.sh): si este enlace falta, las
    | imágenes responden 403 y nada en los registros lo explica.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
