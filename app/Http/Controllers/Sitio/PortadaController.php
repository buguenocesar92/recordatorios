<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sitio;

use App\Enums\ObligationTypeCode;
use App\Models\Comuna;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * La portada con el formulario de la lista de espera. Lo que muestra es igual
 * para todo el mundo (config('sitio') y el catálogo público de comunas, que se
 * lee una vez y queda en caché), y por eso puede cachearse con ETag.
 *
 * El formulario NO lleva token CSRF ni abre sesión: se protege por origen
 * (App\Http\Middleware\VerificarOrigenDelFormulario), Turnstile y límites.
 */
final class PortadaController
{
    public function __invoke(): Response
    {
        $html = self::renderizar();

        // ETag sobre el HTML sin el nonce de esta petición: estable entre dos
        // visitas iguales, distinto si cambia la plantilla, la configuración o
        // el asset compilado. El único guion ejecutable (Turnstile) se autoriza
        // por host en la política, no por nonce, así que un 304 no reusa un
        // nonce viejo para nada ejecutable (.ai/rules/sitio-publico.md).
        $nonce = (string) app('csp-nonce');
        $etag = hash('xxh128', str_replace($nonce, '', $html));

        return response($html)->setEtag($etag);
    }

    /**
     * La misma vista sirve para la portada y para devolver el formulario con
     * errores (422) desde WaitlistController.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function renderizar(array $extra = []): string
    {
        $comunas = Cache::remember('sitio:comunas-por-region', 86400, fn (): array => Comuna::query()
            ->orderBy('region_code')
            ->orderBy('name')
            ->get(['code', 'name', 'region_code', 'region_name'])
            ->groupBy('region_name')
            ->map(fn ($grupo) => $grupo->map(fn (Comuna $c): array => ['code' => $c->code, 'name' => $c->name])->all())
            ->all());

        return view('sitio.portada', array_merge([
            'portada' => (array) config('sitio.portada'),
            'comunas' => $comunas,
            'tipos' => ObligationTypeCode::cases(),
            'sitekey' => (string) config('services.turnstile.sitekey'),
            'anterior' => [],
        ], $extra))->render();
    }
}
