<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * El panel del dueño solo responde desde la VPN (spec §6, §10). Desde fuera
 * responde 404, no 403: no confirma que ahí haya un panel.
 *
 * La IP es la que resuelve Laravel con los proxies de confianza
 * (config/proxies.php): una cabecera X-Forwarded-For escrita por el cliente no
 * la cambia. Sin subred configurada solo deja pasar en `local` y `testing`: en
 * cualquier otro entorno (production, staging, un APP_ENV mal escrito) no deja
 * pasar a nadie. Falla cerrado.
 */
final class SoloDesdeLaVpn
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $subredes */
        $subredes = (array) config('panel.vpn_cidr', []);

        if ($subredes === []) {
            abort_unless(app()->environment(['local', 'testing']), 404);

            return $next($request);
        }

        abort_unless(IpUtils::checkIp((string) $request->ip(), $subredes), 404);

        return $next($request);
    }
}
