<?php

use App\Notifications\Auth\BienvenidaUsuarioNotification;
use App\Notifications\Auth\MfaCodeNotification;
use App\Notifications\Auth\RestablecerContrasenaNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Lo que lleva un secreto no viaja por la cola.
 *
 * Una notificación encolada guarda sus argumentos serializados en Redis
 * mientras espera, y si el envío falla acaba en la tabla «failed_jobs», donde
 * se queda hasta que alguien la borre. Para un correo de bienvenida da igual;
 * para el código del segundo factor o el testigo de restablecimiento significa
 * dejar la llave escrita en dos sitios más, y en uno de ellos para siempre.
 *
 * Estas dos se envían en el acto, que además es lo que corresponde: quien está
 * esperando el código lo necesita ahora, no cuando el trabajador lo tome.
 *
 * Si alguien añade «implements ShouldQueue» para «que no bloquee», este test se
 * pone rojo y explica por qué no.
 */
it('el código del segundo factor no se encola', function () {
    expect(is_subclass_of(MfaCodeNotification::class, ShouldQueue::class))
        ->toBeFalse('El código del segundo factor quedaría serializado en Redis y en failed_jobs');
});

it('el testigo de restablecimiento no se encola', function () {
    expect(is_subclass_of(RestablecerContrasenaNotification::class, ShouldQueue::class))
        ->toBeFalse('El testigo de restablecimiento quedaría serializado en Redis y en failed_jobs');
});

it('lo que sí se encola no lleva secretos dentro', function () {
    // La de bienvenida sí encola, y está bien: no lleva contraseña ni testigo.
    $constructor = (new ReflectionClass(BienvenidaUsuarioNotification::class))
        ->getConstructor();

    $parametros = collect($constructor?->getParameters() ?? [])
        ->map(fn (ReflectionParameter $p): string => strtolower($p->getName()));

    foreach (['password', 'contrasena', 'clave', 'token', 'codigo', 'secreto'] as $peligroso) {
        expect($parametros)->not->toContain($peligroso);
    }
});
