<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * La política de contenido la declaran dos presets de spatie/laravel-csp
 * (App\Support\Csp\PoliticaDelSitio y PoliticaDelPanel), no una cadena armada a
 * mano. Estos casos fijan lo que cada una tiene que decir y, sobre todo, lo que
 * NO puede decir.
 */
uses(RefreshDatabase::class);

function politicaDe(string $url): string
{
    return (string) test()->get($url)->headers->get('Content-Security-Policy');
}

function jefeDelPanel(): User
{
    Role::findOrCreate((string) config('filament-shield.super_admin.name', 'super_admin'), 'web');
    $jefe = User::factory()->create();
    $jefe->assignRole((string) config('filament-shield.super_admin.name', 'super_admin'));

    return $jefe;
}

it('fuera del panel la política no permite guiones en línea', function () {
    // Con «unsafe-inline» una inyección ejecuta igual: la política no frena
    // nada, que es justo para lo que se pone.
    // Se mide en «/»: al unificar el ingreso y la recuperación en el panel, las
    // pantallas propias dejaron de existir. La raíz es la única ruta HTML fuera
    // del panel que se puede pedir sin sesión, y aunque sea una redirección las
    // cabeceras se aplican igual, que es justo lo que este caso mira.
    $csp = politicaDe('/');

    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->and($csp)->not->toContain("'unsafe-inline' 'unsafe-eval'")
        ->and($csp)->not->toContain("'unsafe-eval'");
});

it('el panel conserva la excepción que Filament necesita', function () {
    // Filament y Alpine construyen funciones en tiempo de ejecución: sin
    // «unsafe-eval» el panel deja de responder. Se acota a /admin, que además
    // está detrás del ingreso y del segundo factor.
    config()->set('mfa.enabled', false);

    $csp = (string) $this->actingAs(jefeDelPanel())->get('/admin')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("'unsafe-eval'")
        ->and($csp)->toContain("'unsafe-inline'");
});

it('el panel NO declara un nonce en script-src, o Filament se rompe en silencio', function () {
    // Por especificación, cuando script-src lleva un nonce el navegador IGNORA
    // 'unsafe-inline'. Filament 5.7.6 escribe cinco <script> en línea sin
    // atributo nonce (vendor/filament/filament/resources/views/components/
    // layout/base.blade.php) y no ofrece forma de etiquetarlos: con un nonce en
    // la política, esos cinco guiones dejan de correr sin un solo error de
    // servidor. El nonce existe igual en la petición —Livewire etiqueta los
    // suyos con él—, pero la cabecera del panel no puede nombrarlo hasta que
    // Filament etiquete los propios.
    $csp = politicaDe('/admin/login');

    expect($csp)->not->toBeEmpty()
        ->and($csp)->not->toContain('nonce-');
});

it('el panel autoriza los workers y el video desde blob:, que FilePond necesita', function () {
    // Sin worker-src las miniaturas quedan grises; sin media-src el video queda
    // en negro. Los dos fallan SIN ningún error visible (ver
    // .ai/rules/cabeceras-y-csp.md).
    $csp = politicaDe('/admin/login');

    expect($csp)->toContain("worker-src 'self' blob:")
        ->and($csp)->toContain("media-src 'self' blob:");
});

it('el sitio público no abre blob: a los workers ni al video', function () {
    // La regla del repo: blob: va solo bajo el panel. Fuera de él caen a
    // default-src 'self' y no se baja la política «para que sea uniforme».
    $csp = politicaDe('/');

    expect($csp)->not->toContain('worker-src')
        ->and($csp)->not->toContain('media-src');
});

it('la pantalla del segundo factor recibe la política del panel', function () {
    // Es un componente Livewire fuera de /admin con la misma necesidad de
    // 'unsafe-eval'. Sin esto nadie puede terminar de entrar al panel.
    config()->set('mfa.enabled', true);

    $csp = (string) $this->actingAs(jefeDelPanel())
        ->get(route('mfa.verify'))
        ->assertSuccessful()
        ->headers->get('Content-Security-Policy');

    expect($csp)->toContain("'unsafe-eval'")
        ->and($csp)->not->toContain('nonce-');
});

it('las directivas comunes están en las dos políticas', function () {
    foreach (['/', '/admin/login'] as $url) {
        $csp = politicaDe($url);

        foreach ([
            "default-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ] as $directiva) {
            expect(str_contains($csp, $directiva))->toBeTrue("{$url} no declara «{$directiva}»: {$csp}");
        }

        // Una política con frame-ancestors DESACTIVA X-Frame-Options por
        // especificación; acá manda la cabecera y punto.
        expect($csp)->not->toContain('frame-ancestors');
    }
});

it('la ventana queda aislada de quien la abrió', function () {
    expect($this->get('/')->headers->get('Cross-Origin-Opener-Policy'))
        ->toBe('same-origin');
});

it('un dominio que no está en la lista no se indexa', function () {
    config()->set('sitio.dominios_indexables', ['ejemplo.cl']);

    $respuesta = $this->get('http://otro-dominio.test/');

    expect($respuesta->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

/*
|--------------------------------------------------------------------------
| Candados de la migración a spatie/laravel-csp (2026-09-10)
|--------------------------------------------------------------------------
|
| Los de arriba miran la política del sitio y la del panel por separado. Estos
| miran lo que NO puede aparecer en ninguna de las dos, medido sobre la
| respuesta real y no sobre el preset: entre el preset y el cable hay tres pilas
| de middleware, y este repositorio ya vivió el caso de una cabecera que existía
| en el código y no llegaba al panel.
|
*/

/** Devuelve ['default-src' => "'self'", …] a partir de una respuesta HTTP. */
function directivasDeLaRespuesta(string $ruta): array
{
    $csp = (string) test()->get($ruta)->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeEmpty("{$ruta} se sirve sin política de contenido");

    $directivas = [];

    foreach (explode(';', $csp) as $trozo) {
        $trozo = trim($trozo);

        if ($trozo === '') {
            continue;
        }

        [$nombre, $valores] = array_pad(explode(' ', $trozo, 2), 2, '');

        $directivas[$nombre] = $valores;
    }

    return $directivas;
}

dataset('superficies con política', [
    'sitio' => ['/'],
    'sitio sin formulario' => ['/privacidad'],
    'panel' => ['/admin/login'],
]);

it('connect-src no admite comodines ni esquemas sueltos', function (string $ruta) {
    // Un esquema suelto —«ws:», «wss:», «https:»— NO acota nada: vale para
    // cualquier host del mundo, así que dejarlo es lo mismo que no tener la
    // directiva. Si algún día hace falta un WebSocket, va el ORIGEN completo
    // leído de config(), y si la config viene vacía se queda en 'self'.
    $connect = directivasDeLaRespuesta($ruta)['connect-src'] ?? '';

    expect($connect)->not->toBeEmpty("{$ruta} no declara connect-src");

    foreach (['*', 'ws:', 'wss:', 'http:', 'https:', 'data:'] as $suelto) {
        expect(in_array($suelto, explode(' ', $connect), true))->toBeFalse(
            "connect-src de {$ruta} admite «{$suelto}», que vale para cualquier host"
        );
    }
})->with('superficies con política');

it('ninguna directiva autoriza a un tercero', function (string $ruta) {
    // La lista de terceros va VACÍA, y no es cosmético: cada dominio que se
    // agregue es permiso para ejecutar o pintar algo servido por alguien que no
    // somos nosotros, y en un sistema municipal cada carga le entrega la IP de
    // un funcionario a ese alguien sin base de licitud (Ley 21.719).
    $intrusos = [];

    foreach (directivasDeLaRespuesta($ruta) as $directiva => $valores) {
        foreach (explode(' ', $valores) as $valor) {
            if ($valor === '' || str_starts_with($valor, "'")) {
                continue;
            }

            // «data:» y «blob:» son esquemas locales, no hosts.
            if (in_array($valor, ['data:', 'blob:'], true)) {
                continue;
            }

            $intrusos[] = "{$directiva} {$valor}";
        }
    }

    sort($intrusos);

    // Única excepción, y exacta: la portada lleva el widget de Cloudflare
    // Turnstile (subencargado declarado, spec §9). Solo su guion y su marco.
    if ($ruta === '/') {
        $intrusos = array_values(array_diff($intrusos, [
            'frame-src https://challenges.cloudflare.com',
            'script-src https://challenges.cloudflare.com',
        ]));
    }

    // Se compara con toBe([]) y no con toContain(): en Pest el segundo
    // argumento de toContain() es otro patrón a buscar, no un mensaje de fallo,
    // y el caso pasaría siempre en verde.
    expect($intrusos)->toBe([]);
})->with('superficies con política');

it('worker-src y media-src llevan blob: en el panel, y en ninguna parte más', function (string $ruta) {
    // Sin «worker-src 'self' blob:» la miniatura del campo de subida (FilePond)
    // queda en un recuadro GRIS para siempre; sin «media-src 'self' blob:» el
    // video queda en un rectángulo NEGRO en 0:00. Las dos cosas SIN ningún
    // error visible en ninguna parte: no se ven en el registro ni en la consola.
    //
    // Pero el «blob:» va SOLO donde está la subida, que es el panel. El sitio
    // público se queda sin las dos directivas —caen a «default-src 'self'»—, y
    // no se bajan «para que sea uniforme»: cada una es permiso para ejecutar
    // código o pintar contenido nacido en el propio navegador, en la superficie
    // que ve cualquiera sin sesión (.ai/rules/cabeceras-y-csp.md).
    $directivas = directivasDeLaRespuesta($ruta);
    $esPanel = str_starts_with($ruta, '/admin');

    foreach (['worker-src', 'media-src'] as $directiva) {
        expect($directivas[$directiva] ?? '')->toBe($esPanel ? "'self' blob:" : '',
            $esPanel
                ? "{$ruta} perdió «{$directiva} 'self' blob:»: la subida falla en silencio"
                : "{$ruta} declaró «{$directiva}»: el blob: del panel se bajó a lo público"
        );
    }
})->with('superficies con política');

it('la pantalla del segundo factor se sirve con la política del panel', function () {
    // Vive fuera de /admin pero es un componente Livewire dibujado con el tema
    // de Filament: con la política estricta del sitio la pantalla carga y el
    // botón no responde, y es la ÚNICA pantalla desde la que se puede terminar
    // de entrar al sistema.
    Role::findOrCreate('super_admin', 'web');
    $jefe = User::factory()->create();
    $jefe->assignRole('super_admin');

    // assertOk() y no solo la cabecera: si la pantalla dejara de dibujarse, un
    // caso que solo mira la cabecera del 302 pasaría en verde sin probar nada.
    $csp = (string) $this->actingAs($jefe)->get('/verificar-mfa')
        ->assertOk()
        ->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeEmpty('/verificar-mfa se sirve sin política de contenido');
    expect(str_contains($csp, "'unsafe-eval'"))->toBeTrue(
        '/verificar-mfa se lleva la política estricta del sitio: el segundo factor no responde'
    );
});
