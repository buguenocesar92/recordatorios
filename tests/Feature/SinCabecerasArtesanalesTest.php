<?php

use App\Http\Middleware\CabecerasDeEndurecimiento;
use App\Http\Middleware\NoncePorPeticion;
use App\Support\Csp\PoliticaDelPanel;
use Spatie\Csp\AddCspHeaders;

/**
 * Ni una sola cabecera de política escrita a mano, y las tres piezas en su sitio.
 *
 * Este repositorio tuvo 174 líneas de middleware que armaban la
 * `Content-Security-Policy` concatenando cadenas y decidían la superficie con
 * `$request->is('admin', 'admin/*', 'verificar-mfa')`. Es el MOLDE del que sale
 * cada sistema municipal, así que esa copia se heredaba entera: el día que un
 * sistema generado llama a su panel «feria» o «discapacidad», la condición deja
 * de acertar y el panel se queda con la política estricta —o sea sin
 * JavaScript— sin ningún error de servidor.
 *
 * Ahora la escribe spatie/laravel-csp desde App\Support\Csp. Estos casos
 * impiden que vuelva la versión artesanal, en cualquiera de sus dos formas:
 * resucitar el middleware, o «solo por esta vez» poner un `headers->set()` en
 * un controlador.
 *
 * La tercera parte es la que más silenciosamente se rompe: el panel de Filament
 * NO pasa por el grupo «web», así que un middleware que solo esté en
 * bootstrap/app.php no llega al panel. Y `/verificar-mfa` vive fuera de /admin
 * pero necesita la política del panel. Son TRES pilas, y hay que declararlo en
 * las tres.
 */
it('no existe el middleware que armaba la política a mano', function () {
    expect(class_exists('App\Http\Middleware\SecurityHeaders'))->toBeFalse(
        'volvió App\Http\Middleware\SecurityHeaders: la política la escribe spatie/laravel-csp'
    );
    expect(file_exists(app_path('Http/Middleware/SecurityHeaders.php')))->toBeFalse(
        'volvió app/Http/Middleware/SecurityHeaders.php'
    );
});

it('ningún archivo de app/ ni de config/ escribe la cabecera de política a mano', function () {
    $culpables = [];

    foreach ([app_path(), config_path()] as $raiz) {
        /** @var iterable<SplFileInfo> $archivos */
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz));

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                continue;
            }

            $fuente = (string) file_get_contents($archivo->getPathname());

            if (str_contains($fuente, 'Content-Security-Policy')) {
                $culpables[] = str_replace(base_path().'/', '', $archivo->getPathname());
            }
        }
    }

    sort($culpables);

    // OJO: la lista va vacía y se compara con toBe([]). Con
    // `expect($culpables)->toContain(...)` y un «mensaje» no fallaría nunca: el
    // segundo argumento de toContain() es otro patrón a buscar, no un mensaje.
    expect($culpables)->toBe([]);
});

it('las tres piezas están declaradas en el grupo web y en el panel', function () {
    // El grupo «web», el panel (que no hereda «web») y la pantalla del segundo
    // factor (que vive fuera de /admin y necesita la política del panel).
    $superficies = [
        'bootstrap/app.php' => (string) file_get_contents(base_path('bootstrap/app.php')),
        'AdminPanelProvider' => (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')),
        'routes/web.php' => (string) file_get_contents(base_path('routes/web.php')),
    ];

    foreach (['bootstrap/app.php', 'AdminPanelProvider'] as $donde) {
        foreach ([NoncePorPeticion::class, AddCspHeaders::class, CabecerasDeEndurecimiento::class] as $pieza) {
            $corto = class_basename($pieza);

            expect(str_contains($superficies[$donde], $corto.'::class'))->toBeTrue(
                "{$donde} no registra {$corto}"
            );
        }
    }

    // El panel tiene que pasar el preset a mano: sin el parámetro se llevaría
    // la política estricta del sitio y se quedaría sin JavaScript.
    expect(str_contains($superficies['AdminPanelProvider'], PoliticaDelPanel::class))->toBeTrue(
        'AdminPanelProvider no pasa PoliticaDelPanel al middleware de la política'
    );
});

it('la pantalla del segundo factor declara el preset del panel en su ruta', function () {
    // Acá NO vale mirar el archivo: comentar la línea deja la cadena escrita y
    // el caso pasaría en verde con la ruta ya rota. Se le pregunta al router
    // qué middleware lleva de verdad esa ruta.
    $ruta = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'mfa.verify');

    expect($ruta)->not->toBeNull('no existe la ruta mfa.verify');

    $esperado = AddCspHeaders::class.':'.PoliticaDelPanel::class;

    expect(in_array($esperado, $ruta->gatherMiddleware(), true))->toBeTrue(
        'la ruta del segundo factor no declara la política del panel: la pantalla carga y el botón no responde'
    );
});
