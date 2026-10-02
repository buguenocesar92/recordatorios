<?php

use Kraftdo\Shared\Onboarding\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Onboarding Guiado (Guided Tour)
|--------------------------------------------------------------------------
*/

// El segundo factor va acá también, no solo en el panel: con «auth» a secas, una
// sesión abierta con la contraseña —robada o reutilizada— que nunca pasó por la
// pantalla de verificación llegaba a estos endpoints. Este archivo es el molde que
// copia cada sistema derivado del scaffold: la ruta nueva nace protegida o no nace.
// Y «sin-cache»: estas respuestas llevan datos de la persona que entró y
// ninguna se guarda en el disco del navegador. No lo cubría nadie —el «no-store»
// que ya salía en el panel lo pone Livewire cuando arranca un componente, y un
// controlador corriente como estos no arranca ninguno—, así que era el único
// punto autenticado del andamio que se quedaba en caché.
Route::middleware(['web', 'auth', 'mfa', 'sin-cache'])
    ->prefix('onboarding')
    ->name('onboarding.')
    ->group(function () {
        Route::get('/tour', [OnboardingController::class, 'tour'])->name('tour');
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
        Route::post('/reset', [OnboardingController::class, 'reset'])->name('reset');
    });
