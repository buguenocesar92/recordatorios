<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El grupo «sitio» sirve la portada con caché pública compartida
 * (`Cache-Control: public, s-maxage=300` + ETag), pero una cookie vieja de
 * «Recordarme» todavía puede llegar en la petición: `IgnorarCookieDeRecordar`
 * la vence encolando un `Set-Cookie`, que `AddQueuedCookiesToResponse` adjunta
 * más adentro del grupo. Si un caché compartido (CDN, proxy) guardara ESA
 * respuesta con su `Set-Cookie`, se la serviría después a cualquier otro
 * visitante — la cookie de vencimiento de uno terminaría en el navegador de
 * otro.
 *
 * Este middleware es el único punto del grupo «sitio» que puede ver si la
 * respuesta lleva cookies (va primero, así que es el más externo: envuelve a
 * `AddQueuedCookiesToResponse`, que las adjunta camino de vuelta). Si las hay,
 * fuerza `no-store` y retira el ETag antes de que `cache.headers` escriba su
 * propio `Cache-Control` — visitas normales, sin cookie, siguen públicas y
 * cacheables.
 */
class SinCacheCompartidaConCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->headers->getCookies() !== []) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->remove('ETag');
        }

        return $response;
    }
}
