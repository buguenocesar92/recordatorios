<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cookie de «Recordarme» de Laravel no vale en este sistema.
 *
 * El guard de sesión vuelve a autenticar solo en cuanto encuentra esa cookie
 * en la petición, y lo hace en CUALQUIER ruta que pregunte por el usuario: la
 * portada, el «guest» de la pantalla de acceso, una API con grupo web. En esa
 * misma petición escribe el identificador en la sesión, así que en la
 * siguiente ya no queda rastro de que vino de la cookie. Como la marca del
 * segundo factor vive en la sesión, una sesión revivida así entra sin
 * contraseña y deja el segundo factor de único factor.
 *
 * El rechazo de «Auth::viaRemember()» que corre en el panel llega tarde para
 * las demás rutas: cuando le toca, la sesión ya se escribió en la petición
 * anterior. Acá se corta antes de todo. La cookie se quita de la petición
 * antes de que nadie resuelva al usuario, y se vence en el navegador para que
 * no vuelva mañana. Sin cookie no hay recuerdo que seguir, y sin recuerdo no
 * hay sesión fantasma.
 *
 * Va PRIMERO en el grupo «web» y PRIMERO en la lista del panel de Filament,
 * que no pasa por «web». Se quita por NOMBRE, así que da lo mismo que el valor
 * todavía venga cifrado: el descifrado de cookies corre después.
 */
class IgnorarCookieDeRecordar
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->nombresDeCookieDeRecordar() as $nombre) {
            if (! $request->cookies->has($nombre)) {
                continue;
            }

            $request->cookies->remove($nombre);
            Cookie::queue(Cookie::forget($nombre));
        }

        return $next($request);
    }

    /**
     * Un nombre por cada guard de sesión configurado, calculado por el propio
     * guard y no escrito a mano: el nombre lleva un hash de la clase, así que
     * copiarlo se rompe solo. Construir el guard no resuelve a ningún usuario.
     *
     * @return list<string>
     */
    private function nombresDeCookieDeRecordar(): array
    {
        $nombres = [];

        foreach ((array) config('auth.guards', []) as $guard => $definicion) {
            if (! is_array($definicion) || ($definicion['driver'] ?? null) !== 'session') {
                continue;
            }

            $instancia = Auth::guard((string) $guard);

            if ($instancia instanceof SessionGuard) {
                $nombres[] = $instancia->getRecallerName();
            }
        }

        return $nombres;
    }
}
