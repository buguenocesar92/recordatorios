<?php

/**
 * La landing se pinta con la paleta de SU entidad y ninguna otra.
 *
 * Las paletas viven en un solo archivo, `tools/paletas-de-entidad.php`, para que
 * la landing (este scaffold) y la consola/portal Inertia (su aplicador) no
 * tengan cada una su copia divergente. El scaffold base trae escrita la
 * `neutra`, que no es de nadie; `usar-paquete-compartido.php` la reemplaza por
 * la de la entidad al generar (Tarea 9).
 *
 * Decreto N.º 1/2015 SEGPRES: el contraste se recalcula sobre los valores, no
 * se confía en una tabla anotada a mano.
 */
const TOKENS_DEL_CONTRATO = [
    '--marca-acento', '--marca-acento-profundo', '--marca-acento-claro',
    '--marca-realce', '--marca-realce-claro', '--marca-aviso', '--marca-progreso',
    '--marca-tinta', '--marca-tinta-profunda', '--marca-atenuado',
    '--marca-fondo', '--marca-papel', '--marca-papel-2', '--marca-borde', '--marca-borde-2',
    '--marca-barra-texto', '--marca-fondo-oscuro', '--marca-superficie-oscura',
    '--marca-superficie-oscura-2', '--marca-superficie-oscura-3',
];

function paletasDeEntidad(): array
{
    return require base_path('tools/paletas-de-entidad.php');
}

function primitivosEscritosEn(string $css): array
{
    preg_match_all('/(--marca-[a-z0-9-]+):\s*(#[0-9a-f]{6});/', $css, $m, PREG_SET_ORDER);
    $p = [];
    foreach ($m as $token) {
        $p[$token[1]] = $token[2];
    }

    return $p;
}

function razonDeContraste(string $a, string $b): float
{
    $luminancia = function (string $hex): float {
        $hex = ltrim($hex, '#');
        $canal = fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $canal(hexdec(substr($hex, 0, 2)) / 255)
            + 0.7152 * $canal(hexdec(substr($hex, 2, 2)) / 255)
            + 0.0722 * $canal(hexdec(substr($hex, 4, 2)) / 255);
    };
    $la = $luminancia($a);
    $lb = $luminancia($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

it('hay tres paletas y cada una declara los 20 primitivos del contrato', function () {
    $paletas = paletasDeEntidad();

    expect(array_keys($paletas))->toBe(['muni', 'kraftdo', 'neutra']);

    foreach ($paletas as $eco => $p) {
        expect(array_keys($p))->toBe(TOKENS_DEL_CONTRATO, "La paleta «{$eco}» no sigue el contrato.");
        foreach ($p as $token => $hex) {
            expect($hex)->toMatch('/^#[0-9a-f]{6}$/', "{$eco} {$token}");
        }
    }
});

/**
 * Estos tres tests se escriben como COHERENCIA, no como ancla a un valor fijo:
 * `tests/` viaja al proyecto generado y el generador (`tools/usar-paquete-compartido.php`)
 * cambia justo lo que una aserción anclada («la paleta es la neutra», «el tipo
 * es organizacion») afirmaba — quedarían en rojo en CUALQUIER proyecto
 * generado con `--shared`, que es el caso normal, no el raro.
 */
it('el bloque de primitivos coincide EXACTAMENTE con una de las tres paletas del contrato', function () {
    $css = (string) file_get_contents(resource_path('css/sitio.css'));

    expect($css)->toContain('/* PRIMITIVOS-DE-LA-ENTIDAD */')
        ->and($css)->toContain('/* FIN-PRIMITIVOS-DE-LA-ENTIDAD */');

    $inicio = strpos($css, '/* PRIMITIVOS-DE-LA-ENTIDAD */');
    $fin = strpos($css, '/* FIN-PRIMITIVOS-DE-LA-ENTIDAD */');
    $bloque = substr($css, $inicio, $fin - $inicio);
    $escrito = primitivosEscritosEn($bloque);

    expect(in_array($escrito, paletasDeEntidad(), true))->toBeTrue(
        'El bloque entre marcadores no coincide con NINGUNA de las tres paletas de tools/paletas-de-entidad.php: ni la neutra de fábrica ni una entidad válida.'
    );
});

it('fuera del bloque de primitivos no hay ningún --marca-* escrito a mano', function () {
    $css = (string) file_get_contents(resource_path('css/sitio.css'));

    expect($css)->not->toContain('--mg-')->and($css)->not->toContain('--kd-');

    $inicio = strpos($css, '/* PRIMITIVOS-DE-LA-ENTIDAD */');
    $finMarcador = '/* FIN-PRIMITIVOS-DE-LA-ENTIDAD */';
    $fin = strpos($css, $finMarcador) + strlen($finMarcador);
    $fuera = substr($css, 0, $inicio).substr($css, $fin);

    expect(primitivosEscritosEn($fuera))->toBe([],
        'Un primitivo de marca (--marca-*: #hex;) fuera del bloque de primitivos es identidad horneada: sobrevive a un `--shared` distinto que reemplace solo el bloque.'
    );
});

it('el tipo de entidad de la config es coherente con la paleta escrita en el CSS', function () {
    $css = (string) file_get_contents(resource_path('css/sitio.css'));

    $inicio = strpos($css, '/* PRIMITIVOS-DE-LA-ENTIDAD */');
    $fin = strpos($css, '/* FIN-PRIMITIVOS-DE-LA-ENTIDAD */');
    $bloque = substr($css, $inicio, $fin - $inicio);
    $escrito = primitivosEscritosEn($bloque);

    // Mismo mapeo que tools/usar-paquete-compartido.php: neutra↔organizacion
    // (el scaffold base, sin --shared), muni↔municipalidad, kraftdo↔empresa.
    $tipoPorPaleta = ['neutra' => 'organizacion', 'muni' => 'municipalidad', 'kraftdo' => 'empresa'];
    $ecoEscrito = array_search($escrito, paletasDeEntidad(), true);

    expect(config('sitio.entidad.tipo'))->toBeIn(array_values($tipoPorPaleta))
        ->and(config('sitio.entidad.tipo'))->toBe(
            $tipoPorPaleta[$ecoEscrito] ?? null,
            "La paleta escrita es «{$ecoEscrito}» pero config('sitio.entidad.tipo') es «".config('sitio.entidad.tipo').'».',
        );
});

it('las tres paletas cumplen los mínimos de contraste WCAG 2.2 AA', function () {
    foreach (paletasDeEntidad() as $eco => $p) {
        $minimos = [
            'blanco sobre el acento profundo' => ['#ffffff', $p['--marca-acento-profundo'], 4.5],
            'tinta profunda sobre el realce claro' => [$p['--marca-tinta-profunda'], $p['--marca-realce-claro'], 4.5],
            'texto sobre blanco' => [$p['--marca-tinta'], '#ffffff', 4.5],
            'texto sobre el lienzo' => [$p['--marca-tinta'], $p['--marca-fondo'], 4.5],
            'atenuado sobre blanco' => [$p['--marca-atenuado'], '#ffffff', 4.5],
            'atenuado sobre el lienzo' => [$p['--marca-atenuado'], $p['--marca-fondo'], 4.5],
            'borde de control sobre el papel' => [$p['--marca-atenuado'], $p['--marca-papel'], 3.0],
            'acento sobre blanco' => [$p['--marca-acento'], '#ffffff', 4.5],
            'acento sobre el lienzo' => [$p['--marca-acento'], $p['--marca-fondo'], 4.5],
            'blanco sobre el acento' => ['#ffffff', $p['--marca-acento'], 4.5],
            'texto de la barra' => ['#eaf5f3', $p['--marca-acento-profundo'], 4.5],
            'texto suave de la barra' => [$p['--marca-barra-texto'], $p['--marca-acento-profundo'], 4.5],
            'activo/foco en la barra' => [$p['--marca-realce-claro'], $p['--marca-acento-profundo'], 3.0],
            'progreso sobre el lienzo claro' => [$p['--marca-progreso'], $p['--marca-fondo'], 3.0],
            'progreso sobre el lienzo oscuro' => [$p['--marca-progreso'], $p['--marca-fondo-oscuro'], 3.0],
            'texto en oscuro' => ['#eaf5f3', $p['--marca-superficie-oscura'], 4.5],
            'texto sobre el lienzo oscuro' => ['#eaf5f3', $p['--marca-fondo-oscuro'], 4.5],
            'atenuado en oscuro' => ['#94a3b8', $p['--marca-superficie-oscura'], 4.5],
            'acento en oscuro' => [$p['--marca-acento-claro'], $p['--marca-superficie-oscura'], 4.5],
            'acento fuerte en oscuro' => [$p['--marca-realce-claro'], $p['--marca-superficie-oscura'], 4.5],
            'estado correcto en oscuro' => [$p['--marca-realce'], $p['--marca-superficie-oscura'], 4.5],
            'estado de aviso en oscuro' => [$p['--marca-aviso'], $p['--marca-superficie-oscura'], 4.5],
            'texto sobre el acento claro' => [$p['--marca-tinta-profunda'], $p['--marca-acento-claro'], 4.5],
            'texto suave sobre la barra oscura' => [$p['--marca-barra-texto'], $p['--marca-superficie-oscura-2'], 4.5],
        ];

        foreach ($minimos as $nombre => [$frente, $fondo, $minimo]) {
            expect(round(razonDeContraste($frente, $fondo), 2))->toBeGreaterThanOrEqual(
                $minimo,
                "Paleta «{$eco}»: «{$nombre}» ({$frente} sobre {$fondo}) no llega a {$minimo}:1.",
            );
        }
    }
});

it('la hoja del sitio es un entry de Vite', function () {
    expect((string) file_get_contents(base_path('vite.config.js')))->toContain("'resources/css/sitio.css'");
});
