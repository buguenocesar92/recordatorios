<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sitio;

use App\Support\Indexacion;
use App\Support\Sitio\Direccion;
use App\Support\Sitio\Sitemap;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Lo que leen los buscadores antes que nada. Generado, no estático: la línea
 * «Sitemap:» y el propio sitemap solo existen en un dominio indexable, y eso
 * se decide por host en cada petición (App\Support\Indexacion).
 */
final class BuscadoresController
{
    public function robots(Request $peticion): Response
    {
        $lineas = ['User-agent: *'];

        if (Indexacion::permitida($peticion)) {
            $lineas[] = 'Disallow: /admin';
            $lineas[] = 'Disallow: /livewire';
            $lineas[] = '';
            $lineas[] = 'Sitemap: '.Direccion::absoluta('sitemap.xml');
        } else {
            // Un dominio de pruebas o interno: nada que indexar.
            $lineas[] = 'Disallow: /';
        }

        return response(implode("\n", $lineas)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Request $peticion): Response
    {
        abort_unless(Indexacion::permitida($peticion), 404);

        // La caché de una hora no se invalida en el deploy: una URL nueva
        // registrada en `Sitemap` tarda hasta una hora en aparecer acá.
        // Aceptado: el sitemap no es un canal urgente y una hora de retraso no
        // afecta el ranking de un buscador.
        $xml = Cache::remember('sitio:sitemap', 3600, function (): string {
            $lineas = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

            foreach (Sitemap::urls() as $url) {
                $lineas[] = '  <url>';
                $lineas[] = '    <loc>'.htmlspecialchars(Direccion::absoluta($url->loc), ENT_XML1).'</loc>';
                if ($url->lastmod !== null) {
                    $lineas[] = '    <lastmod>'.htmlspecialchars($url->lastmod, ENT_XML1).'</lastmod>';
                }
                if ($url->changefreq !== null) {
                    $lineas[] = '    <changefreq>'.$url->changefreq.'</changefreq>';
                }
                if ($url->priority !== null) {
                    $lineas[] = '    <priority>'.rtrim(rtrim(number_format($url->priority, 1, '.', ''), '0'), '.').'</priority>';
                }
                $lineas[] = '  </url>';
            }

            $lineas[] = '</urlset>';

            return implode("\n", $lineas)."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
