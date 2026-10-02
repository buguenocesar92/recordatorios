<?php

use App\Support\Csp\NonceParaVite;
use App\Support\Csp\PoliticaDelSitio;

/*
|--------------------------------------------------------------------------
| Política de contenido (spatie/laravel-csp)
|--------------------------------------------------------------------------
|
| La política la declaran los presets de App\Support\Csp, no una cadena armada
| a mano: este scaffold tuvo un middleware artesanal (SecurityHeaders) que era
| una de dieciséis copias divergentes del ecosistema, cada una con su propia
| versión del nonce horneado en caché y del worker-src ausente para FilePond.
|
| Acá va la política del sitio; la del panel —y la de /verificar-mfa— se
| declara donde se monta cada ruta, como parámetro del middleware:
| `AddCspHeaders::class.':'.PoliticaDelPanel::class`. Spatie respeta la
| cabecera que ya viene puesta, así que la del grupo «web» no la pisa.
|
| Lo que NO hay acá, a propósito: ninguna variable de entorno que apague la
| política o el nonce. Ya pasó en el ecosistema que una CSP quedara en
| Report-Only en producción porque nadie escribió la variable. La política se
| endurece o se afloja editando los presets, con su test, no por .env.
|
*/

return [

    'presets' => [
        PoliticaDelSitio::class,
    ],

    // Directivas globales sueltas. Vacío: todo se declara en los presets, que
    // es donde se puede leer completa la política de cada superficie.
    'directives' => [],

    // Una política en modo «solo reportar» sirve para ensayar un cambio sin
    // romper nada. Se usa mientras se ensaya y se vacía al terminar: una
    // política que solo reporta no frena ninguna inyección.
    'report_only_presets' => [],
    'report_only_directives' => [],

    // A dónde mandar las violaciones. Vacío = no se reportan. Es lo único de
    // la política que se decide por despliegue (un colector propio, nunca un
    // tercero al que le lleguen las URL que visitan los funcionarios).
    'report_uri' => env('CSP_REPORT_URI', ''),
    'report_only_uri' => '',
    'report_to' => '',
    'report_only_to' => '',
    'reporting_endpoints' => [],

    // Siempre encendida. Sin env(): ver el encabezado.
    'enabled' => true,

    // También con Vite en modo «hot»: el middleware artesanal la aplicaba
    // siempre, y un fallo de política tiene que verse en desarrollo, no en
    // producción.
    'enabled_while_hot_reloading' => true,

    // El generador propio le entrega el nonce a Vite en el mismo acto: la
    // cabecera, @cspNonce, @vite y Livewire leen el MISMO valor.
    'nonce_generator' => NonceParaVite::class,
    'nonce_enabled' => true,
];
