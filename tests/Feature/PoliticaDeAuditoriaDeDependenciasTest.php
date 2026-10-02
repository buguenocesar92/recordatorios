<?php

/*
 * Ojo al escribir aserciones acá: `toContain()` de Pest recibe VARIOS patrones
 * (varargs), no un mensaje. `expect($x)->not->toContain('a', 'mensaje')` pregunta
 * si contiene «a» Y «mensaje», y como el mensaje nunca está, el `not` pasa
 * siempre: el test queda verde sin comprobar nada. Por eso acá se compara con
 * `str_contains()` y el mensaje va en `toBeFalse()`/`toBeTrue()`.
 */

/**
 * `composer audit … || true` dejaba pasar en verde cualquier CVE crítica
 * futura: se cerró en «845811e: composer audit bloquea el merge, ya no es
 * report-only». Este test fija esa promesa para que nadie lo reintroduzca sin
 * darse cuenta.
 */
it('la auditoría de dependencias de CI rompe el build en vez de solo informar', function () {
    $lineas = explode("\n", (string) file_get_contents(base_path('.github/workflows/ci.yml')));

    $ordenes = array_filter(
        $lineas,
        fn (string $linea): bool => str_contains($linea, 'composer audit') && str_contains($linea, 'run:'),
    );

    expect($ordenes)->not->toBeEmpty('CI ya no corre `composer audit`');

    foreach ($ordenes as $numero => $orden) {
        expect(str_contains($orden, '|| true'))->toBeFalse(
            'ci.yml:'.($numero + 1).' tapa `composer audit` con `|| true`, así que nunca bloquea',
        );
    }
});

/**
 * `composer.json` sigue con `policy.advisories.block: false`, y es a
 * propósito: NO es el mismo agujero que el `|| true` de arriba.
 *
 * Ese valor solo pesa en el momento de `composer install`, y en los cuatro
 * sitios donde este scaffold instala (el contenedor de desarrollo, la etapa
 * «vendor» del Dockerfile, y las dos veces que CI instala antes de correr los
 * pasos) ya se desactiva a mano, justo antes de instalar, con el motivo
 * escrito al lado: quien decide de verdad es el paso «composer audit» de CI
 * —que bloquea el merge y dice cuál es el paquete—, no un `composer install`
 * que corta un servicio municipal de madrugada porque anoche se publicó un
 * aviso, con un error que no explica nada.
 *
 * Poner `block: true` en `composer.json` movería esa decisión a un lugar peor:
 * el propio `composer install`, sin la explicación que sí da la auditoría, y
 * justo en el arranque de un contenedor. Por eso el candado de abajo exige lo
 * contrario de lo que exigiría un candado ingenuo: que la supresión exista Y
 * que venga documentada, no que desaparezca.
 */
it('composer.json no bloquea la instalación por su cuenta: decide el audit de CI', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['config']['policy']['advisories']['block'] ?? null)->toBeFalse(
        'policy.advisories.block ya no es false: composer install fallará en el arranque de un contenedor '
        .'sin la explicación que sí da «composer audit», en vez de dejar que decida ese paso.',
    );
});

/**
 * `policy.advisories.block false` deja que composer instale un paquete con
 * aviso de seguridad conocido. Está puesto a propósito en CI, en el
 * contenedor de desarrollo y en la etapa «vendor» del Dockerfile —el que
 * decide es el paso de auditoría de arriba—, pero la razón tiene que estar
 * escrita al lado. Si alguien lo copia a otro sitio sin explicarlo, este test
 * lo detiene.
 */
it('cada supresión de avisos de composer viene con su motivo escrito al lado', function () {
    // La ventana de líneas hacia arriba en las que se busca «advisories» en el
    // comentario. El Dockerfile necesita una más ancha: la explicación no
    // puede ir pegada a la orden porque esa RUN es UN SOLO script de shell
    // unido con «\» — un «#» ahí comentaría también el «composer install» que
    // sigue en la misma línea ensamblada — así que va en el bloque de
    // comentarios antes de la instrucción RUN completa.
    $archivos = [
        '.github/workflows/ci.yml' => 8,
        'docker-entrypoint.sh' => 8,
        'Dockerfile' => 20,
    ];

    foreach ($archivos as $ruta => $ventana) {
        $lineas = explode("\n", (string) file_get_contents(base_path($ruta)));

        foreach ($lineas as $i => $linea) {
            // Solo la ORDEN, no el comentario que la explica: el propio texto
            // de la explicación nombra la opción, y contarlo se marcaba a sí
            // mismo.
            $sinComentario = trim((string) preg_replace('/^\s*#.*$/', '', $linea));

            if (! str_contains($sinComentario, 'composer config policy.advisories.block false')) {
                continue;
            }

            $anteriores = implode("\n", array_slice($lineas, max(0, $i - $ventana), min($ventana, $i)));

            expect(str_contains($anteriores, 'advisories'))->toBeTrue(
                "{$ruta}:".($i + 1).' suprime los avisos de composer sin explicar por qué en las líneas de arriba',
            );
        }
    }
});
