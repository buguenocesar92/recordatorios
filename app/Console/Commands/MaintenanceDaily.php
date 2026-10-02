<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WaitlistSignup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mantenimiento diario (spec §8.4). En la fase 0 solo purga la lista de espera:
 * el dato no confirmado no se conserva más allá de las 72 h. En la V1 crece
 * (tokens de Telegram, exportaciones, borrados de cuenta, métricas).
 *
 * También recorta `failed_jobs` para el correo de confirmación: su carga ya no
 * lleva el correo ni el token, pero apunta a una inscripción, y no hay motivo
 * para guardarla la semana que dura la purga general: 24 h, así que con la
 * corrida diaria nunca pasa de 48 h (menos que las 72 h de la inscripción).
 *
 * Al registro va solo la cantidad, nunca un correo.
 */
final class MaintenanceDaily extends Command
{
    protected $signature = 'maintenance:daily';

    protected $description = 'Mantenimiento diario: purga las inscripciones sin confirmar vencidas.';

    public function handle(): int
    {
        $borradas = WaitlistSignup::query()->expiredUnconfirmed()->delete();

        $this->info("Inscripciones sin confirmar purgadas: {$borradas}");

        $fallidos = DB::table('failed_jobs')
            ->where('payload', 'like', '%EnviarConfirmacionDeLista%')
            ->where('failed_at', '<', now()->subHours((int) config('waitlist.failed_job_retention_hours', 24)))
            ->delete();

        $this->info("Envíos de confirmación fallidos purgados: {$fallidos}");

        return self::SUCCESS;
    }
}
