<?php

use Kraftdo\Shared\Persona\PersonaResolverInterface;
use App\Services\PersonaResolver\ApiPersonaResolver;
use App\Services\PersonaResolver\LocalPersonaResolver;

/**
 * El contrato para resolver una persona por su RUT.
 *
 * Las dos implementaciones que trae el scaffold son PLANTILLAS: el cuerpo real
 * viene comentado y devuelven «no encontrado» siempre. Es deliberado —cada
 * sistema resuelve contra su propia fuente— pero es una trampa silenciosa: quien
 * inyecte la interfaz y no la implemente no recibe un error, recibe un mundo
 * donde no existe ninguna persona.
 *
 * Por eso lo que se comprueba acá no es «devuelve null», que dejaría de valer en
 * cuanto alguien la implemente, sino la INVARIANTE que tiene que cumplirse en
 * las dos épocas: existsByRut() y findByRut() no pueden contradecirse. Si una
 * dice que la persona existe y la otra no la encuentra, el sistema de arriba
 * hace cosas incoherentes —por ejemplo, rechazar un ingreso por duplicado y
 * enseguida no poder mostrar el duplicado.
 */
covers(LocalPersonaResolver::class, ApiPersonaResolver::class);

dataset('resolvedores', [
    'local' => [fn () => new LocalPersonaResolver, 'local'],
    'api' => [fn () => new ApiPersonaResolver, 'api'],
]);

it('cumple el contrato de la interfaz', function (Closure $crear, string $fuente) {
    $resolvedor = $crear();

    expect($resolvedor)->toBeInstanceOf(PersonaResolverInterface::class)
        ->and($resolvedor->getSource())->toBe($fuente);
})->with('resolvedores');

it('nunca se contradice: lo que dice existir tiene que poder encontrarse', function (Closure $crear) {
    $resolvedor = $crear();

    // RUTs de forma variada, incluida la basura: el contrato debe sostenerse
    // igual, sin excepciones ni respuestas a medias.
    $ruts = ['11111111-1', '5.126.663-3', '', 'no-es-un-rut', '99999999-9'];

    foreach ($ruts as $rut) {
        $existe = $resolvedor->existsByRut($rut);
        $encontrada = $resolvedor->findByRut($rut);

        expect($existe)->toBe(
            $encontrada !== null,
            "Para «{$rut}», existsByRut() dijo ".var_export($existe, true).
            ' y findByRut() '.($encontrada === null ? 'no encontró nada' : 'sí encontró')
        );
    }
})->with('resolvedores');

it('el que resuelve personas en este proyecto es el local', function () {
    // Si alguien cambia el enlace del contenedor, que sea a propósito: de esto
    // depende de dónde salen los datos personales que muestra el sistema.
    expect(app(PersonaResolverInterface::class))->toBeInstanceOf(LocalPersonaResolver::class);
});

it('la plantilla todavía no resuelve nada, y eso debe ser una decisión y no un olvido', function () {
    /*
     * Este test es un recordatorio, no una comprobación de comportamiento.
     * Cuando un proyecto implemente el resolvedor, va a fallar — y ahí toca
     * borrarlo, no ajustarlo: la invariante de más arriba es la que sigue
     * cuidando el contrato.
     */
    $local = new LocalPersonaResolver;

    expect($local->findByRut('11111111-1'))->toBeNull(
        'El resolvedor ya resuelve personas: borra esta prueba y cubre el caso real'
    );
});
