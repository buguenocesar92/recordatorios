<?php

use Kraftdo\Shared\Support\Coordenadas;

/**
 * Lectura de coordenadas pegadas desde Google Maps.
 *
 * Estaba en 0 % de cobertura. Es de las clases donde eso más duele: recibe algo
 * que una persona copió y pegó —la forma más impredecible de entrada que hay— y
 * de su resultado depende dónde queda un punto en el mapa. Un error acá no da
 * excepción: deja el marcador en otro lugar del mundo.
 */
covers(Coordenadas::class);

it('no acepta lo que no es una coordenada', function (?string $entrada) {
    expect(Coordenadas::parse($entrada))->toBeNull();
})->with([
    'nulo' => null,
    'vacío' => '',
    'solo espacios' => '   ',
    'texto suelto' => 'Municipalidad de Graneros',
    'un solo número' => '-34.0672',
    'letras donde van números' => '@abc,def',
    'coma sin números' => ',',
    // Debe exigir la forma completa: media coordenada es peor que ninguna,
    // porque parecería válida.
    'latitud sin longitud' => '-34.0672,',
]);

it('lee las formas en que la gente pega un lugar', function (string $entrada, float $lat, float $lng) {
    expect(Coordenadas::parse($entrada))->toBe(['lat' => $lat, 'lng' => $lng]);
})->with([
    'texto simple' => ['-34.0672, -70.7278', -34.0672, -70.7278],
    'texto simple sin espacio' => ['-34.0672,-70.7278', -34.0672, -70.7278],
    'texto con espacios alrededor' => ['  -34.0672 , -70.7278  ', -34.0672, -70.7278],
    'URL de Google Maps' => ['https://www.google.com/maps/@-34.0672,-70.7278,17z', -34.0672, -70.7278],
    'parámetro q' => ['https://maps.google.com/?q=-34.0672,-70.7278', -34.0672, -70.7278],
    'parámetro query' => ['https://maps.google.com/?query=-34.0672,-70.7278', -34.0672, -70.7278],
    'parámetro ll' => ['https://maps.google.com/?ll=-34.0672,-70.7278', -34.0672, -70.7278],
    'parámetro destination' => ['https://maps.google.com/dir/?destination=-34.0672,-70.7278', -34.0672, -70.7278],
    'parámetros de lugar' => ['https://www.google.com/maps/place/x/data=!3d-34.0672!4d-70.7278', -34.0672, -70.7278],
    'coordenadas positivas' => ['48.8584, 2.2945', 48.8584, 2.2945],
    'enteros sin decimales' => ['0, 0', 0.0, 0.0],
]);

it('entiende el signo menos que pega el navegador', function () {
    // Google Maps y varios navegadores copian el menos tipográfico (U+2212) en
    // lugar del guion ASCII. Sin normalizarlo, una coordenada del hemisferio sur
    // —o sea todas las de Chile— se leería como positiva o se descartaría.
    $conMenosTipografico = "\u{2212}34.0672, \u{2212}70.7278";

    expect(Coordenadas::parse($conMenosTipografico))
        ->toBe(['lat' => -34.0672, 'lng' => -70.7278]);
});

it('rechaza coordenadas que no existen en la Tierra', function (string $entrada) {
    expect(Coordenadas::parse($entrada))->toBeNull();
})->with([
    'latitud sobre el polo' => '91, 0',
    'latitud bajo el polo' => '-91, 0',
    'longitud pasada por el este' => '0, 181',
    'longitud pasada por el oeste' => '0, -181',
]);

it('acepta los bordes exactos del planeta', function (string $entrada, float $lat, float $lng) {
    expect(Coordenadas::parse($entrada))->toBe(['lat' => $lat, 'lng' => $lng]);
})->with([
    'polo norte' => ['90, 0', 90.0, 0.0],
    'polo sur' => ['-90, 0', -90.0, 0.0],
    'antimeridiano este' => ['0, 180', 0.0, 180.0],
    'antimeridiano oeste' => ['0, -180', 0.0, -180.0],
]);

it('prefiere la coordenada de la URL antes que cualquier número suelto del texto', function () {
    // Una URL de Maps trae varios números (el zoom, identificadores). El orden
    // de los patrones importa: si ganara otro, el punto quedaría en otra parte.
    $url = 'https://www.google.com/maps/@-34.0672,-70.7278,17z/data=!3m1!1e3';

    expect(Coordenadas::parse($url))->toBe(['lat' => -34.0672, 'lng' => -70.7278]);
});
