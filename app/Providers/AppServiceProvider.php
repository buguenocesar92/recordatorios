<?php

namespace App\Providers;

use App\Avisos\RegistroDeFuentes;
use App\Http\Middleware\AcotarVidaDeSesion;
use App\Http\Middleware\SinCacheParaAutenticados;
use App\Http\Middleware\SoloDesdeLaVpn;
use App\Http\Middleware\TwoFactorAuthentication;
use App\Listeners\RegistrarAcceso;
use App\Models\WaitlistSignup;
use App\Notifications\Auth\RestablecerContrasenaNotification;
use App\Policies\ActivityPolicy;
use App\Support\CredencialesDePlantilla;
use App\Support\ProxiesDeConfianza;
use App\Support\Sitio\Sitemap;
use App\Support\VerificadorDeFiltraciones;
use App\Waitlist\ClaveDeOrigen;
use App\Waitlist\ClavesDeLimite;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordDelPanel;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El registro de fuentes de avisos del escritorio es único por
        // aplicación: la pantalla y las acciones de sus filas tienen que ver
        // exactamente la misma lista.
        //
        // Un sistema nuevo declara las suyas acá:
        //   $this->app->afterResolving(RegistroDeFuentes::class, fn ($r) => $r->registrar(MisVencimientos::class));
        $this->app->singleton(RegistroDeFuentes::class);

        // El panel construye SU propia notificación de recuperación de
        // contraseña en vez de llamar a «sendPasswordResetNotification()» del
        // modelo, así que la sobrescritura de User no lo alcanza: cubre la ruta
        // de Laravel, no la del panel. Y desde que la recuperación vive solo en
        // el panel, esa es la única ruta por la que alguien pide el enlace.
        //
        // Sin este bind, el correo más delicado que manda el sistema —el que la
        // gente tiene que saber distinguir de una copia fraudulenta— saldría
        // con la plantilla de fábrica, en inglés y sin el escudo ni el pie
        // municipal. Filament le asigna la URL firmada después de construirla
        // (`$notification->url = …`); por eso la clase propia la declara.
        $this->app->bind(
            ResetPasswordDelPanel::class,
            static function (mixed $app, array $parametros): RestablecerContrasenaNotification {
                \assert(is_string($parametros['token'] ?? null), 'Filament construye la notificación con el testigo');

                return new RestablecerContrasenaNotification($parametros['token']);
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CredencialesDePlantilla::comprobar();

        $this->limitesDeLaListaDeEspera();

        // Lo primero del arranque: si la instalación de producción trae una
        // credencial publicada en el repositorio (contraseña de la base o de los
        // respaldos del `.env.example`) o el segundo factor apagado o regalado,
        // no se arranca. Ver App\Support\CredencialesDePlantilla y su candado
        // en CandadosDelEcosistemaTest («guardaDeCredencialesDePlantilla»).

        // La portada es la primera URL del sitemap; cada módulo público agrega
        // las suyas (ver App\Support\Sitio\Sitemap).
        Sitemap::reiniciar();

        // Rutas de assets de Vite RELATIVAS a la raíz, para toda la app.
        //
        // Por defecto, `Vite::assetPath()` construye la URL con `asset()`, que
        // arma una dirección ABSOLUTA usando el host de la petición (o
        // `ASSET_URL`/`APP_URL` si está fijado). La portada (`/`) se sirve con
        // `Cache-Control: public, s-maxage=300`: sin este resolver, un
        // `Host: evil.example` forjado en una petición cacheada quedaría
        // escrito en el `<link>`/`<script>` del HTML servido a todo el mundo
        // durante esos 300 s (envenenamiento de caché). Igual que
        // `App\Support\Sitio\Direccion`, el HTML no puede depender del host de
        // la petición. Todo en este proyecto es same-origin: no hay CDN de
        // estáticos delante (`ASSET_URL` vacío en el .env.example) ni ningún
        // consumidor con una base distinta.
        Vite::createAssetPathsUsing(fn (string $path): string => '/'.ltrim($path, '/'));

        // Quién entró, quién salió y quién lo intentó.
        //
        // El historial de cambios responde «quién editó qué»; esto responde
        // «quién entró», que es otra pregunta y la que hace falta después de un
        // incidente: diez intentos fallidos seguidos contra la misma cuenta no
        // se ven en ninguna otra parte.

        // Quién decide si una contraseña ya apareció en una filtración.
        //
        // El de fábrica («NotPwnedVerifier») atrapa el fallo de red, lo manda a
        // «report()» y devuelve que la contraseña está limpia: en una isla sin
        // salida a internet la garantía desaparece en silencio. El propio elige
        // de forma explícita qué hacer cuando no puede comprobar y lo deja
        // escrito en el registro (ver config/seguridad.php → 'filtraciones').
        //
        // Va con «extend» y no con «bind», y no es un capricho: quien registra
        // el de fábrica es «ValidationServiceProvider», que es DIFERIDO. Un
        // «bind» acá se pisa solo, porque el proveedor diferido se carga —y
        // vuelve a registrar el suyo— justo al resolver el contrato, después de
        // nuestro binding y sin decir nada. Con «extend» la sustitución se
        // aplica al resolver, que es lo único que corre siempre después.
        $this->app->extend(
            UncompromisedVerifier::class,
            static fn (): VerificadorDeFiltraciones => VerificadorDeFiltraciones::desdeConfiguracion(),
        );

        // Las tablas recuerdan cómo las dejó cada persona.
        //
        // Filtrar, buscar y ordenar se perdía al abrir una ficha y volver al
        // listado —lo que un funcionario hace decenas de veces al día—, así que
        // cada regreso obligaba a rehacer el filtro. Se guarda en la sesión, no
        // en la cuenta: es una comodidad de rato, no una preferencia que valga
        // la pena persistir ni un dato que valga la pena almacenar.
        Table::configureUsing(function (Table $table): void {
            $table
                ->persistFiltersInSession()
                ->persistSearchInSession()
                ->persistSortInSession()
                ->persistColumnSearchesInSession();
        });

        // Lo que rige en todo campo de subida mientras nadie diga otra cosa.
        //
        // El tope sale de la configuración y está por debajo del que acepta PHP:
        // si el formulario admitiera más de lo que el servidor recibe, el archivo
        // se perdería en el camino y el campo quedaría vacío sin explicar nada.
        //
        // Abrir y descargar: sin esto, un archivo ya guardado solo se puede
        // reemplazar, no mirar, y para comprobar qué documento hay subido había
        // que subirlo de nuevo.
        FileUpload::configureUsing(function (FileUpload $upload): void {
            $upload
                ->maxSize((int) config('seguridad.subida_maxima_kb'))
                // Lista de lo que SE acepta, no de lo que se prohíbe: una lista
                // de prohibidos siempre se queda corta —.phtml, .phar, .pht—.
                //
                // Comprobado en local antes de poner esto: un archivo .php
                // dejado en storage/app/public SE EJECUTA al pedirlo por
                // /storage. Es decir, quien pudiera subir un archivo podía
                // ejecutar código en el servidor. Hay una segunda capa en el
                // servidor (docker/caddy/estaticos.caddyfile) porque un campo
                // concreto puede ampliar esta lista y nadie debería poder abrir
                // ese agujero por descuido.
                //
                // Un campo que necesite otra cosa la declara: ->acceptedFileTypes([...]).
                ->acceptedFileTypes(config('seguridad.tipos_de_archivo_aceptados'))
                // Las fotos se reducen ANTES de salir del navegador.
                //
                // Una foto de teléfono llega con 4000 px de ancho y entre 8 y 12
                // MB, y se muestra a 800 px como mucho: subirla entera es
                // regalar minutos de red —que en una municipalidad no sobra—,
                // disco y trabajo del servidor. «contain» conserva la
                // proporción; sin «upscale», una imagen ya pequeña se deja como
                // está en vez de estirarla.
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth((string) config('seguridad.ancho_maximo_de_imagen'))
                ->imageResizeUpscale(false)
                ->openable()
                ->downloadable();
        });

        $this->fijarPoliticaDeContrasenas();

        Event::listen(Login::class, [RegistrarAcceso::class, 'alEntrar']);
        Event::listen(Failed::class, [RegistrarAcceso::class, 'alFallar']);
        Event::listen(Logout::class, [RegistrarAcceso::class, 'alSalir']);

        // Direcciones desde las que se acepta lo que digan las cabeceras
        // «X-Forwarded-*». Va acá y no en bootstrap/app.php porque allí la
        // configuración todavía no está cargada y config() lanza excepción.
        //
        // Por omisión no se confía en nadie, y de quien sí solo se creen
        // X-Forwarded-For y X-Forwarded-Proto: ver App\Support\ProxiesDeConfianza.
        // Con «*» o con todos los rangos privados cualquiera declaraba la dirección
        // que quisiera: se evadía lo que se cuenta por IP y el candado de la VPN.
        ProxiesDeConfianza::aplicar(config('proxies.confiables'));

        // El segundo factor tiene que cubrir lo que el panel HACE, no solo lo que
        // muestra. Filament es una aplicación de Livewire: abrir una página es una
        // petición normal —y esa sí pasa por el middleware del panel—, pero borrar,
        // editar o cambiar un estado viajan por el punto de llamada de Livewire, que
        // Livewire monta por su cuenta con su propio grupo de middleware.
        //
        // Declararlo persistente es la única forma de que un middleware propio entre
        // ahí. Sin esta línea, quien tenga la contraseña —robada o reutilizada— no ve
        // el panel pero puede operarlo, que es lo peor de los dos mundos: parece
        // protegido y no lo está.
        Livewire::addPersistentMiddleware([
            // Las acciones de Livewire del panel también, no solo la primera carga.
            SoloDesdeLaVpn::class,
            TwoFactorAuthentication::class,
            // El corte de sesión va en la misma lista y por el mismo motivo: un
            // middleware puesto solo en «authMiddleware» se aplica a la primera
            // carga y a ninguna acción posterior, y casi todo lo que ocurre en
            // el panel viaja por Livewire.
            AcotarVidaDeSesion::class,
            // Y el «no-store». Acá lo que se guarda y se restaura con «Atrás»
            // es el DOCUMENTO, no una respuesta POST de Livewire, así que
            // faltar no abriría el agujero; se declara igual para que la
            // respuesta de una acción no salga con una cabecera distinta a la
            // de la página que la produjo, y porque en este repositorio todo
            // middleware propio de seguridad va en esta lista.
            SinCacheParaAutenticados::class,
        ]);

        // Y dos puntos que ese mecanismo NO alcanza: la subida de archivos y la
        // previsualización no llevan snapshot que verificar, así que Livewire no les
        // reaplica nada. De fábrica quedan con «web» a secas —sin «auth» ni el segundo
        // factor—, y cada uno expone su propio punto de extensión para arreglarlo.
        config()->set('livewire.temporary_file_upload.middleware', [
            'throttle:60,1',
            'auth',
            'mfa',
        ]);
        FilePreviewController::$middleware = ['web', 'auth', 'mfa'];

        // La policy de un modelo que vive FUERA de App\Models hay que registrarla a mano:
        // el descubrimiento automático de Laravel busca la policy en el namespace del
        // modelo, así que App\Policies\ActivityPolicy —escrita para el modelo de Spatie—
        // no se encontraba nunca. Comprobado con Gate::getPolicyFor(), que devolvía null.
        //
        // Y un modelo sin policy no queda «sin restricciones»: queda ABIERTO, porque
        // Filament autoriza por defecto cuando no encuentra una.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Forzar esquema HTTPS en producción para prevenir alertas de contenido mixto
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Prevenir Lazy Loading en entornos locales o de pruebas para detectar problemas N+1 antes de producción
        Model::preventLazyLoading(! $this->app->isProduction());

        // Inyección del enlace "¿Olvidaste tu contraseña?" debajo del login de
        // Filament. Apunta a la recuperación DEL PANEL (->passwordReset() en
        // AdminPanelProvider): la del andamio se retiró y su ruta ya no existe.
        if (class_exists(FilamentView::class)) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => '<div class="mt-3 text-center"><a href="'.route('filament.admin.auth.password-reset.request').'" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">¿Olvidaste tu contraseña?</a></div>',
            );
        }
    }

    /**
     * Qué contraseña se acepta.
     *
     * Doce caracteres con letras y números: es lo que encarece adivinarla.
     *
     * La comprobación contra listas de contraseñas ya filtradas se añade —o no—
     * según «seguridad.filtraciones.modo». En 'apagado' la regla ni se aplica, y
     * eso es deliberado: si en este servidor no se va a comprobar nada, la
     * política no lo promete. Fingir la garantía es peor que no tenerla, porque
     * nadie va a buscar lo que cree que ya está resuelto.
     *
     * Qué pasa cuando la comprobación no se puede hacer lo decide
     * App\Support\VerificadorDeFiltraciones; el porqué de cada modo está en
     * config/seguridad.php.
     */
    private function fijarPoliticaDeContrasenas(): void
    {
        Password::defaults(function (): Password {
            $regla = Password::min(12)->letters()->numbers();

            return config('seguridad.filtraciones.modo') === VerificadorDeFiltraciones::APAGADO
                ? $regla
                : $regla->uncompromised();
        });
    }

    /**
     * Límites de la lista de espera (spec §10). La clave es el HMAC de la IP, no
     * la IP: ni la caché guarda direcciones en claro. El límite por correo
     * (uno cada 24 h) vive en App\Waitlist\SignupService porque su respuesta
     * tiene que ser idéntica a la de un alta normal.
     */
    private function limitesDeLaListaDeEspera(): void
    {
        $demasiados = fn (Request $request, array $cabeceras) => response(view('lista.demasiados')->render(), 429, $cabeceras);

        RateLimiter::for('waitlist-ip', fn (Request $request): array => [
            // Por IP (IPv6 agrupada por /64: ver ClaveDeOrigen).
            Limit::perMinute((int) config('waitlist.ip_attempts_per_minute', 5))
                ->by('waitlist-ip:'.WaitlistSignup::hashIp(ClaveDeOrigen::de($request->ip())))
                ->response($demasiados),
            // Por dominio del correo: el límite por correo no frena miles de
            // direcciones distintas contra una misma organización.
            Limit::perMinute((int) config('waitlist.domain_attempts_per_minute', 20))
                ->by('waitlist-dominio:'.hash('sha256', ClavesDeLimite::dominio($request->input('email'))))
                ->response($demasiados),
            // Techo global de seguridad, ALTO y último: Laravel revisa los
            // límites en orden y una petición frenada por IP o dominio no gasta
            // este cupo, así que un solo origen no lo agota. Sirve contra quien
            // rota miles de direcciones; al tocarlo se avisa al log (una vez por
            // minuto) y se responde «intenta en unos minutos».
            Limit::perMinute((int) config('waitlist.global_attempts_per_minute', 300))
                ->by('waitlist-global')
                ->response(function (Request $request, array $cabeceras) {
                    if (Cache::add('waitlist-global:avisado', true, 60)) {
                        Log::warning('lista de espera: se alcanzó el techo global de altas por minuto (posible abuso distribuido)');
                    }

                    return response(view('lista.demasiados', [
                        'mensaje' => 'Hay muchas inscripciones en este momento. Intenta de nuevo en unos minutos.',
                    ])->render(), 429, $cabeceras);
                }),
        ]);

        RateLimiter::for('waitlist-token', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('waitlist-token:'.WaitlistSignup::hashIp(ClaveDeOrigen::de($request->ip()))));
    }
}
