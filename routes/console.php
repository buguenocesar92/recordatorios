<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldos de base de datos automáticos (spatie/laravel-backup).
// Limpia respaldos antiguos y genera un nuevo respaldo diariamente.
Schedule::command('backup:clean')
    ->dailyAt('01:00')
    ->withoutOverlapping();

Schedule::command('backup:run', ['--only-db'])
    ->dailyAt('01:30')
    ->withoutOverlapping();

/*
 * El vigilante del respaldo: lo único que avisa cuando el respaldo NO corrió.
 *
 * «backup:run» que falla ya manda su aviso. «backup:run» que nunca se ejecuta
 * —cron detenido, contenedor caído, planificador muerto, disco lleno— no manda
 * nada, porque no hubo corrida que fallara. «backup:monitor» mira la edad y el
 * peso del respaldo más nuevo y emite «UnhealthyBackupWasFound» cuando el más
 * reciente ya es viejo: es lo único que convierte la ausencia total de
 * respaldos —el peor modo de fallar, el que se descubre el día que hay que
 * restaurar— en un correo.
 *
 * A las 08:30 y no de madrugada, por dos motivos:
 *
 * - DESPUÉS del respaldo de la 01:30, con siete horas de margen. Pegado al
 *   respaldo se arriesga a leer un archivo a medio escribir y reprobar un
 *   sistema sano; ANTES compararía contra el respaldo de ayer y avisaría un día
 *   tarde, cuando ya se perdieron dos noches.
 * - Dentro del horario de oficina. «MaximumAgeInDays => 1» da 24 horas de
 *   margen, así que a las 08:30 el aviso llega el MISMO día en que faltó el
 *   respaldo y con alguien delante para atenderlo; a las 02:00 llegaría igual
 *   de a tiempo pero nadie lo leería hasta la mañana.
 */
Schedule::command('backup:monitor')
    ->dailyAt('08:30')
    ->withoutOverlapping();

// Los trabajos que fallaron no se guardan para siempre.
//
// Un trabajo fallido conserva sus argumentos serializados en la tabla
// «failed_jobs» hasta que alguien los borre a mano, y nadie lo hace nunca. Con
// el tiempo son miles de filas que además pueden contener datos personales de
// quien disparó la acción, guardados sin plazo ni motivo: exactamente lo que la
// Ley 21.719 llama conservar más de lo necesario.
//
// Una semana alcanza de sobra para investigar por qué falló algo.
Schedule::command('queue:prune-failed', ['--hours=168'])
    ->weekly()
    ->withoutOverlapping();

// Lo mismo para la actividad del panel: el historial es útil, pero un año atrás
// deja de responder preguntas y pasa a ser solo un archivo de datos personales.
Schedule::command('activitylog:clean')
    ->weekly()
    ->withoutOverlapping();
