<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Geocodificación con Nominatim (OpenStreetMap) y Photon (Komoot).
 *
 * Gratis, sin API keys y con control de Rate Limit integrado para evitar baneo de IP.
 *
 * BASE DE LICITUD (Ley 21.719)
 * ────────────────────────────
 * Convertir una dirección en coordenadas manda ESA DIRECCIÓN a dos servicios de
 * terceros fuera de Chile. Es un tratamiento de datos personales, aunque la
 * dirección viaje sola, y por eso acá está escrito qué se hace y con qué límites:
 *
 *  - Finalidad: ubicar en el mapa el domicilio asociado a un trámite, para poder
 *    atenderlo (visita en terreno, asignación por sector). Nada más.
 *  - Minimización: sale el texto de la dirección y el contexto geográfico de la
 *    institución. NUNCA el nombre, el RUT ni ningún otro dato de la persona:
 *    quien recibe la consulta no puede saber de quién es el domicilio.
 *  - Responsable identificable: la consulta se firma con un correo real de la
 *    institución («sitio.geocodificacion.contacto»), que es a la vez lo que la
 *    política de uso de Nominatim exige y la vía para reclamar. Sin ese dato no
 *    se consulta: se devuelve vacío. Se prefiere quedarse sin coordenadas a
 *    mandar domicilios a un tercero sin nadie que responda por ello.
 *  - Conservación: el resultado se cachea 30 días (12 h las sugerencias) para no
 *    repetir la consulta, no para acumular un histórico.
 *
 * Nada de esto viene encendido de fábrica: un sistema recién generado no
 * geocodifica hasta que alguien declara el contacto en su .env.
 */
final class Geocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const PHOTON_ENDPOINT = 'https://photon.komoot.io/api/';

    /**
     * Cómo se presenta el sistema ante el servicio externo.
     *
     * Nominatim exige un User-Agent que identifique a quien consulta y permita
     * contactarlo; el genérico que traía este scaffold —«ScaffoldApp/1.0
     * (https://mi-municipio.cl)»— no identifica a nadie y es justo el perfil que
     * bloquean por IP. Devuelve null cuando no hay contacto declarado, que es la
     * señal de que no se puede consultar.
     */
    private static function agente(): ?string
    {
        $contacto = trim((string) config('sitio.geocodificacion.contacto', ''));

        if ($contacto === '') {
            Log::warning(
                'Geocoder: no se consultó porque no hay un contacto declarado. '
                .'Sin él no se puede identificar a quién reclamar por el envío de '
                .'direcciones a un tercero (Ley 21.719). Configura GEOCODER_CONTACTO.'
            );

            return null;
        }

        return trim((string) config('app.name', 'Sistema')).' ('.$contacto.')';
    }

    /**
     * Busca coordenadas para una dirección de texto en cascada.
     *
     * @return array<string, mixed>|null
     */
    public static function buscar(?string $direccion, ?string $sector = null): ?array
    {
        $direccion = trim((string) $direccion);
        $sector = trim((string) $sector);

        if ($direccion === '' && $sector === '') {
            return null;
        }

        $clave = 'geocoder:'.md5(mb_strtolower($direccion.'|'.$sector));

        return Cache::remember($clave, now()->addDays(30), fn (): ?array => self::resolver($direccion, $sector));
    }

    /**
     * Sugerencias de direcciones para autocompletado en tiempo real.
     *
     * @return array<int, mixed>
     */
    public static function sugerencias(string $q, int $limite = 6): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [];
        }

        $clave = 'geocoder:sug:'.md5(mb_strtolower($q));

        return Cache::remember($clave, now()->addHours(12), fn (): array => self::consultarPhoton($q, $limite));
    }

    /** @return array<string, mixed>|null */
    public static function decodificarSugerencia(?string $valor): ?array
    {
        if (! $valor) {
            return null;
        }

        $json = json_decode((string) base64_decode($valor, true), true);

        return is_array($json) && isset($json['lat'], $json['lng']) ? $json : null;
    }

    /** @return array<array-key, mixed> */
    private static function consultarPhoton(string $q, int $limite): array
    {
        $agente = self::agente();

        if ($agente === null) {
            return [];
        }

        if (RateLimiter::tooManyAttempts('geocoder:photon', 120)) {
            Log::warning('Geocoder: límite de peticiones a Photon alcanzado.');

            return [];
        }
        RateLimiter::hit('geocoder:photon', 60);

        // El punto de referencia solo ordena por cercanía. Sin configurar no se
        // manda ninguno: un sesgo inventado empuja los resultados a otra región.
        $lat = config('sitio.geocodificacion.lat');
        $lon = config('sitio.geocodificacion.lon');

        $parametros = ['q' => $q, 'limit' => $limite * 3];

        if ($lat !== null && $lat !== '' && $lon !== null && $lon !== '') {
            $parametros['lat'] = (float) $lat;
            $parametros['lon'] = (float) $lon;
        }

        try {
            $respuesta = Http::withHeaders(['User-Agent' => $agente])
                ->timeout(6)
                ->get(self::PHOTON_ENDPOINT, $parametros);
        } catch (Throwable $e) {
            Log::warning('Geocoder: fallo de red al consultar Photon.', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $respuesta->ok()) {
            return [];
        }

        $items = [];

        foreach ((array) $respuesta->json('features') as $f) {
            $p = $f['properties'] ?? [];
            $coords = $f['geometry']['coordinates'] ?? null; // [lon, lat]

            if (($p['countrycode'] ?? null) !== 'CL' || ! is_array($coords)) {
                continue;
            }

            $calle = trim(($p['street'] ?? $p['name'] ?? '').' '.($p['housenumber'] ?? ''));
            if ($calle === '') {
                continue;
            }

            $ciudad = $p['city'] ?? $p['district'] ?? $p['county'] ?? '';
            $label = trim($calle.($ciudad !== '' ? ', '.$ciudad : ''));
            $lat = round((float) $coords[1], 7);
            $lng = round((float) $coords[0], 7);

            $items[] = [
                'valor' => base64_encode((string) json_encode([
                    'lat' => $lat, 'lng' => $lng, 'dir' => $calle, 'label' => $label,
                ])),
                'label' => $label,
            ];

            if (count($items) >= $limite) {
                break;
            }
        }

        return $items;
    }

    /** @return array<string, mixed>|null */
    private static function resolver(string $direccion, string $sector): ?array
    {
        $sinNumero = trim((string) preg_replace('/\s*\d+\s*$/', '', $direccion));

        $consultas = array_values(array_unique(array_filter([
            self::armar($direccion, $sector),
            $sector !== '' ? self::armar($direccion, null) : null,
            ($sinNumero !== '' && $sinNumero !== $direccion) ? self::armar($sinNumero, $sector) : null,
            $sector !== '' ? self::armar($sector, null) : null,
        ])));

        foreach ($consultas as $i => $consulta) {
            $r = self::consultar($consulta);
            if ($r !== null) {
                $r['aproximado'] = $i > 0;

                return $r;
            }
        }

        return null;
    }

    /**
     * El contexto que desambigua la búsqueda.
     *
     * Iba fijo a «Santiago, Región Metropolitana». En las comunas del ecosistema
     * eso no es contexto sino una pista falsa: empuja la búsqueda a cien
     * kilómetros del lugar correcto. Ahora sale de la configuración y, si nadie
     * la declaró, no se añade nada; la búsqueda se sigue acotando a Chile.
     */
    private static function armar(string $texto, ?string $sector): string
    {
        $partes = array_filter([
            trim($texto),
            trim((string) $sector),
            trim((string) config('sitio.geocodificacion.comuna', '')),
            trim((string) config('sitio.geocodificacion.region', '')),
            'Chile',
        ]);

        return implode(', ', $partes);
    }

    /** @return array<string, mixed>|null */
    private static function consultar(string $consulta): ?array
    {
        $agente = self::agente();

        if ($agente === null) {
            return null;
        }

        if (RateLimiter::tooManyAttempts('geocoder:nominatim', 60)) {
            Log::warning('Geocoder: límite de peticiones a Nominatim alcanzado.');

            return null;
        }
        RateLimiter::hit('geocoder:nominatim', 60);

        try {
            $respuesta = Http::withHeaders(['User-Agent' => $agente])
                ->timeout(8)
                ->get(self::ENDPOINT, [
                    'q' => $consulta,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'cl',
                ]);
        } catch (Throwable $e) {
            Log::warning('Geocoder: fallo de red al consultar Nominatim.', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $respuesta->ok()) {
            Log::warning('Geocoder: respuesta no OK de Nominatim.', ['status' => $respuesta->status()]);

            return null;
        }

        $primero = $respuesta->json()[0] ?? null;
        if (! is_array($primero) || ! isset($primero['lat'], $primero['lon'])) {
            return null;
        }

        return [
            'lat' => round((float) $primero['lat'], 7),
            'lng' => round((float) $primero['lon'], 7),
            'nombre' => (string) ($primero['display_name'] ?? $consulta),
        ];
    }
}
