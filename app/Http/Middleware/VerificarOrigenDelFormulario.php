<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protección CSRF SIN sesión para los formularios públicos de la lista de
 * espera.
 *
 * La landing no emite cookies (ni sesión ni XSRF-TOKEN): así puede cachearse y
 * no le deja nada a quien solo mira. Sin sesión no hay token CSRF clásico, así
 * que se usa la defensa que recomienda OWASP para ese caso: verificar el origen
 * de la petición con las cabeceras que el navegador pone y que una página ajena
 * no puede falsificar.
 *
 * - `Sec-Fetch-Site: same-origin` → pasa. Cualquier otro valor → 403.
 * - Sin `Sec-Fetch-Site` (navegadores viejos): `Origin` tiene que ser
 *   exactamente el origen de esta aplicación.
 * - Sin ninguna de las dos → 403 (falla cerrado). Todo navegador actual manda
 *   `Origin` en un POST; quien no lo manda no es un navegador y el Turnstile y
 *   los límites lo atajan igual.
 */
final class VerificarOrigenDelFormulario
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->origenValido($request)) {
            abort(403, 'Origen no permitido.');
        }

        return $next($request);
    }

    private function origenValido(Request $request): bool
    {
        $sitio = $request->headers->get('Sec-Fetch-Site');

        if (is_string($sitio) && $sitio !== '') {
            return $sitio === 'same-origin';
        }

        $origen = $request->headers->get('Origin');

        return is_string($origen) && $origen !== '' && $origen === $request->getSchemeAndHttpHost();
    }
}
