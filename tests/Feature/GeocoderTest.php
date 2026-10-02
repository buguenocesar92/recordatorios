<?php

use App\Support\Geocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Búsqueda de direcciones contra servicios externos.
 *
 * Estaba cubierta a menos de la mitad, y es de las piezas que más conviene
 * tener atada: habla con un servicio de terceros que puede tardar, caerse o
 * responder cualquier cosa, y con lo que devuelve se ubica a una persona en el
 * mapa.
 *
 * Ninguna prueba sale a la red: si alguna lo hiciera, la batería dependería de
 * que OpenStreetMap esté disponible y fallaría sola algún día sin que nadie
 * haya tocado nada.
 */
covers(Geocoder::class);

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('geocoder:photon');
    RateLimiter::clear('geocoder:nominatim');
    Http::preventStrayRequests();

    // Mandar una dirección a un tercero exige declarar quién responde por ese
    // tratamiento (Ley 21.719), y sin ese dato el Geocoder no consulta. Los casos
    // de abajo prueban qué hace CUANDO está declarado, así que se declara acá.
    config()->set('sitio.geocodificacion.contacto', 'informatica@ejemplo.cl');
});

/** Respuesta de Photon con una dirección chilena válida. */
function respuestaPhoton(array $propiedades = [], array $coordenadas = [-70.7278, -34.0672]): array
{
    return ['features' => [[
        'properties' => array_merge([
            'countrycode' => 'CL',
            'street' => 'Avenida Central',
            'housenumber' => '123',
            'city' => 'Graneros',
        ], $propiedades),
        'geometry' => ['coordinates' => $coordenadas],
    ]]];
}

it('no molesta al servicio externo por dos letras', function (string $consulta) {
    // Sin esto, cada tecla del formulario dispararía una petición.
    Http::fake();

    expect(Geocoder::sugerencias($consulta))->toBe([]);

    Http::assertNothingSent();
})->with(['', 'a', 'ab', '  ab  ']);

it('devuelve sugerencias legibles a partir de la respuesta del servicio', function () {
    Http::fake(['*' => Http::response(respuestaPhoton())]);

    $sugerencias = Geocoder::sugerencias('avenida central');

    expect($sugerencias)->toHaveCount(1)
        ->and($sugerencias[0]['label'])->toBe('Avenida Central 123, Graneros');

    // El valor viaja codificado y tiene que volver con las coordenadas intactas.
    $decodificada = Geocoder::decodificarSugerencia($sugerencias[0]['valor']);

    expect($decodificada['lat'])->toBe(-34.0672)
        ->and($decodificada['lng'])->toBe(-70.7278);
});

it('descarta lo que no está en Chile', function () {
    // El servicio responde por cercanía, no por país: sin este filtro, buscar
    // una calle común ofrecería direcciones de Argentina o Perú como si fueran
    // del municipio.
    Http::fake(['*' => Http::response(respuestaPhoton(['countrycode' => 'AR']))]);

    expect(Geocoder::sugerencias('avenida central'))->toBe([]);
});

it('descarta lo que no trae calle, que no sirve para ubicar a nadie', function () {
    Http::fake(['*' => Http::response(respuestaPhoton(['street' => null, 'name' => null, 'housenumber' => null]))]);

    expect(Geocoder::sugerencias('avenida central'))->toBe([]);
});

it('no devuelve más sugerencias que las pedidas', function () {
    $muchas = ['features' => array_fill(0, 20, [
        'properties' => ['countrycode' => 'CL', 'street' => 'Calle', 'housenumber' => '1', 'city' => 'Graneros'],
        'geometry' => ['coordinates' => [-70.7, -34.0]],
    ])];

    Http::fake(['*' => Http::response($muchas)]);

    expect(Geocoder::sugerencias('calle', 4))->toHaveCount(4);
});

it('aguanta que el servicio se caiga, en vez de tumbar la página', function (int $estado) {
    Http::fake(['*' => Http::response('', $estado)]);

    expect(Geocoder::sugerencias('avenida central'))->toBe([]);
})->with([500, 503, 429, 404]);

it('aguanta que la red falle', function () {
    Http::fake(fn () => throw new ConnectionException('sin conexión'));

    expect(Geocoder::sugerencias('avenida central'))->toBe([]);
});

it('la segunda búsqueda igual no vuelve a salir a internet', function () {
    Http::fake(['*' => Http::response(respuestaPhoton())]);

    Geocoder::sugerencias('avenida central');
    Geocoder::sugerencias('avenida central');
    Geocoder::sugerencias('  AVENIDA CENTRAL  '); // misma consulta, otra forma

    Http::assertSentCount(1);
});

it('deja de insistir cuando ya se pidió demasiado', function () {
    Http::fake(['*' => Http::response(respuestaPhoton())]);

    // El límite es de 120 por minuto. Al pasarlo, no debe salir ni una más:
    // el servicio es gratuito y ajeno, y abusar de él termina en un bloqueo.
    for ($i = 0; $i < 121; $i++) {
        RateLimiter::hit('geocoder:photon', 60);
    }

    expect(Geocoder::sugerencias('avenida central'))->toBe([]);

    Http::assertNothingSent();
});

it('lo que escribe la persona viaja como parámetro y no puede reescribir la petición', function () {
    Http::fake(['*' => Http::response(['features' => []])]);

    // Si la consulta se concatenara en la URL, esto agregaría parámetros
    // propios o apuntaría a otro sitio. Tiene que llegar codificada, entera y
    // dentro de «q».
    $malicioso = 'calle&limit=999&lat=0&lon=0#';

    Geocoder::sugerencias($malicioso);

    Http::assertSent(function ($peticion) use ($malicioso): bool {
        $url = $peticion->url();

        return str_starts_with($url, 'https://photon.komoot.io/api/')
            && str_contains($url, urlencode($malicioso))
            && ! str_contains($url, 'limit=999');
    });
});

it('no acepta una sugerencia manipulada', function (?string $valor) {
    // El valor vuelve del navegador: hay que tratarlo como lo que es, algo que
    // cualquiera puede cambiar antes de mandarlo.
    expect(Geocoder::decodificarSugerencia($valor))->toBeNull();
})->with([
    'vacío' => '',
    'nulo' => null,
    'no es base64' => '###',
    'base64 que no es JSON' => 'bm8gc295IGpzb24=',
    'JSON sin coordenadas' => 'eyJkaXIiOiJDYWxsZSJ9',
]);

it('no busca una dirección que no le dieron', function (?string $direccion) {
    Http::fake();

    expect(Geocoder::buscar($direccion))->toBeNull();

    Http::assertNothingSent();
})->with(['nula' => null, 'vacía' => '', 'espacios' => '   ']);

it('encuentra la dirección y la marca como exacta cuando acierta a la primera', function () {
    Http::fake(['*' => Http::response([['lat' => '-34.0672', 'lon' => '-70.7278']])]);

    $r = Geocoder::buscar('Avenida Central 123', 'Graneros');

    expect($r['lat'])->toBe(-34.0672)
        ->and($r['lng'])->toBe(-70.7278)
        ->and($r['aproximado'])->toBeFalse();
});

it('avisa que la ubicación es aproximada cuando tuvo que aflojar la búsqueda', function () {
    /*
     * Busca en cascada: dirección con sector, sin sector, sin número, y por
     * último solo el sector. Que el resultado venga de un intento posterior no
     * es un detalle interno — significa que el punto NO es la dirección pedida,
     * y quien lo mire tiene que saberlo antes de mandar a alguien para allá.
     */
    Http::fake(['*' => Http::sequence()
        ->push([], 200)
        ->push([['lat' => '-34.0672', 'lon' => '-70.7278']], 200),
    ]);

    $r = Geocoder::buscar('Avenida Central 123', 'Graneros');

    expect($r)->not->toBeNull()
        ->and($r['aproximado'])->toBeTrue('Se encontró en el segundo intento y no quedó marcado como aproximado');
});

it('devuelve nada cuando ninguna variante da resultado', function () {
    Http::fake(['*' => Http::response([])]);

    expect(Geocoder::buscar('Calle Que No Existe 9999', 'Graneros'))->toBeNull();
});

it('la misma dirección no se consulta dos veces', function () {
    Http::fake(['*' => Http::response([['lat' => '-34.0672', 'lon' => '-70.7278']])]);

    Geocoder::buscar('Avenida Central 123', 'Graneros');
    Geocoder::buscar('Avenida Central 123', 'Graneros');

    Http::assertSentCount(1);
});

it('respeta el límite del servicio de direcciones', function () {
    Http::fake(['*' => Http::response([['lat' => '-34.0672', 'lon' => '-70.7278']])]);

    for ($i = 0; $i < 61; $i++) {
        RateLimiter::hit('geocoder:nominatim', 60);
    }

    expect(Geocoder::buscar('Avenida Central 123', 'Graneros'))->toBeNull();

    Http::assertNothingSent();
});

it('aguanta que el servicio de direcciones se caiga', function (int $estado) {
    // Los caminos de error son los que de verdad se recorren un martes
    // cualquiera, y los que menos se prueban.
    Http::fake(['*' => Http::response('', $estado)]);

    expect(Geocoder::buscar('Avenida Central 123', 'Graneros'))->toBeNull();
})->with([500, 503, 429]);

it('aguanta que la red se corte al buscar una dirección', function () {
    Http::fake(fn () => throw new ConnectionException('sin conexión'));

    expect(Geocoder::buscar('Avenida Central 123', 'Graneros'))->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Ley 21.719: quién responde por mandar el domicilio de un vecino a un tercero.
// ─────────────────────────────────────────────────────────────────────────────

it('sin un contacto declarado no sale ni una petición con la dirección', function () {
    // El caso que esto cubre es el de un sistema recién generado: si el Geocoder
    // consultara igual, el primer despliegue estaría mandando domicilios de
    // vecinos a dos servicios de terceros sin que nadie lo haya decidido y sin
    // nadie a quien reclamar. Se prefiere quedarse sin coordenadas.
    config()->set('sitio.geocodificacion.contacto', '');
    Http::fake();

    expect(Geocoder::buscar('Avenida Central 123', 'Graneros'))->toBeNull();
    expect(Geocoder::sugerencias('avenida central'))->toBe([]);

    Http::assertNothingSent();
});

it('se identifica ante el servicio con el contacto declarado, no con uno de ejemplo', function () {
    // La política de uso de Nominatim exige un User-Agent que identifique a quien
    // consulta y permita contactarlo; el genérico es justo el perfil que bloquean
    // por IP, y además no le sirve a nadie para reclamar.
    Http::fake(['*' => Http::response([['lat' => '-34.0672', 'lon' => '-70.7278']])]);

    Geocoder::buscar('Avenida Central 123', 'Graneros');

    Http::assertSent(function ($peticion): bool {
        $agente = $peticion->header('User-Agent')[0] ?? '';

        return str_contains($agente, 'informatica@ejemplo.cl')
            && ! str_contains($agente, 'mi-municipio.cl');
    });
});

it('el contexto geográfico sale de la configuración y no de un Santiago fijo', function () {
    // Estaba escrito «Santiago, Región Metropolitana» dentro de la clase. En las
    // comunas del ecosistema eso no es contexto: es una pista falsa que empuja la
    // búsqueda a 100 km del lugar correcto.
    config()->set('sitio.geocodificacion.comuna', 'Graneros');
    config()->set('sitio.geocodificacion.region', "O'Higgins");
    Http::fake(['*' => Http::response([['lat' => '-34.0672', 'lon' => '-70.7278']])]);

    Geocoder::buscar('Avenida Central 123');

    Http::assertSent(function ($peticion): bool {
        $consulta = (string) ($peticion->data()['q'] ?? '');

        return str_contains($consulta, 'Graneros')
            && str_contains($consulta, "O'Higgins")
            && ! str_contains($consulta, 'Santiago');
    });
});

it('sin comuna ni región configuradas no se inventa un contexto', function () {
    config()->set('sitio.geocodificacion.comuna', '');
    config()->set('sitio.geocodificacion.region', '');
    Http::fake(['*' => Http::response([['lat' => '-34.0672', 'lon' => '-70.7278']])]);

    Geocoder::buscar('Avenida Central 123');

    Http::assertSent(function ($peticion): bool {
        $consulta = (string) ($peticion->data()['q'] ?? '');

        // Se sigue acotando a Chile por «countrycodes», así que la palabra sí va;
        // lo que no puede aparecer es una comuna que nadie declaró.
        return $consulta === 'Avenida Central 123, Chile';
    });
});

it('ignora una respuesta con forma inesperada', function (mixed $cuerpo) {
    // Un servicio ajeno puede cambiar sin avisar. Lo que no puede pasar es que
    // el sistema explote o que dé por buenas unas coordenadas que no existen.
    Http::fake(['*' => Http::response($cuerpo)]);

    expect(Geocoder::buscar('Avenida Central 123', 'Graneros'))->toBeNull();
})->with([
    'lista vacía' => [[]],
    'sin coordenadas' => [[['display_name' => 'Algo']]],
    'solo latitud' => [[['lat' => '-34.0672']]],
    'no es una lista' => [['error' => 'lo que sea']],
]);
