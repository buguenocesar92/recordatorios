<?php

namespace App\Http\Middleware;

use App\Support\Indexacion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cabeceras de seguridad que NO son la política de contenido.
 *
 * La CSP la escribe spatie/laravel-csp a partir de los presets de
 * App\Support\Csp; este middleware no la toca ni la conoce. Lo que queda acá es
 * lo demás que endurece una respuesta, y va en las dos superficies: el grupo
 * «web» (bootstrap/app.php) y el panel (AdminPanelProvider), que no pasa por
 * «web» y se servía sin nada de esto hasta que se declaró a mano.
 */
class CabecerasDeEndurecimiento
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Fuera de los dominios declarados, nada se indexa. Va como cabecera y
        // no como etiqueta «meta» porque tiene que cubrir TODO: un PDF o una
        // imagen se indexan igual, y ahí no hay dónde poner una etiqueta.
        if (! Indexacion::permitida($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Sin esto el panel es enmarcable desde otro sitio, y el borrado masivo
        // de usuarios está a un clic. La CSP no declara frame-ancestors a
        // propósito: si lo hiciera, esta cabecera dejaría de contar.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

        // Aísla esta ventana de cualquier otra que la haya abierto: sin esto,
        // una página ajena conserva una referencia a la nuestra.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // HSTS solo sobre HTTPS: prometerlo por HTTP es un error que el navegador
        // ignora, y en local sin TLS dejaría el sitio inaccesible durante un año.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
