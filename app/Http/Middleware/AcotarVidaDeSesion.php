<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le pone un techo absoluto a una sesión abierta del panel.
 *
 * La caducidad corriente mide inactividad, y a quien trabaja seguido —o deja
 * una pestaña que recarga sola— no lo alcanza nunca: la sesión dura para
 * siempre. Doce horas cubren la jornada más larga con margen (ocho cortaban a
 * quien seguía trabajando), así que la sesión que quedó abierta en un equipo
 * compartido no sigue viva al día siguiente.
 *
 * Ya NO se mira desde qué red se usa la sesión. La comprobación anterior
 * guardaba el prefijo de red del primer request y cerraba la sesión si
 * cambiaba, para cortar una cookie robada. En la práctica cerraba la sesión de
 * quien salía del wifi de la oficina y quedaba en datos móviles —justo ese
 * caso es «otra red»—, y como este middleware es persistente en Livewire, la
 * botaba a media pantalla. Se decidió el 2026-09-09 (spec de
 * discapacidad-graneros:
 * `docs/superpowers/specs/2026-09-09-aflojar-candados-de-acceso-design.md`).
 *
 * Riesgo aceptado, escrito: una cookie robada sirve hasta que venza la sesión
 * (inactividad de dos horas o estas doce absolutas), igual que en cualquier
 * sistema corriente. Lo cubren la caducidad, HTTPS con `HttpOnly` y
 * `SameSite=Lax`, y el segundo factor.
 */
class AcotarVidaDeSesion
{
    /** Doce horas: la jornada más larga, con margen. */
    public const DURACION_MAXIMA = 12 * 3600;

    private const CLAVE_INICIO = 'sesion.iniciada_en';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        // Quien llega por la cookie de «Recordarme» no escribió la contraseña.
        // Esa cookie vale catorce meses y la sesión dos horas, así que al
        // vencer la sesión volvía a autenticar sola: el panel saltaba directo
        // a la pantalla del segundo factor, con el correo del último que entró
        // a la vista y un código recién enviado a su casilla. El segundo factor
        // quedaba de único factor —quien tomara el equipo, o se llevara solo esa
        // cookie, entraba sin saber la contraseña— y el corte de las doce horas
        // de más abajo no servía de nada, porque la cookie lo revivía al día
        // siguiente.
        //
        // Se cierra y se pide la contraseña. «logout» rota el testigo, así que
        // esta cookie y cualquier otra repartida en otros equipos dejan de
        // valer, no solo esta vez.
        if (Auth::viaRemember()) {
            return $this->cerrar($request, 'recordar');
        }

        $sesion = $request->session();

        // Primera petición de la sesión: se anota cuándo empezó.
        if (! $sesion->has(self::CLAVE_INICIO)) {
            $sesion->put(self::CLAVE_INICIO, now()->getTimestamp());

            return $next($request);
        }

        $vencida = now()->getTimestamp() - (int) $sesion->get(self::CLAVE_INICIO) > self::DURACION_MAXIMA;

        if ($vencida) {
            return $this->cerrar($request, 'expirada');
        }

        return $next($request);
    }

    /**
     * Cierra la sesión y manda al formulario de acceso con el motivo.
     *
     * Se invalida y se rota el testigo: sin eso, el identificador de la sesión
     * anterior seguiría siendo válido y todo esto no serviría de nada.
     */
    private function cerrar(Request $request, string $motivo): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Brazos explícitos: un motivo nuevo mal escrito no debe caer en silencio
        // en el mensaje de otro.
        $aviso = match ($motivo) {
            'expirada' => 'Tu sesión superó las 12 horas. Ingresa de nuevo.',
            'recordar' => 'Por seguridad, escribe tu contraseña para volver a entrar.',
            default => 'Tu sesión se cerró. Ingresa de nuevo.',
        };

        return redirect()->route('filament.admin.auth.login')->with('status', $aviso);
    }
}
