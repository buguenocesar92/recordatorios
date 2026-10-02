<?php

use App\Support\Rutas\MiddlewareDeApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Vacío a propósito: el MVP todavía no expone API. Toda ruta que se agregue va
| DENTRO de este grupo, con la pila de MiddlewareDeApi::AUTENTICADA
| (web + auth + mfa + sin-cache): nace protegida o no nace. El candado es
| tests/Feature/ApiExigeSegundoFactorTest.php, que revisa todo «api/*».
|
*/

Route::middleware(MiddlewareDeApi::AUTENTICADA)->group(function (): void {
    //
});
