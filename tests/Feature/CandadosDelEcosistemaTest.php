<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\CredencialesDePlantilla;
use App\Support\ReporteDeErrores;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Los candados de configuración que este proyecto heredaba de un paquete de
 * pruebas compartido (`Candados::…()`), reescritos aquí sin esa dependencia.
 *
 * Este sistema nace sin ningún paquete compartido (decisión del dueño): cada
 * garantía que antes daba el paquete se comprueba ahora con código propio, con
 * el mismo nombre que tenía, para que el inventario antes/después del README
 * («Garantías de seguridad») se pueda seguir uno a uno.
 */
uses(RefreshDatabase::class);

/** Lee un archivo del repositorio; falla con un mensaje claro si no está. */
function leerDelRepo(string $relativa): string
{
    $ruta = base_path($relativa);
    expect(is_file($ruta))->toBeTrue("Falta {$relativa}");

    return (string) file_get_contents($ruta);
}

/** @return list<string> */
function archivosPhpBajo(string ...$directorios): array
{
    $archivos = [];

    foreach ($directorios as $directorio) {
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directorio), FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            if ($archivo instanceof SplFileInfo && $archivo->getExtension() === 'php') {
                $archivos[] = $archivo->getPathname();
            }
        }
    }

    return $archivos;
}

describe('seedersSinCredencialesEnProduccion', function () {
    it('en producción ningún seeder deja una cuenta con contraseña conocida', function () {
        $this->app->detectEnvironment(fn (): string => 'production');

        (new RolesSeeder)->run();

        $conClaveDePlantilla = User::all()->filter(
            fn (User $usuario): bool => Hash::check('password', (string) $usuario->password),
        );

        expect($conClaveDePlantilla)->toBeEmpty();
    });

    it('todo seeder que escribe una contraseña literal está detrás de una guarda de entorno', function () {
        foreach (archivosPhpBajo('database/seeders') as $archivo) {
            $codigo = (string) file_get_contents($archivo);

            if (preg_match('/(Hash::make|bcrypt)\(\s*[\'"]/', $codigo) !== 1) {
                continue;
            }

            expect($codigo)->toMatch(
                "/environment\\(\\s*'production'\\s*\\)/",
                basename($archivo).' escribe una contraseña literal sin preguntar por el entorno',
            );
        }
    });
});

describe('imagenDeProduccion', function () {
    it('la imagen instala sin dependencias de desarrollo y sobre FrankenPHP fijado', function () {
        $dockerfile = leerDelRepo('Dockerfile');

        expect($dockerfile)
            ->toContain('composer install --no-dev')
            ->toMatch('/^FROM dunglas\/frankenphp:\d+\.\d+\.\d+-php[\d.]+-alpine\s*$/m')
            ->not->toMatch('/^ENV\s+APP_DEBUG\s*=?\s*true/mi')
            ->not->toMatch('/^ENV\s+APP_ENV\s*=?\s*(local|testing)/mi');
    });

    it('el contenedor no sirve como root', function () {
        $entrypoint = leerDelRepo('docker/docker-entrypoint.prod.sh');

        expect($entrypoint)->toContain('APP_USER=www-data')
            ->toMatch('/exec su-exec "\$APP_USER" php artisan octane:start/');
    });
});

describe('erroresNoSalenDelPais', function () {
    it('solo se reporta a un colector propio, nunca al SaaS de Sentry', function (?string $dsn, bool $esperado) {
        $anterior = getenv('SENTRY_LARAVEL_DSN');
        putenv($dsn === null ? 'SENTRY_LARAVEL_DSN' : "SENTRY_LARAVEL_DSN={$dsn}");

        try {
            expect(ReporteDeErrores::vaADestinoPropio())->toBe($esperado);
        } finally {
            putenv($anterior === false ? 'SENTRY_LARAVEL_DSN' : "SENTRY_LARAVEL_DSN={$anterior}");
        }
    })->with([
        'sin DSN' => [null, false],
        'vacío' => ['', false],
        'sentry.io' => ['https://abc@sentry.io/1', false],
        'subdominio de sentry.io' => ['https://abc@o1.ingest.SENTRY.io/1', false],
        'DSN ilegible' => ['no-es-una-url', false],
        'GlitchTip propio' => ['https://abc@errores.kraftdo.cl/1', true],
    ]);

    it('el arranque engancha el reporte solo detrás de esa decisión', function () {
        expect(leerDelRepo('bootstrap/app.php'))
            ->toContain('use App\Support\ReporteDeErrores;')
            ->toMatch('/if \(ReporteDeErrores::vaADestinoPropio\(\)\)\s*\{\s*Integration::handles/');
    });
});

describe('proxiesDeConfianza', function () {
    it('no se confía en cualquier proxy (por omisión, en ninguno)', function () {
        $confiables = config('proxies.confiables');

        expect($confiables)->toBeArray()
            ->and(array_intersect($confiables, ['*', '**', '0.0.0.0/0', '::/0']))->toBe([]);
    });

    it('la lista se aplica al arranque', function () {
        expect(leerDelRepo('app/Providers/AppServiceProvider.php'))
            ->toContain("ProxiesDeConfianza::aplicar(config('proxies.confiables'))");
    });
});

describe('nadieEmiteCookieDeRecordar', function () {
    it('ningún código propio inicia sesión con «recordarme»', function () {
        $patrones = [
            '/->login\([^;]*,\s*true\s*\)/',
            '/Auth::login\([^;]*,\s*true\s*\)/',
            '/attempt\([^;]*,\s*true\s*\)/',
            '/remember\s*:\s*true/',
            '/loginUsingId\([^;]*,\s*true\s*\)/',
        ];

        foreach (archivosPhpBajo('app', 'routes') as $archivo) {
            $codigo = (string) file_get_contents($archivo);

            foreach ($patrones as $patron) {
                expect(preg_match($patron, $codigo))->toBe(0, basename($archivo)." emite la cookie de recordar ({$patron})");
            }
        }
    });
});

describe('cookieDeRecordarInerte', function () {
    it('la cookie de recordar se descarta en web, en el sitio y en el panel', function () {
        $arranque = leerDelRepo('bootstrap/app.php');

        expect($arranque)->toMatch('/web\(prepend:\s*\[\s*IgnorarCookieDeRecordar::class/')
            ->and($arranque)->toMatch("/group\\('sitio',\\s*\\[[^\\]]*IgnorarCookieDeRecordar::class/s")
            ->and(leerDelRepo('app/Providers/Filament/AdminPanelProvider.php'))->toContain('IgnorarCookieDeRecordar::class');
    });

    it('los testigos de recordarme que ya existían se olvidan con una migración', function () {
        expect(leerDelRepo('database/migrations/2026_09_02_120000_olvidar_los_testigos_de_recordarme.php'))
            ->toContain('remember_token');
    });
});

describe('guardaDeCredencialesDePlantilla', function () {
    it('el arranque de la aplicación ejecuta la guarda', function () {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.connections.'.config('database.default').'.password', 'sistema_pass');

        $proveedor = $this->app->getProvider(AppServiceProvider::class);

        expect($proveedor)->not->toBeNull()
            ->and(fn () => $proveedor->boot())->toThrow(RuntimeException::class, 'sigue siendo la del ejemplo');
    });

    it('la guarda es lo primero que corre en el arranque', function () {
        $codigo = leerDelRepo('app/Providers/AppServiceProvider.php');

        expect($codigo)->toMatch('/public function boot\(\): void\s*\{\s*CredencialesDePlantilla::comprobar\(\);/')
            ->and(class_exists(CredencialesDePlantilla::class))->toBeTrue();
    });
});

describe('pwaSinRestosDelScaffold', function () {
    it('no queda un service worker ni un manifiesto sin registrar', function () {
        $restos = array_merge(
            glob(public_path('sw*.js')) ?: [],
            glob(public_path('manifest*.webmanifest')) ?: [],
        );

        $vistas = collect(archivosPhpBajo('resources/views'))
            ->map(fn (string $archivo): string => (string) file_get_contents($archivo))
            ->implode("\n");

        foreach ($restos as $resto) {
            expect(str_contains($vistas, basename($resto)))->toBeTrue(basename($resto).' está en public/ pero ninguna vista lo registra');
        }

        expect(true)->toBeTrue();
    });
});

describe('higieneDeLaEtapaDeAssets', function () {
    it('la etapa de assets no instala devDependencies ni corre postinstall de terceros', function () {
        $dockerfile = leerDelRepo('Dockerfile');

        expect($dockerfile)->toContain('RUN npm ci --omit=dev --ignore-scripts')
            ->and(preg_match('/^RUN npm (ci|install)\s*$/m', $dockerfile))->toBe(0);
    });

    it('lo que el build necesita está en dependencies, no en devDependencies', function () {
        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $paquete */
        $paquete = json_decode(leerDelRepo('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $dependencias = array_keys($paquete['dependencies'] ?? []);

        foreach (['vite', 'laravel-vite-plugin', '@tailwindcss/vite', 'tailwindcss', 'axios'] as $necesario) {
            expect($dependencias)->toContain($necesario);
        }

        // Todo paquete que importa un entrypoint de resources/js también.
        foreach (glob(resource_path('js/*.js')) ?: [] as $entrada) {
            preg_match_all("/(?:from\\s+|import\\s*\\(?\\s*)['\"]([^'\".\\/][^'\"]*)['\"]/", (string) file_get_contents($entrada), $m);

            foreach ($m[1] as $importado) {
                $paqueteRaiz = str_starts_with($importado, '@')
                    ? implode('/', array_slice(explode('/', $importado), 0, 2))
                    : explode('/', $importado)[0];

                expect(in_array($paqueteRaiz, $dependencias, true))->toBeTrue(basename($entrada)." importa {$paqueteRaiz} y no está en dependencies");
            }
        }
    });
});

describe('permisosNoSeQuedanPegados', function () {
    it('los permisos se vacían en cada petición de Octane', function () {
        expect(config('permission.register_octane_reset_listener'))->toBeTrue();
    });
});

describe('segundoFactorNoSeRegala', function () {
    it('por omisión el segundo factor está encendido y el código no se muestra', function () {
        $config = require config_path('mfa.php');

        // Lo que se despliega si nadie toca el entorno: los valores por omisión.
        $ejemplo = leerDelRepo('.env.example');

        expect(preg_match('/^MFA_ENABLED=false/m', $ejemplo))->toBe(0)
            ->and(preg_match('/^MFA_SHOW_CODE=true/m', $ejemplo))->toBe(0)
            ->and(leerDelRepo('config/mfa.php'))
            ->toContain("env('MFA_ENABLED', true)")
            ->toContain("env('MFA_SHOW_CODE', false)")
            ->and($config)->toHaveKeys(['enabled', 'show_code']);
    });
});
