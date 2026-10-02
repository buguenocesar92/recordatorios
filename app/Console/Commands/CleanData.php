<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Vacía los datos operativos/temporales del sistema (jobs, logs, progresos)
 * conservando lo esencial (usuarios, roles, permisos).
 *
 *   php artisan env:clean-data              # pide confirmación
 *   php artisan env:clean-data --force      # sin preguntar
 */
class CleanData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'env:clean-data
        {--force : Ejecutar sin confirmación interactiva}
        {--auditoria : Vaciar también el registro de auditoría (activity_log)}';

    protected $description = 'Vacía los datos operativos (jobs, logs de progreso) conservando usuarios, roles/permisos.';

    private const TABLAS = [
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $tablas = self::TABLAS;
        if ($this->option('auditoria')) {
            $tablas[] = 'activity_log';
        }

        $this->warn('Se vaciarán estas tablas operativas (se CONSERVAN usuarios, roles, permisos y tours/pasos):');
        $total = 0;
        foreach ($tablas as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                $n = DB::table($t)->count();
                $total += $n;
                $this->line(sprintf('  - %-28s %s filas', $t, number_format($n, 0, ',', '.')));
            }
        }

        if ($total === 0) {
            $this->info('No hay datos operativos que limpiar.');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tablas as $t) {
                if (DB::getSchemaBuilder()->hasTable($t)) {
                    DB::table($t)->delete();
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Limpiar caché
        $this->info('🧹 Limpiando caché de Laravel...');
        Artisan::call('cache:clear');

        $this->newLine();
        $this->info("Listo. Se vaciaron {$total} filas de datos operativos.");

        return self::SUCCESS;
    }
}
