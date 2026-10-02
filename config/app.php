<?php

/*
 * Ajustes de la aplicación que el marco no toma del entorno.
 *
 * Laravel dejó de publicar este archivo, y su versión interna trae la zona
 * horaria escrita a mano en «UTC»: la variable APP_TIMEZONE del entorno estaba
 * puesta en América/Santiago desde el principio y no la leía nadie. El sitio
 * corría cuatro horas adelantado, así que a partir de las ocho de la noche
 * «hoy» ya era mañana: una convocatoria dejaba de aparecer el día anterior al
 * que cerraba, y un evento del calendario se daba por pasado antes de ocurrir.
 *
 * Solo van las claves que el municipio necesita ajustar; el resto sigue
 * viniendo del marco.
 */

return [

    'name' => env('APP_NAME', 'Sistema'),

    'timezone' => env('APP_TIMEZONE', 'America/Santiago'),

    'locale' => env('APP_LOCALE', 'es'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'es_ES'),

];
