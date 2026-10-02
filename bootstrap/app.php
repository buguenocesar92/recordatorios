<?php

use App\Http\Middleware\AcotarVidaDeSesion;
use App\Http\Middleware\CabecerasDeEndurecimiento;
use App\Http\Middleware\IgnorarCookieDeRecordar;
use App\Http\Middleware\NoncePorPeticion;
use App\Http\Middleware\SinCacheCompartidaConCookies;
use App\Http\Middleware\SinCacheParaAutenticados;
use App\Http\Middleware\SoloDesdeLaVpn;
use App\Http\Middleware\TwoFactorAuthentication;
use App\Support\ReporteDeErrores;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // El sitio público (landing). Trae su propio grupo de middleware;
            // ver routes/sitio.php.
            Route::middleware('sitio')->group(base_path('routes/sitio.php'));

            // La lista de espera: sin sesión, sin caché. Ver routes/lista-de-espera.php.
            Route::middleware('lista')->group(base_path('routes/lista-de-espera.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Los proxies de confianza NO se fijan aquí: dentro de este closure todavía no
        // hay configuración cargada —config() lanza «Target class [config] does not
        // exist»—, así que leerla acá no funciona. Se fijan en AppServiceProvider::boot,
        // que es donde ya existe. Ver config/proxies.php.

        // Antes que nada: la cookie de «Recordarme» se descarta antes de que
        // ningún guard llegue a verla. Puesta al final del grupo llegaría
        // tarde —«guest» y «auth» ya habrían resuelto al usuario desde ella—.
        // Ver el docblock del middleware.
        $middleware->web(prepend: [
            IgnorarCookieDeRecordar::class,
        ]);

        // Cabeceras de seguridad web. Tres piezas, cada una con un solo trabajo:
        // el nonce de la petición (antes de dibujar nada), la política de
        // contenido que arma spatie desde config/csp.php, y el resto de las
        // cabeceras. El panel no pasa por este grupo: AdminPanelProvider las
        // declara igual, con la política del panel.
        $middleware->web(append: [
            NoncePorPeticion::class,
            AddCspHeaders::class,
            CabecerasDeEndurecimiento::class,
        ]);

        // El sitio público: las mismas tres piezas de cabeceras que «web»,
        // sin sesión ni CSRF (ver routes/sitio.php), más caché con ETag. El
        // orden importa: `cache.headers` va último para que su Cache-Control
        // sea el que llega al navegador.
        //
        // La portada es la URL más visitada del sistema, y el candado del
        // ecosistema (`CandadosDelEcosistemaTest`) exige que una cookie de
        // «Recordarme» vieja se venza ahí también. `IgnorarCookieDeRecordar`
        // solo ENCOLA el vencimiento cuando la petición trae esa cookie, y es
        // `AddQueuedCookiesToResponse` quien de verdad la adjunta a la
        // respuesta. Sin esa cookie no hay nada encolado, así que la portada
        // sigue sin emitir `Set-Cookie` ni sesión ni CSRF en el caso normal:
        // `PortadaTest > no arranca sesión ni emite cookies` lo confirma.
        //
        // Esa cookie de vencimiento y la caché pública compartida no pueden
        // convivir: un caché intermedio que guardara la respuesta con su
        // `Set-Cookie` se la serviría después a cualquier otro visitante.
        // `SinCacheCompartidaConCookies` va PRIMERO —el más externo— para ver
        // la respuesta ya con las cookies que `AddQueuedCookiesToResponse`
        // adjuntó más adentro, y forzar `no-store` antes de que
        // `cache.headers` (que sigue yendo último) escriba el suyo.
        $middleware->group('sitio', [
            SinCacheCompartidaConCookies::class,
            IgnorarCookieDeRecordar::class,
            NoncePorPeticion::class,
            AddCspHeaders::class,
            CabecerasDeEndurecimiento::class,
            AddQueuedCookiesToResponse::class,
            'cache.headers:public;max_age=300;s_maxage=300;etag',
        ]);

        // La VPN se comprueba antes que la autenticación: fuera de la VPN el
        // panel y la pantalla del segundo factor responden 404, no «inicia
        // sesión». Sin esto Laravel ordena «auth» primero por prioridad.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: SoloDesdeLaVpn::class,
        );

        // La lista de espera (formulario de la portada, confirmación, baja):
        // las mismas cabeceras que el sitio, el enlace de modelos para
        // {signup}, y no-store en todo. Sin sesión ni CSRF de sesión: el
        // origen lo verifica VerificarOrigenDelFormulario ruta por ruta.
        $middleware->group('lista', [
            NoncePorPeticion::class,
            AddCspHeaders::class,
            CabecerasDeEndurecimiento::class,
            SubstituteBindings::class,
            SinCacheParaAutenticados::class,
        ]);

        // Rotar la contraseña cierra TODAS las sesiones abiertas, no solo las del
        // panel. Es la respuesta de incidente estándar —se sospecha que una cookie
        // de sesión se filtró y lo primero que se hace es cambiar la contraseña—,
        // y hasta acá quedaba a medias: Filament declara «AuthenticateSession» a
        // mano en la lista del panel, así que «/admin» sí se cerraba, pero el grupo
        // «web» no lo llevaba y todo lo autenticado que vive FUERA del panel
        // seguía abierto con la contraseña vieja («/verificar-mfa» y las rutas de
        // «routes/api.php», que van con ['web','auth','mfa']). El operador creía
        // haber revocado el acceso y no lo había hecho del todo.
        //
        // Va al grupo entero y no ruta por ruta a propósito: este repositorio es el
        // MOLDE del que sale cada sistema municipal, donde «routes/web.php» deja de
        // tener dos rutas para tener cuarenta. La ruta nueva nace atada al hash de
        // la contraseña o no nace.
        //
        // No toca nada de lo público: el middleware se retira sin hacer nada cuando
        // la petición no trae sesión o no hay usuario resuelto, así que la portada,
        // la pantalla de acceso y la recuperación de contraseña —que además viven
        // en el panel— siguen igual. Y no rompe el primer ingreso: si la sesión
        // todavía no guarda el hash, lo ADOPTA en vez de cerrarla.
        //
        // Queda después de «IgnorarCookieDeRecordar», que va «prepend»: cuando le
        // toca, la cookie de «Recordarme» ya se quitó de la petición, así que la
        // rama de «viaRemember()» de este middleware nunca llega a correr. Ese es
        // el orden correcto; invertirlo dejaría que la cookie resucitara la sesión
        // antes de comprobarla. Candados:
        // «RotarLaContrasenaCierraLasSesionesTest».
        $middleware->authenticateSessions();

        // Registrar alias para TwoFactorAuthentication middleware
        $middleware->alias([

            'sesion.acotada' => AcotarVidaDeSesion::class,
            'mfa' => TwoFactorAuthentication::class,
            // Alias y no grupo: «no-store» se cuelga RUTA A RUTA, donde hay
            // sesión y datos. En el grupo «web» entero mataría la caché de todo
            // lo público que pasa por PHP. Ver el docblock del middleware.
            'sin-cache' => SinCacheParaAutenticados::class,
        ]);

        // Adónde va quien no ha iniciado sesión. Apuntaba a «/login», la puerta
        // propia que se retiró por duplicada: al quitarla, esto mandaba a todo
        // invitado a un 404. Se resuelve por NOMBRE de ruta y no por una cadena
        // escrita a mano, que es lo que dejó de cuadrar sin que nada avisara.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Reporta excepciones a Sentry solo si el DSN configurado apunta a un
        // destino propio (GlitchTip autoalojado): antes se enganchaba con
        // «class_exists(Integration::class)», que es incondicional porque el
        // paquete está siempre en composer.json. Ver ReporteDeErrores.
        if (ReporteDeErrores::vaADestinoPropio()) {
            Integration::handles($exceptions);
        }
    })->create();
