<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WaitlistSignup;
use App\Waitlist\FechaDePurgaDeLaLista;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantenimiento diario (spec §8.4). En la fase 0 purga la lista de espera: el
 * dato no confirmado no se conserva más allá de las 72 h, el hash de un token
 * ya usado se borra 72 h después de confirmar, y desde WAITLIST_PURGE_AFTER
 * («lanzamiento más seis meses», lo que promete el aviso) se borra también lo
 * confirmado. En la V1 crece
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

    protected $description = 'Mantenimiento diario: purga la lista de espera según los plazos del aviso de privacidad.';

    public function handle(): int
    {
        $borradas = WaitlistSignup::query()->expiredUnconfirmed()->delete();

        $this->info("Inscripciones sin confirmar purgadas: {$borradas}");

        $tokens = WaitlistSignup::query()
            ->whereNotNull('confirmed_at')
            ->whereNotNull('confirm_token_hash')
            ->where('confirmed_at', '<', now()->subHours((int) config('waitlist.used_token_retention_hours', 72)))
            ->update(['confirm_token_hash' => null, 'confirm_expires_at' => null]);

        $this->info("Hashes de token ya usados borrados: {$tokens}");

        $limite = FechaDePurgaDeLaLista::desdeConfiguracion();

        if ($limite !== null && now()->greaterThanOrEqualTo($limite)) {
            $confirmadas = WaitlistSignup::query()->whereNotNull('confirmed_at')->delete();
            $this->info("Inscripciones confirmadas purgadas (WAITLIST_PURGE_AFTER): {$confirmadas}");
        } elseif ($limite === null && filled(config('waitlist.purge_confirmed_after'))) {
            Log::error('maintenance:daily: WAITLIST_PURGE_AFTER ilegible (formato AAAA-MM-DD); no se purgó lo confirmado');
        }

        $fallidos = DB::table('failed_jobs')
            ->where('payload', 'like', '%EnviarConfirmacionDeLista%')
            ->where('failed_at', '<', now()->subHours((int) config('waitlist.failed_job_retention_hours', 24)))
            ->delete();

        $this->info("Envíos de confirmación fallidos purgados: {$fallidos}");

        return self::SUCCESS;
    }
}
