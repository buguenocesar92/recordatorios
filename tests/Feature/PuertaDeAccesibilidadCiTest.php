<?php

/**
 * El job «e2e» del CI llevaba `continue-on-error: true` a nivel de JOB. Eso
 * absorbe el fallo de CUALQUIER paso dentro, incluido el de accesibilidad, que
 * declaraba su propio `continue-on-error: false`: el workflow quedaba verde
 * aunque WCAG 2.2 AA fallara, justo lo contrario de lo que promete el
 * comentario de ese paso («no debería llegar a main sin que nadie se entere»).
 *
 * El Decreto N.º 1/2015 SEGPRES obliga a los sitios y sistemas del Estado
 * —municipalidades incluidas— a cumplir estándares W3C de accesibilidad, y de
 * este repositorio nacen todos los sistemas del ecosistema.
 */
it('el paso de accesibilidad del CI puede bloquear el merge de verdad', function () {
    // Sin comentarios: los propios comentarios de este archivo explican QUÉ
    // pasaba antes citando literalmente «continue-on-error», y eso confundiría
    // una búsqueda de texto con la declaración YAML real.
    $original = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
    $lineas = array_filter(
        explode("\n", $original),
        fn (string $linea): bool => ! str_starts_with(trim($linea), '#'),
    );
    $contenido = implode("\n", $lineas);

    $inicioJobE2e = strpos($contenido, "\n  e2e:\n");
    if ($inicioJobE2e === false) {
        throw new RuntimeException('No encontré el job "e2e:" en ci.yml.');
    }

    $inicioAccesibilidad = strpos($contenido, 'Accesibilidad (WCAG 2.2 AA', $inicioJobE2e);
    if ($inicioAccesibilidad === false) {
        throw new RuntimeException('No encontré el paso de accesibilidad.');
    }

    $inicioRestoE2e = strpos($contenido, 'Ejecutar el resto de las E2E', $inicioAccesibilidad);
    if ($inicioRestoE2e === false) {
        throw new RuntimeException('No encontré el paso "Ejecutar el resto de las E2E".');
    }

    // Desde que arranca el job hasta el paso de accesibilidad (setup, checkout,
    // instalación de dependencias…) no puede aparecer un continue-on-error a
    // nivel de job: eso es justo lo que absorbía el fallo de accesibilidad.
    $antesDeAccesibilidad = substr($contenido, $inicioJobE2e, $inicioAccesibilidad - $inicioJobE2e);
    expect(str_contains($antesDeAccesibilidad, 'continue-on-error'))->toBeFalse(
        'El job "e2e" (o algún paso antes de accesibilidad) sigue llevando continue-on-error.',
    );

    // El paso de accesibilidad en sí sigue declarando que un fallo suyo cuenta.
    $pasoAccesibilidad = substr($contenido, $inicioAccesibilidad, $inicioRestoE2e - $inicioAccesibilidad);
    expect(str_contains($pasoAccesibilidad, 'continue-on-error: false'))->toBeTrue(
        'El paso de accesibilidad ya no declara continue-on-error: false.',
    );

    // El resto de las E2E (rendimiento, interacción, móvil) sí puede seguir
    // siendo frágil sin bloquear el merge, pero ahora está declarado en el
    // paso, no heredado del job.
    $pasoRestoE2e = substr($contenido, $inicioRestoE2e, 200);
    expect(str_contains($pasoRestoE2e, 'continue-on-error: true'))->toBeTrue(
        'El paso "Ejecutar el resto de las E2E" no declara su propio continue-on-error.',
    );
});
