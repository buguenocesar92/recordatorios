<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sitio;

use Illuminate\Http\Response;

/**
 * La portada. Todo lo que muestra sale de config('sitio'): no consulta la base
 * de datos, y por eso puede cachearse con ETag sin pensar en invalidación.
 */
final class PortadaController
{
    public function __invoke(): Response
    {
        $portada = (array) config('sitio.portada');
        $cta = (array) ($portada['cta'] ?? []);

        $html = view('sitio.portada', [
            'portada' => $portada,
            'ctaTexto' => (string) ($cta['texto'] ?? 'Ingresar'),
            // Relativa a propósito: un enlace interno no necesita host y así
            // funciona detrás de cualquier proxy sin filtrar el de la petición.
            'ctaDireccion' => route($cta['ruta'] ?? config('sitio.ingreso'), [], false),
        ])->render();

        // El ETag automático de «cache.headers» (Tarea 4) hashea el HTML tal
        // como sale, y ese HTML lleva el nonce de CSP de ESTA petición
        // (NoncePorPeticion se lo entrega a Vite para el <link> del CSS, y al
        // JSON-LD vía @cspNonce): distinto en cada visita aunque nada haya
        // cambiado, así que el hash nunca coincidía y el 304 no llegaba nunca.
        //
        // Un ETag calculado solo desde config('sitio') tampoco sirve: no se
        // entera de un cambio en la plantilla, en el pie o en el hash del CSS
        // compilado tras un deploy, y un navegador que ya tenía la portada
        // seguiría recibiendo 304 con el HTML viejo para siempre.
        //
        // Por eso el hash sale del HTML renderizado CON EL NONCE DE LA
        // PETICIÓN QUITADO: cambia con la plantilla, la configuración y el
        // asset compilado, y es estable entre dos peticiones iguales —lo único
        // que varía entre ellas es justo lo que se quita—. Esto NO es el
        // «marcador sustituido en el cuerpo» que prohíbe
        // .ai/rules/cabeceras-y-csp.md: ese patrón cachea el HTML y le estampa
        // ENCIMA el nonce de la petición actual antes de servirlo, regalando un
        // nonce válido a lo que sea que haya quedado escrito ahí. Acá el HTML
        // se renderiza de nuevo en cada petición, con SU PROPIO nonce real; la
        // versión sin nonce se usa solo puertas adentro, para decidir si hace
        // falta mandar el cuerpo o alcanza con un 304 —sin cuerpo, nada que
        // inyectar—. Un nonce viejo dentro de un 304 es inocuo acá: la portada
        // no tiene scripts ejecutables (el único <script> es JSON-LD, que el
        // navegador no ejecuta) y el <link> del CSS lo cubre `style-src 'self'`,
        // que no declara nonce.
        //
        // xxh128 y no sha256: es lo mismo que usa por defecto
        // Illuminate\Http\Middleware\SetCacheHeaders (más rápido que un hash
        // criptográfico y sobra para detectar cambios, no para firmar nada).
        $nonce = (string) app('csp-nonce');
        $etag = hash('xxh128', str_replace($nonce, '', $html));

        return response($html)->setEtag($etag);
    }
}
