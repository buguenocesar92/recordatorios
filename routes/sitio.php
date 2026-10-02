<?php

declare(strict_types=1);

use App\Http\Controllers\Sitio\BuscadoresController;
use App\Http\Controllers\Sitio\PortadaController;
use Illuminate\Support\Facades\Route;

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
Route::get('/', PortadaController::class)->name('sitio.portada');
Route::get('/robots.txt', [BuscadoresController::class, 'robots'])->name('sitio.robots');
Route::get('/sitemap.xml', [BuscadoresController::class, 'sitemap'])->name('sitio.sitemap');
