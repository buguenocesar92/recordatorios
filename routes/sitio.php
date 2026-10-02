<?php

declare(strict_types=1);

use App\Http\Controllers\Sitio\BuscadoresController;
use App\Http\Controllers\Sitio\PortadaController;
use App\Support\Csp\PoliticaDeLaListaDeEspera;
use Illuminate\Support\Facades\Route;
use Spatie\Csp\AddCspHeaders;

/*
|--------------------------------------------------------------------------
| El sitio público (landing)
|--------------------------------------------------------------------------
|
| Lo que indexan los buscadores. Estas rutas NO pasan por el grupo «web»
| (bootstrap/app.php las carga con el grupo «sitio»): sin sesión, sin CSRF,
| sin cookies. La portada no tiene formularios ni estado, y una respuesta
| pública con `Cache-Control: public` que además emite una cookie de sesión es
| una sesión que puede quedar cacheada en un proxy y compartida entre
| visitantes.
|
| Lo que sí lleva: el nonce por petición, la política de contenido del sitio,
| las cabeceras de endurecimiento (incluido X-Robots-Tag fuera del dominio
| indexable) y cabeceras de caché con ETag.
|
| Los nombres («sitio.portada», «sitio.robots», «sitio.sitemap») son parte del
| contrato: el sitemap y la cabecera los resuelven por nombre.
*/
// La portada lleva el widget de Turnstile: su política autoriza
// challenges.cloudflare.com (guion y marco) y nada más. El resto del sitio
// sigue con la política estricta.
Route::get('/', PortadaController::class)
    ->middleware(AddCspHeaders::class.':'.PoliticaDeLaListaDeEspera::class)
    ->name('sitio.portada');

// Textos legales: borradores pendientes de abogado (spec §9).
Route::view('/privacidad', 'sitio.legal.privacidad')->name('sitio.privacidad');
Route::view('/terminos', 'sitio.legal.terminos')->name('sitio.terminos');
Route::view('/que-no-hacemos', 'sitio.legal.que-no-hacemos')->name('sitio.que-no-hacemos');
Route::get('/robots.txt', [BuscadoresController::class, 'robots'])->name('sitio.robots');
Route::get('/sitemap.xml', [BuscadoresController::class, 'sitemap'])->name('sitio.sitemap');
