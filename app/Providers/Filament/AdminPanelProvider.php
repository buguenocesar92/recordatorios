<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\AvatarLocalProvider;
use App\Filament\Pages\Auth\Ingresar;
use App\Filament\Pages\Escritorio;
use App\Filament\Resources\ActivityResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\EstadoDelSistema;
use App\Filament\Widgets\MovimientoReciente;
use App\Filament\Widgets\RequiereTuAtencion;
use App\Http\Middleware\CabecerasDeEndurecimiento;
use App\Http\Middleware\IgnorarCookieDeRecordar;
use App\Http\Middleware\NoncePorPeticion;
use App\Http\Middleware\SinCacheParaAutenticados;
use App\Http\Middleware\SoloDesdeLaVpn;
use App\Support\AssetVersionado;
use App\Support\Csp\PoliticaDelPanel;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Csp\AddCspHeaders;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Ingresar::class)
            // La recuperación de contraseña vive acá desde que se quitó la del
            // andamio (routes/web.php cuenta por qué). NO es opcional: sin
            // encenderla no queda NINGUNA forma de recuperar una contraseña, y el
            // enlace «¿Olvidaste tu contraseña?» que AppServiceProvider dibuja
            // debajo del formulario se cae con «Route not defined».
            //
            // Se usa la pantalla de Filament sin subclasear porque la del andamio
            // tampoco validaba nada propio: solo `Password::defaults()`, que este
            // proyecto configura en AppServiceProvider y que Filament respeta
            // igual. Una regla propia del producto va en una subclase de
            // ResetPassword pasada como `resetAction:`.
            //
            // El correo que sale NO es el de fábrica de Filament: AppServiceProvider
            // lo reemplaza en el contenedor por el institucional.
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName('Administración')
            // Sin ->favicon(): apuntaba a public/favicon.ico, que este scaffold
            // nunca trajo. Cada 404 de un asset que no existe es ruido en los
            // logs y una petición de más en cada carga del panel; sin la
            // llamada, Filament sirve el suyo propio.
            // El panel se comporta como una sola aplicación: al cambiar de
            // sección se reemplaza el contenido en vez de recargar la página
            // entera, y con eso desaparece el parpadeo entre pantallas.
            //
            // Tiene una consecuencia que hay que tener presente al tocar este
            // archivo: Livewire vuelve a evaluar TODOS los <script> del cuerpo
            // en cada visita. Los que inyecta este panel llevan
            // «data-navigate-once» por eso; sin ese atributo no fallan de
            // golpe, se van duplicando visita tras visita.
            ->spa()
            // Buscar sin levantar la mano del teclado, como en las herramientas
            // que la gente ya usa.
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // Avisa antes de abandonar un formulario a medio llenar. Con
            // navegación instantánea hace más falta que antes: un clic en el
            // menú lateral ya no da el respiro que daba una recarga.
            ->unsavedChangesAlerts()
            // Sin ->font(): ese `->font('Inter')` que había acá activaba el
            // BunnyFontProvider de Filament (HasFont::getFontProvider() cae a
            // Bunny en cuanto hay una familia custom sin proveedor propio), que
            // en CADA carga del panel pedía `fonts.bunny.net` -preconnect y
            // hoja- y le entregaba la IP de quien entra a un tercero (Ley
            // 21.719), además de dejar el panel sin tipografía en una red
            // filtrada o sin internet. Sin la llamada, Filament usa su
            // propio default -"Inter Variable", LocalFontProvider- que ya viene
            // publicado en `public/fonts/filament/filament/inter/`: mismo
            // tipo de letra, cero CDN.
            // El proveedor de fábrica arma el avatar contra ui-avatars.com pasándole el
            // nombre de quien entra en la URL: una petición saliente en cada carga del
            // panel hacia un tercero que nadie contrató. Se dibuja localmente.
            ->defaultAvatarProvider(AvatarLocalProvider::class)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                // Paleta de KraftDo (tools/paletas-de-entidad.php, rol «acento»).
                'primary' => Color::generateV3Palette('#3c4d5c'),
                'success' => Color::generateV3Palette('#0f7a5a'),
                'warning' => Color::generateV3Palette('#c47a10'),
                'danger' => Color::generateV3Palette('#b03030'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                ActivityResource::class,
                UserResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            // El escritorio del paquete, no el de fábrica: seis columnas en vez
            // de dos, y con tarjetas que dicen qué hacer hoy.
            ->pages([
                Escritorio::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            // Lo primero que se ve al entrar. Sin esto la página queda en blanco
            // y quien entra no sabe ni por dónde empezar.
            ->widgets([
                RequiereTuAtencion::class,
                EstadoDelSistema::class,
                MovimientoReciente::class,
            ])
            // El estilo del panel, que antes venía de un paquete aparte.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<link rel="stylesheet" href="'.AssetVersionado::url('css/panel.css').'">',
            )
            // Movimiento del panel (Motion One): entrada escalonada de las
            // tarjetas y conteo de los indicadores. Va por Vite y no por
            // public/, porque importa una dependencia de npm. Lleva
            // «data-navigate-once» porque el panel navega sin recargar: Livewire
            // reevalúa todos los <script> del cuerpo en cada visita y sin ese
            // atributo se irían duplicando.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => '<script type="module" data-navigate-once src="'
                    .Vite::asset('resources/js/panel.js').'"></script>',
            )
            ->plugin(FilamentShieldPlugin::make())
            ->middleware([
                // Antes que nada: fuera de la VPN el panel no existe (404).
                SoloDesdeLaVpn::class,
                // Primero de todos, antes incluso de descifrar las cookies: el
                // panel no pasa por el grupo «web», así que lo que se quita
                // allí no se quita acá. Ver el docblock del middleware.
                IgnorarCookieDeRecordar::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Filament no usa el grupo «web», así que lo que se añada allí no llega
                // acá: el panel —donde están los datos— se servía sin política de
                // contenido, sin X-Frame-Options y sin nosniff, mientras la pantalla de
                // login, que no guarda nada, sí las llevaba. Van declarados a mano, y
                // con la política DEL PANEL: la única que lleva 'unsafe-eval' (ver
                // App\Support\Csp\PoliticaDelPanel, que explica por qué no lleva nonce).
                NoncePorPeticion::class,
                AddCspHeaders::class.':'.PoliticaDelPanel::class,
                CabecerasDeEndurecimiento::class,
                // El panel es la pantalla con datos, y hasta acá salía sin
                // «no-store» propio: el que traía lo ponía de rebote Livewire al
                // arrancar un componente. Declarado, deja de depender de un
                // detalle interno de un paquete. Ver el docblock del middleware.
                SinCacheParaAutenticados::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Antes del segundo factor: si la sesión no vale —vencida, o
                // traída por la cookie de «Recordarme» sin haber escrito la
                // contraseña— no hay nada que verificar. Al revés, el segundo
                // factor redirigía primero y el control de la sesión no
                // llegaba a correr. Ya no se corta por cambio de red
                // (2026-09-09).
                'sesion.acotada',
                'mfa',
            ]);
    }
}
