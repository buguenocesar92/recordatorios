<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * Nada se guarda para siempre porque nadie se acordó de borrarlo.
 *
 * Un trabajo fallido conserva sus argumentos serializados hasta que alguien los
 * borre a mano, y nadie lo hace nunca; el historial del panel crece igual. Con
 * el tiempo son miles de filas que pueden contener datos personales guardados
 * sin plazo ni motivo, que es justo lo que la Ley 21.719 llama conservar más de
 * lo necesario.
 */
it('los trabajos fallidos se podan solos', function () {
    $comandos = collect(app(Schedule::class)->events())
        ->map(fn ($evento): string => (string) $evento->command);

    expect($comandos->filter(fn (string $c): bool => str_contains($c, 'queue:prune-failed')))
        ->not->toBeEmpty('Sin poda, failed_jobs crece sin plazo');
});

it('el historial de actividad se poda solo', function () {
    $comandos = collect(app(Schedule::class)->events())
        ->map(fn ($evento): string => (string) $evento->command);

    expect($comandos->filter(fn (string $c): bool => str_contains($c, 'activitylog:clean')))
        ->not->toBeEmpty('Sin poda, el historial guarda datos personales sin plazo');
});

it('los respaldos también se limpian', function () {
    $comandos = collect(app(Schedule::class)->events())
        ->map(fn ($evento): string => (string) $evento->command);

    expect($comandos->filter(fn (string $c): bool => str_contains($c, 'backup:clean')))
        ->not->toBeEmpty();
});
