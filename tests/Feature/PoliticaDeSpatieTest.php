<?php

use App\Support\Csp\NonceParaVite;
use App\Support\Csp\PoliticaDelPanel;
use App\Support\Csp\PoliticaDelSitio;
use Spatie\Csp\Policy;

/**
 * La política de contenido la declara spatie/laravel-csp, y la declara ENTERA.
 *
 * Este archivo mira la configuración y los presets, no la respuesta HTTP: lo que
 * sale por el cable lo mide PoliticaDeContenidoTest. Acá se vigilan las tres
 * decisiones que, si alguien las revierte, dejan el sistema aparentemente sano:
 *
 *  1. que la política no se pueda apagar desde un archivo que no está en el
 *     repositorio (en personas-graneros la CSP llevaba MESES saliendo como
 *     «Report-Only» porque la variable que la activaba no estaba escrita en
 *     ningún .env y su valor por defecto era falso);
 *  2. que bloquee en vez de reportar;
 *  3. que cada preset produzca exactamente las directivas de la política
 *     artesanal que reemplaza, valor por valor.
 */

/** Devuelve ['default-src' => "'self'", …] a partir de lo que produce un preset. */
function directivasDelPreset(string $preset): array
{
    $contenido = Policy::create([$preset])->getContents();

    $directivas = [];

    foreach (explode(';', $contenido) as $trozo) {
        $trozo = trim($trozo);

        if ($trozo === '') {
            continue;
        }

        [$nombre, $valores] = array_pad(explode(' ', $trozo, 2), 2, '');

        $directivas[$nombre] = $valores;
    }

    return $directivas;
}

it('la política no se apaga desde el entorno', function () {
    // Ningún INTERRUPTOR sale de un archivo que no se versiona: apagar la
    // política, dejarla en «solo reportar» o quitarle el nonce desde un .env es
    // un incidente esperando fecha (pasó en personas-graneros: meses en
    // Report-Only porque la variable no estaba escrita en ninguna parte).
    //
    // Lo único que SÍ se decide por despliegue es a dónde se mandan los
    // reportes —CSP_REPORT_URI, un colector propio—: es una dirección, no un
    // interruptor, y con la variable vacía la política sigue bloqueando igual.
    //
    // Se mira el archivo SIN comentarios: los comentarios del propio archivo
    // explican por qué no lleva env(), y leerlos como código daba un rojo que
    // no era el defecto que este caso busca.
    $fuente = (string) php_strip_whitespace(config_path('csp.php'));

    preg_match_all('/env\(\s*[\'"]([A-Z0-9_]+)[\'"]/', $fuente, $variables);

    expect(array_values(array_unique($variables[1])))->toBe(['CSP_REPORT_URI'],
        'config/csp.php lee una variable de entorno que no es el colector de reportes: la política quedó con interruptor'
    );
});

it('bloquea en vez de reportar, y siempre', function () {
    expect(config('csp.enabled'))->toBeTrue('la política está apagada');
    expect(config('csp.nonce_enabled'))->toBeTrue('sin nonce la política pública no autoriza ningún guion propio');
    expect(config('csp.report_only_presets'))->toBe([]);
    expect(config('csp.report_only_directives'))->toBe([]);
    expect(config('csp.report_uri'))->toBe('');

    // También con Vite en modo «hot»: de fábrica el paquete apaga la política
    // entera ahí, y entonces los fallos de política no se ven en desarrollo,
    // solo en producción.
    expect(config('csp.enabled_while_hot_reloading'))->toBeTrue();
});

it('el sitio es la política por omisión', function () {
    // Fail-closed: una ruta nueva nace con la política dura. Para aflojarla hay
    // que escribirlo, no al revés.
    expect(config('csp.presets'))->toBe([PoliticaDelSitio::class]);
    expect(config('csp.directives'))->toBe([]);
});

it('el nonce se reparte desde un solo sitio', function () {
    expect(config('csp.nonce_generator'))->toBe(NonceParaVite::class);
});

it('la política del sitio declara las mismas directivas que la artesanal', function () {
    $directivas = directivasDelPreset(PoliticaDelSitio::class);

    expect($directivas['default-src'])->toBe("'self'")
        ->and($directivas['style-src'])->toBe("'self' 'unsafe-inline'")
        ->and($directivas['img-src'])->toBe("'self' data: blob:")
        ->and($directivas['font-src'])->toBe("'self' data:")
        ->and($directivas['connect-src'])->toBe("'self'")
        ->and($directivas['object-src'])->toBe("'none'")
        ->and($directivas['base-uri'])->toBe("'self'")
        ->and($directivas['form-action'])->toBe("'self'");

    // Ni «worker-src» ni «media-src»: caen a «default-src 'self'». El «blob:»
    // que FilePond necesita vive SOLO en la política del panel, y no se baja
    // acá «para que sea uniforme» (.ai/rules/cabeceras-y-csp.md).
    expect(array_key_exists('worker-src', $directivas))->toBeFalse(
        'la política del sitio abrió worker-src: el blob: de FilePond sale a lo público'
    );
    expect(array_key_exists('media-src', $directivas))->toBeFalse(
        'la política del sitio abrió media-src: el blob: del reproductor sale a lo público'
    );

    // Los guiones propios se autorizan uno a uno con el nonce de la petición.
    expect($directivas['script-src'])->toStartWith("'self' 'nonce-");
    expect(str_contains($directivas['script-src'], 'unsafe'))->toBeFalse(
        'la política del sitio dejó de ser estricta: le entró un «unsafe-»'
    );
});

it('la política del panel conserva la excepción de Filament y NO declara nonce', function () {
    $directivas = directivasDelPreset(PoliticaDelPanel::class);

    // Filament y Alpine construyen funciones en tiempo de ejecución, y Filament
    // 5.7 escribe tres <script> en línea sin etiquetar en /admin/login.
    expect($directivas['script-src'])->toBe("'self' 'unsafe-eval' 'unsafe-inline'");

    // Y por eso mismo NO puede llevar nonce: por especificación, un script-src
    // con nonce hace que el navegador IGNORE 'unsafe-inline', y el panel se
    // queda sin JavaScript sin un solo error de servidor.
    expect(str_contains($directivas['script-src'], 'nonce-'))->toBeFalse(
        'la política del panel declara un nonce: eso apaga «unsafe-inline» y deja el panel muerto'
    );

    // El resto, idéntico al sitio.
    expect($directivas['worker-src'])->toBe("'self' blob:")
        ->and($directivas['media-src'])->toBe("'self' blob:")
        ->and($directivas['default-src'])->toBe("'self'")
        ->and($directivas['object-src'])->toBe("'none'")
        ->and($directivas['base-uri'])->toBe("'self'")
        ->and($directivas['form-action'])->toBe("'self'");
});

it('ninguna de las dos políticas declara frame-ancestors', function () {
    // Una política con «frame-ancestors» DESACTIVA por completo X-Frame-Options,
    // por especificación. Manda la cabecera, que dice SAMEORIGIN.
    foreach ([PoliticaDelSitio::class, PoliticaDelPanel::class] as $preset) {
        expect(array_key_exists('frame-ancestors', directivasDelPreset($preset)))->toBeFalse(
            "{$preset} declara frame-ancestors y con eso anula X-Frame-Options"
        );
    }
});
