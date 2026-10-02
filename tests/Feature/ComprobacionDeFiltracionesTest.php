<?php

declare(strict_types=1);

use App\Support\VerificadorDeFiltraciones;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * Que la comprobación contra listas de filtraciones falle de forma HONESTA.
 *
 * El verificador de fábrica de Laravel («NotPwnedVerifier») atrapa el fallo de
 * red, lo manda a «report()», deja el cuerpo de la respuesta vacío y devuelve
 * que la contraseña está limpia. En una isla sin salida a internet eso significa
 * que la garantía «esta contraseña no está en una filtración conocida»
 * desaparece en silencio: nadie la comprueba y todo el mundo cree que sí.
 *
 * Y no es solo la excepción: una respuesta 503 o un 429 del servicio tampoco
 * lanzan nada, así que el cuerpo también queda vacío y la contraseña pasa igual.
 */

/** SHA-1 de «password», que está en todas las filtraciones. */
const HASH_FILTRADO = '5BAA61E4C9B93F3F0682250B6CF8331B7EE68FD8';

function respuestaDelRango(string $sufijoYVeces): PromiseInterface
{
    // El servicio devuelve una línea por coincidencia: «SUFIJO:VECES».
    return Http::response("0018A45C4D1DEF81644B54AB7F969B88D65:1\n".$sufijoYVeces."\n");
}

it('sin salida a internet la contraseña se rechaza, en vez de darse por buena', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $verificador = new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3);

    expect($verificador->verify(['value' => 'una-contrasena-cualquiera-42', 'threshold' => 0]))
        ->toBeFalse('Sin poder comprobar nada, la contraseña no puede darse por limpia.');
});

it('una respuesta de error del servicio tampoco cuenta como comprobación', function () {
    // Este es el segundo agujero del verificador de fábrica: un 503 no lanza
    // excepción, así que ni siquiera llega a «report()»; el cuerpo queda vacío y
    // la contraseña pasa como si el servicio hubiera dicho que está limpia.
    Http::fake(fn () => Http::response('Service Unavailable', 503));

    $verificador = new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3);

    expect($verificador->verify(['value' => 'una-contrasena-cualquiera-42', 'threshold' => 0]))
        ->toBeFalse('Un 503 no es una comprobación: es la ausencia de una.');
});

it('el degradado queda escrito en el registro, no en un report() que nadie mira', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    Log::spy();

    (new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3))
        ->verify(['value' => 'una-contrasena-cualquiera-42', 'threshold' => 0]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $mensaje, array $contexto): bool {
            return str_contains($mensaje, 'filtraciones')
                && ($contexto['modo'] ?? null) === VerificadorDeFiltraciones::CERRADO
                && str_contains((string) ($contexto['resultado'] ?? ''), 'rechaz');
        });
});

it('el registro del degradado nunca lleva la contraseña ni su resumen', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $escrito = [];
    Log::listen(function ($evento) use (&$escrito): void {
        $escrito[] = $evento->message.' '.json_encode($evento->context);
    });

    (new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3))
        ->verify(['value' => 'contrasena-secreta-de-alguien-42', 'threshold' => 0]);

    $todo = implode("\n", $escrito);

    expect(str_contains($todo, 'contrasena-secreta-de-alguien-42'))
        ->toBeFalse('La contraseña no puede acabar en un archivo de registro.')
        ->and(str_contains($todo, strtoupper(sha1('contrasena-secreta-de-alguien-42'))))
        ->toBeFalse('Tampoco su resumen completo: es la contraseña con otro nombre.');
});

it('en modo abierto se acepta la contraseña, pero avisando de que no se comprobó', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    Log::spy();

    $paso = (new VerificadorDeFiltraciones(VerificadorDeFiltraciones::ABIERTO, 3))
        ->verify(['value' => 'una-contrasena-cualquiera-42', 'threshold' => 0]);

    expect($paso)->toBeTrue('En modo abierto la disponibilidad manda sobre la comprobación.');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $mensaje, array $contexto): bool => ($contexto['modo'] ?? null) === VerificadorDeFiltraciones::ABIERTO
            && str_contains((string) ($contexto['resultado'] ?? ''), 'acept'));
});

it('cuando el servicio responde, una contraseña filtrada se rechaza', function () {
    Http::fake([
        'api.pwnedpasswords.com/*' => respuestaDelRango(substr(HASH_FILTRADO, 5).':24230577'),
    ]);

    expect((new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3))
        ->verify(['value' => 'password', 'threshold' => 0]))
        ->toBeFalse();
});

it('cuando el servicio responde, una contraseña que no aparece se acepta', function () {
    Http::fake([
        'api.pwnedpasswords.com/*' => respuestaDelRango('0000000000000000000000000000000000A:9'),
    ]);

    expect((new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3))
        ->verify(['value' => 'password', 'threshold' => 0]))
        ->toBeTrue();
});

it('solo sale un fragmento del resumen, nunca la contraseña', function () {
    Http::fake([
        'api.pwnedpasswords.com/*' => respuestaDelRango('0000000000000000000000000000000000A:9'),
    ]);

    // Un valor que no aparece en el propio dominio consultado
    // («pwnedpasswords» contiene «password»), o la comprobación se engañaría sola.
    $clave = 'zarigueya-de-cobre-1975';
    $resumen = strtoupper(sha1($clave));

    (new VerificadorDeFiltraciones(VerificadorDeFiltraciones::CERRADO, 3))
        ->verify(['value' => $clave, 'threshold' => 0]);

    Http::assertSent(function ($peticion) use ($clave, $resumen): bool {
        $url = (string) $peticion->url();

        return str_ends_with($url, '/'.substr($resumen, 0, 5))
            && ! str_contains($url, $clave)
            && ! str_contains($url, substr($resumen, 5));
    });
});

it('la espera es corta: una isla sin salida no cuelga el alta treinta segundos', function () {
    // El verificador de fábrica espera 30 s por defecto. Multiplicado por cada
    // alta o cambio de contraseña, en una isla sin salida es media pantalla
    // colgada por nada.
    expect((int) config('seguridad.filtraciones.espera_segundos'))
        ->toBeLessThanOrEqual(5)
        ->toBeGreaterThan(0);
});

it('en modo apagado la política ni siquiera promete comprobar filtraciones', function () {
    // Es la diferencia entre no tener la garantía y fingir que se tiene: si no
    // se va a comprobar, la regla no lo dice.
    config()->set('seguridad.filtraciones.modo', VerificadorDeFiltraciones::APAGADO);

    expect(Password::defaults()->appliedRules()['uncompromised'])->toBeFalse();
});

it('en cualquier otro modo la política sí comprueba filtraciones', function () {
    config()->set('seguridad.filtraciones.modo', VerificadorDeFiltraciones::CERRADO);

    expect(Password::defaults()->appliedRules()['uncompromised'])->toBeTrue();
});

it('el verificador que usa el sistema es el propio, no el de fábrica', function () {
    expect(app(UncompromisedVerifier::class))
        ->toBeInstanceOf(VerificadorDeFiltraciones::class);
});

it('el mensaje al usuario no afirma que la contraseña esté filtrada cuando no se pudo comprobar', function () {
    $mensaje = (string) trans('validation.password.uncompromised');

    expect(str_contains($mensaje, 'no se pudo comprobar'))
        ->toBeTrue('El rechazo por falta de comprobación no puede leerse como «tu contraseña está filtrada».');
});
