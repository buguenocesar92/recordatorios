<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthentication
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si la MFA está deshabilitada (demo/testing), no se exige verificación
        if (! config('mfa.enabled', false)) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user) {
            // Solo dos exenciones, y las dos son necesarias: la propia pantalla de
            // verificación —si no, el control se muerde la cola: para verificarse hay
            // que entrar donde el propio control no deja— y la salida, para que nadie
            // quede atrapado con una sesión que no puede cerrar.
            //
            // Aquí había una tercera que eximía a Livewire. No hace falta ninguna: este
            // middleware es persistente (ver AppServiceProvider), y Livewire no le pasa
            // la petición al punto de llamada sino una reconstruida con la ruta que
            // originó el componente, así que la exención de la pantalla de verificación
            // ya cubre ese caso. Y sí estorbaría: por Livewire viajan las acciones del
            // panel —borrar, editar, cambiar estado—, que son justo lo que hay que
            // proteger. No reintroducirla.
            if ($request->routeIs('mfa.verify') || $request->is('logout')) {
                return $next($request);
            }

            // Redirigir a la pantalla de verificación si no está validado en sesión
            if (! session()->get('auth.two_factor_verified')) {
                return redirect()->route('mfa.verify');
            }
        }

        return $next($request);
    }
}
