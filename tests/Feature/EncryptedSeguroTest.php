<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Muni\Shared\Casts\EncryptedSeguro;

/**
 * El cast que guarda cifrados los datos personales sensibles.
 *
 * Estaba en 0 %, y es de las piezas donde eso menos se puede permitir: las
 * reglas del ecosistema mandan usarlo para todo dato sensible, y lo que este
 * archivo decide es si un dato de salud, una dirección o un documento quedan
 * cifrados en la base o legibles para cualquiera que la lea.
 *
 * Todavía ningún modelo del scaffold lo usa —está para cuando un sistema tenga
 * datos sensibles—, así que se prueba directamente sobre el cast.
 */
covers(EncryptedSeguro::class);

/** Un modelo cualquiera: el cast no lo usa, pero la interfaz lo exige. */
function modeloDePrueba(): Model
{
    return new class extends Model {};
}

it('lo que se guarda en la base NO es el texto original', function () {
    $cast = new EncryptedSeguro;
    $sensible = 'Diagnóstico: hipertensión arterial';

    $guardado = $cast->set(modeloDePrueba(), 'dato', $sensible, []);

    // Lo esencial: quien lea la tabla no puede leer el dato.
    expect($guardado)->not->toBe($sensible)
        ->and($guardado)->not->toContain('hipertensión')
        ->and($guardado)->not->toContain('Diagnóstico');
});

it('lo guardado se vuelve a leer idéntico', function (string $original) {
    $cast = new EncryptedSeguro;
    $modelo = modeloDePrueba();

    $guardado = $cast->set($modelo, 'dato', $original, []);

    expect($cast->get($modelo, 'dato', $guardado, []))->toBe($original);
})->with([
    'texto simple' => 'Calle Larga 123',
    'con tildes y ñ' => 'Añoranza, Peñaflor, José Muñoz',
    'con signos' => 'RUT 11.111.111-1 · tel +56 9 1234 5678',
    'con salto de línea' => "Primera línea\nSegunda línea",
    'con comillas' => 'Dijo "no" y firmó \'aquí\'',
    'emoji' => 'Observación: revisar 📋 mañana',
    'muy largo' => 'a', // se expande abajo
]);

it('aguanta un texto largo sin recortarlo', function () {
    $cast = new EncryptedSeguro;
    $modelo = modeloDePrueba();
    $largo = str_repeat('Observación clínica extensa. ', 200);

    $guardado = $cast->set($modelo, 'dato', $largo, []);

    expect($cast->get($modelo, 'dato', $guardado, []))->toBe($largo);
});

it('deja pasar el vacío sin cifrarlo', function (?string $vacio) {
    $cast = new EncryptedSeguro;
    $modelo = modeloDePrueba();

    // Cifrar el vacío llenaría la columna de basura para decir «no hay dato», y
    // rompería cualquier consulta que busque por nulo.
    expect($cast->set($modelo, 'dato', $vacio, []))->toBe($vacio)
        ->and($cast->get($modelo, 'dato', $vacio, []))->toBe($vacio);
})->with(['nulo' => null, 'cadena vacía' => '']);

it('el mismo dato guardado dos veces no produce el mismo texto cifrado', function () {
    $cast = new EncryptedSeguro;
    $modelo = modeloDePrueba();

    $uno = $cast->set($modelo, 'dato', 'Mismo dato', []);
    $otro = $cast->set($modelo, 'dato', 'Mismo dato', []);

    // Si fueran idénticos, alguien con acceso a la tabla podría deducir qué
    // filas comparten el mismo valor sin descifrar nada.
    expect($uno)->not->toBe($otro)
        ->and($cast->get($modelo, 'dato', $uno, []))->toBe('Mismo dato')
        ->and($cast->get($modelo, 'dato', $otro, []))->toBe('Mismo dato');
});

it('una fila vieja en texto plano se sigue leyendo, en vez de reventar la pantalla', function () {
    // Es la razón de existir de este cast en vez del «encrypted» de Laravel:
    // una fila anterior a la migración, o escrita por fuera del modelo, no
    // puede tumbar la vista de quien la abre.
    $cast = new EncryptedSeguro;

    expect($cast->get(modeloDePrueba(), 'dato', 'dato viejo sin cifrar', []))
        ->toBe('dato viejo sin cifrar');
});

it('un dato cifrado dos veces se lee mal, y conviene saberlo', function () {
    /*
     * El cast cifra SIEMPRE al escribir. Si un valor que ya viene cifrado se
     * vuelve a pasar por él —copiar los atributos crudos de un registro a otro,
     * por ejemplo— queda con dos capas, y al leerlo sale el texto cifrado
     * interior en lugar del dato.
     *
     * No se «arregla» detectando si el valor parece cifrado: eso rompería
     * guardar un dato que legítimamente lo parezca. Lo que corresponde es no
     * copiar atributos crudos entre modelos; queda escrito acá porque el
     * síntoma —un campo con texto ilegible en pantalla— no señala la causa.
     */
    $cast = new EncryptedSeguro;
    $modelo = modeloDePrueba();

    $unaVez = $cast->set($modelo, 'dato', 'Dato sensible', []);
    $dosVeces = $cast->set($modelo, 'dato', $unaVez, []);

    expect($cast->get($modelo, 'dato', $dosVeces, []))->toBe($unaVez)
        ->and($cast->get($modelo, 'dato', $dosVeces, []))->not->toBe('Dato sensible');
});

it('el cifrado usa la clave de la aplicación', function () {
    // Comprobación cruzada: lo que guarda el cast tiene que poder descifrarlo
    // el cifrador de Laravel. Si algún día el cast usara otra clave o algoritmo,
    // los datos dejarían de ser recuperables con las herramientas del framework.
    $cast = new EncryptedSeguro;

    $guardado = $cast->set(modeloDePrueba(), 'dato', 'Comprobación', []);

    expect(Crypt::decryptString((string) $guardado))->toBe('Comprobación');
});
