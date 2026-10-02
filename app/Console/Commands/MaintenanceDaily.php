<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WaitlistSignup;
use Illuminate\Console\Command;

/**
 * Mantenimiento diario (spec §8.4). En la fase 0 solo purga la lista de espera:
 * el dato no confirmado no se conserva más allá de las 72 h. En la V1 crece
 * (tokens de Telegram, exportaciones, borrados de cuenta, métricas).
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

        return self::SUCCESS;
    }
}
