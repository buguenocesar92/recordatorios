<?php

declare(strict_types=1);

use App\Http\Controllers\Landing\WaitlistController;
use App\Http\Middleware\VerificarOrigenDelFormulario;
use App\Support\Csp\PoliticaDeLaListaDeEspera;
use Illuminate\Support\Facades\Route;
use Spatie\Csp\AddCspHeaders;

/*
|--------------------------------------------------------------------------
| Lista de espera (fase 0)
|--------------------------------------------------------------------------
|
| Grupo «lista» (bootstrap/app.php): mismas cabeceras que el sitio, SIN sesión
| ni cookies, y todas las respuestas con no-store (llevan tokens en la URL o
| datos de una inscripción). El CSRF se cubre por origen
| (VerificarOrigenDelFormulario); la baja en un clic de los clientes de correo
| (RFC 8058) se autoriza solo por la firma de la URL.
|
*/

// La política de la portada (con Turnstile): el 422 vuelve a dibujar la
// portada con su widget, y con la política del sitio el widget no cargaba.
Route::post('/lista-de-espera', [WaitlistController::class, 'store'])
    ->middleware([VerificarOrigenDelFormulario::class, 'throttle:waitlist-ip', AddCspHeaders::class.':'.PoliticaDeLaListaDeEspera::class])
    ->name('lista.store');

Route::get('/lista-de-espera/confirmar/{token}', [WaitlistController::class, 'confirmShow'])
    ->middleware('throttle:waitlist-token')
    ->name('lista.confirmar');

Route::post('/lista-de-espera/confirmar/{token}', [WaitlistController::class, 'confirm'])
    ->middleware([VerificarOrigenDelFormulario::class, 'throttle:waitlist-token'])
    ->name('lista.confirmar.enviar');

Route::post('/lista-de-espera/encuesta/{signup}', [WaitlistController::class, 'survey'])
    ->middleware([VerificarOrigenDelFormulario::class, 'signed:relative'])
    ->name('lista.encuesta');

Route::get('/lista-de-espera/baja/{id}', [WaitlistController::class, 'unsubscribeShow'])
    ->middleware('signed:relative')
    ->where('id', '[0-9A-Za-z]{26}')
    ->name('lista.baja');

Route::post('/lista-de-espera/baja/{id}', [WaitlistController::class, 'unsubscribe'])
    ->middleware('signed:relative')
    ->where('id', '[0-9A-Za-z]{26}')
    ->name('lista.baja.enviar');
