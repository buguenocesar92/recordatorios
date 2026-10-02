<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

/*
 * La aplicación tiene que pensar en la hora de Chile.
 *
 * Desde Laravel 11 el scaffold no trae `config/app.php`, y en la configuración
 * por defecto del framework `timezone` es la ÚNICA clave escrita a mano
 * —`'timezone' => 'UTC'`—; las demás sí leen del `.env`. Así, el
 * `APP_TIMEZONE=America/Santiago` del `.env` se ignoraba en silencio.
 *
 * Peor que no tenerlo declarado: todos asumen que aplica.
 */
it('la aplicación corre en la hora de Chile', function () {
    expect(config('app.timezone'))->toBe('America/Santiago')
        ->and(now()->timezone->getName())->toBe('America/Santiago');
});

/*
 * El candado de verdad: la diferencia se ve al cruzar la medianoche.
 *
 * Las 21:00 del 1 de agosto en Chile son las 01:00 del 2 de agosto en UTC.
 * Corriendo en UTC, todo lo que pasaba de noche quedaba contado al día
 * siguiente: reportes diarios, cierres de jornada y cualquier `whereDate`.
 */
it('un hecho de las 21:00 pertenece a ese día y no al siguiente', function () {
    $this->travelTo(Carbon::parse('2026-08-02 01:00:00', 'UTC'));

    expect(now()->format('Y-m-d H:i'))->toBe('2026-08-01 21:00')
        ->and(now()->toDateString())->toBe('2026-08-01');
});

/*
 * Chile cambia de hora dos veces al año. Se prueban los dos lados para que
 * nadie “arregle” esto con un desfase fijo de 3 o 4 horas.
 */
it('sigue el horario de verano y el de invierno', function () {
    $this->travelTo(Carbon::parse('2026-01-15 12:00:00', 'UTC'));
    $verano = now()->format('P');

    $this->travelTo(Carbon::parse('2026-07-15 12:00:00', 'UTC'));
    $invierno = now()->format('P');

    expect($verano)->toBe('-03:00')
        ->and($invierno)->toBe('-04:00');
});
