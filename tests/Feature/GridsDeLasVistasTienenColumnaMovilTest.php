<?php

use Symfony\Component\Finder\Finder;

/**
 * Un `grid` que solo declara columnas desde un breakpoint (`sm:grid-cols-2`) deja en
 * móvil una columna IMPLÍCITA: el contenido más ancho (una URL, un detalle largo) la
 * estira más allá de la pantalla y la página gana scroll horizontal. `grid-cols-1`
 * explícito la fija a `minmax(0, 1fr)` y el texto se parte. Visto en
 * `salud-del-sistema.blade.php`.
 */
it('todo grid de las vistas que cambia de columnas por breakpoint declara su columna móvil', function () {
    $sinColumnaMovil = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $vista) {
        preg_match_all('/class="([^"]*)"/', $vista->getContents(), $clases);

        foreach ($clases[1] as $atributo) {
            $tokens = preg_split('/\s+/', trim($atributo));
            $esGrid = in_array('grid', $tokens, true);
            $porBreakpoint = (bool) array_filter($tokens, fn (string $t): bool => preg_match('/^(sm|md|lg|xl|2xl):grid-cols-\d+$/', $t) === 1);
            $baseMovil = (bool) array_filter($tokens, fn (string $t): bool => preg_match('/^grid-cols-/', $t) === 1);

            if ($esGrid && $porBreakpoint && ! $baseMovil) {
                $sinColumnaMovil[] = $vista->getRelativePathname().': '.$atributo;
            }
        }
    }

    expect($sinColumnaMovil)->toBe([]);
});
