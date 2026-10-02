<?php

use App\Support\VerificadorDeFiltraciones;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Qué contraseña se acepta.
 *
 * Sin política, el mínimo de Laravel son ocho caracteres y cualquier cosa entra.
 * Doce con letras y números es lo que encarece adivinarla; la comprobación
 * contra listas de contraseñas filtradas no se hace en las pruebas, porque
 * depende de la red y un rojo por ahí no significaría nada.
 *
 * Qué pasa con esa comprobación cuando la red no está —que es el caso que de
 * verdad importa— se prueba aparte, en «ComprobacionDeFiltracionesTest».
 */
it('rechaza una contraseña de diez caracteres, que el mínimo de fábrica aceptaría', function () {
    // Diez con letras y números pasa el mínimo de Laravel (ocho). Si este test
    // se escribiera con siete caracteres, pasaría sin política ninguna y no
    // comprobaría nada.
    $falla = Validator::make(
        ['clave' => 'Corta12345'],
        ['clave' => ['required', Password::defaults()]],
    )->fails();

    expect($falla)->toBeTrue();
});

it('rechaza una contraseña larga sin números', function () {
    $falla = Validator::make(
        ['clave' => 'solamenteletras'],
        ['clave' => ['required', Password::defaults()]],
    )->fails();

    expect($falla)->toBeTrue();
});

it('acepta una contraseña larga con letras y números', function () {
    $falla = Validator::make(
        ['clave' => 'caballo-correcto-42'],
        ['clave' => ['required', Password::defaults()]],
    )->fails();

    expect($falla)->toBeFalse();
});

it('fuera de las pruebas se comprueba contra listas de contraseñas filtradas', function () {
    // La comprobación manda solo un fragmento del resumen criptográfico, nunca
    // la contraseña. Acá se comprueba de verdad —no leyendo el archivo—: con el
    // modo puesto en cualquier cosa que no sea 'apagado', la política tiene que
    // llevar la regla.
    config()->set('seguridad.filtraciones.modo', VerificadorDeFiltraciones::CERRADO);

    expect(Password::defaults()->appliedRules()['uncompromised'])->toBeTrue();
});
