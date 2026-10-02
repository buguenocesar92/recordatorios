<?php

/**
 * El campo del segundo factor no puede pedirle al navegador que se calle.
 *
 * Traía `autocomplete="off"`, que no es neutro: le PROHÍBE al navegador y al
 * sistema operativo ofrecer el código que acaba de llegar por SMS o por correo.
 * Es exactamente la ayuda que hace falta en esta pantalla, y la que evita que la
 * gente se equivoque tecleando seis dígitos a mano. `one-time-code` es el valor
 * que existe para esto.
 *
 * `inputmode="numeric"` abre el teclado numérico en el teléfono. Se queda en
 * `type="text"` a propósito: `type="number"` trae flechas de incremento, admite
 * notación exponencial y pierde los ceros a la izquierda de un código como
 * 000123.
 *
 * Decreto N°1/2015 SEGPRES → WCAG 2.2 AA, 1.3.5 «Identificar el propósito de la
 * entrada».
 */
it('el campo del código de un solo uso deja que el navegador lo ofrezca', function () {
    $vista = (string) file_get_contents(resource_path('views/livewire/verificar-mfa.blade.php'));

    preg_match('#<input[^>]*id="code"[^>]*>#', $vista, $campo);

    expect($campo)->not->toBeEmpty('no se encontró el campo del código: ¿cambió la vista?');

    expect(str_contains($campo[0], 'autocomplete="one-time-code"'))->toBeTrue(
        'el campo del segundo factor no declara `one-time-code`: el navegador no ofrecerá el código recibido'
    );
    expect(str_contains($campo[0], 'autocomplete="off"'))->toBeFalse(
        '`autocomplete="off"` le PROHÍBE al navegador ofrecer el código; es peor que no declarar nada'
    );
    expect(str_contains($campo[0], 'inputmode="numeric"'))->toBeTrue(
        'sin `inputmode="numeric"` el teléfono abre el teclado alfabético para seis dígitos'
    );
    expect(str_contains($campo[0], 'type="number"'))->toBeFalse(
        '`type="number"` perdería los ceros a la izquierda de un código como 000123'
    );
});
