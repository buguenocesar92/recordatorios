<?php

/**
 * El estilo de la pantalla del segundo factor no se sale de su tarjeta.
 *
 * La vista trae su CSS en un <style> dentro del propio componente. Eso está bien
 * mientras cada regla apunte a algo suyo, y estaba escrito con selectores de
 * elemento pelados —«h2 { color: #38bdf8 }», «p { color: #94a3b8 }»—, que no
 * distinguen nada: pintan CUALQUIER h2 y CUALQUIER p de la página que monte el
 * componente.
 *
 * Hoy no se nota porque «layouts.app» es una cáscara con un solo slot. Es
 * precisamente por eso que conviene el candado: el día que alguien le agregue un
 * encabezado o un pie a ese layout —o monte este componente en otra pantalla— se
 * le van a repintar los textos con los colores de una tarjeta oscura, y el
 * síntoma va a aparecer lejos de la causa. Este scaffold es el molde de once
 * sistemas: la trampa se copia once veces.
 */
it('la pantalla del segundo factor no pinta elementos que no son suyos', function () {
    $vista = (string) file_get_contents(
        resource_path('views/livewire/verificar-mfa.blade.php')
    );

    preg_match('#<style>(.*?)</style>#s', $vista, $bloque);

    expect($bloque[1] ?? null)->not->toBeNull(
        'no encontré el bloque <style> de la vista: si el estilo se movió, hay que '
        .'actualizar esta comprobación o se apaga sola'
    );

    // Un selector por regla: lo que va antes de la llave que abre, ya sin
    // comentarios y separando las listas de selectores por coma.
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $bloque[1]);

    preg_match_all('#([^{}]+)\{#', $css, $encontrados);

    $selectores = [];

    foreach ($encontrados[1] as $grupo) {
        foreach (explode(',', $grupo) as $selector) {
            $selector = trim($selector);

            if ($selector !== '') {
                $selectores[] = $selector;
            }
        }
    }

    expect($selectores)->not->toBeEmpty('no extraje ningún selector: revisa el formato del bloque');

    foreach ($selectores as $selector) {
        // Cada selector tiene que empezar por una clase propia. Basta con mirar
        // el principio: «.mfa-card h2» sí acota, «h2» no.
        expect(str_starts_with($selector, '.'))->toBeTrue(
            "«{$selector}» es un selector suelto: pinta ese elemento en toda la página que "
            .'monte el componente, no solo dentro de la tarjeta. Acótalo con una clase propia.'
        );
    }
});
