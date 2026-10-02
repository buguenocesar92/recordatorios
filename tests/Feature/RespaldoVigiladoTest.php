<?php

use Illuminate\Console\Scheduling\Schedule;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;

/*
 * El respaldo que NO corre no le avisa a nadie.
 *
 * `backup:run` que FALLA ya emite «BackupHasFailedNotification», y eso funciona
 * desde siempre. `backup:run` que nunca llegó a ejecutarse —cron detenido,
 * contenedor caído, planificador muerto, disco lleno— no emite absolutamente
 * nada, porque no hubo corrida que fallara. El único comando que mira la EDAD
 * del respaldo más nuevo, y por lo tanto el único capaz de convertir la
 * ausencia total de respaldos en un correo, es «backup:monitor».
 *
 * Si estas pruebas fallan es porque el sistema volvió a quedar como estaba: la
 * ausencia de respaldos deja de avisarse y se descubre el día que hay que
 * restaurar, que es el peor día posible para descubrirla.
 */
it('agenda backup:monitor', function () {
    $agendado = collect(app(Schedule::class)->events())
        ->contains(fn ($evento): bool => str_contains((string) $evento->command, 'backup:monitor'));

    expect($agendado)->toBeTrue(
        'Sin backup:monitor en el planificador, la ausencia de respaldos deja de avisarse: '
        .'un backup:run que no corre no emite ningún aviso, y el vacío recién se descubre al restaurar.'
    );
});

it('vigila después del respaldo del día y en horario de oficina', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($evento): bool => str_contains((string) $evento->command, 'backup:monitor'));

    expect($evento)->not->toBeNull(
        'Sin backup:monitor en el planificador, la ausencia de respaldos deja de avisarse.'
    );

    expect($evento->expression)->toBe(
        '30 8 * * *',
        'Antes del respaldo de la 01:30 el monitor compara contra el de ayer y avisa un día tarde; '
        .'pegado a él puede leer un archivo a medio escribir y reprobar un sistema sano.'
    );
});

/*
 * Un monitor mal configurado es peor que ninguno: el comando corre, termina
 * bien y no vigila nada. Que los discos vigilados sean los MISMOS donde se
 * escribe el respaldo es lo que separa a las dos situaciones.
 */
it('el monitor vigila los discos donde de verdad se escribe el respaldo', function () {
    $monitores = config('backup.monitor_backups');

    expect($monitores)->not->toBeEmpty(
        'Sin monitores declarados, backup:monitor corre, no vigila nada y la ausencia de respaldos sigue sin avisarse.'
    );

    expect($monitores[0]['disks'])->toBe(
        config('backup.backup.destination.disks'),
        'El monitor mira otros discos que los del respaldo: vigilaría un lugar donde nunca se escribe nada.'
    );

    expect($monitores[0]['health_checks'])->not->toBeEmpty(
        'Un monitor sin health_checks nunca reprueba, y la ausencia de respaldos deja de avisarse.'
    );
});

/*
 * Un aviso que no sale por ningún canal es lo mismo que no tenerlo: el comando
 * corre, detecta el respaldo viejo, emite la notificación y nadie la recibe.
 */
it('el aviso de respaldo enfermo sale por algún canal', function () {
    $canales = config('backup.notifications.notifications')[UnhealthyBackupWasFoundNotification::class] ?? null;

    expect($canales)->not->toBeEmpty(
        'UnhealthyBackupWasFound sin canales: backup:monitor detectaría la ausencia de respaldos '
        .'y no se lo diría a nadie.'
    );
});
