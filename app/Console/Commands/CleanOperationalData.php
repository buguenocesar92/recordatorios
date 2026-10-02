<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Vacía los DATOS operativos del sistema dejando intacta la configuración.
 */
class CleanOperationalData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'app:clear-operational-data
        {--force : Ejecutar sin confirmación interactiva (necesario en producción)}
        {--auditoria : Vaciar también el registro de auditoría (activity_log)}';

    protected $description = 'Vacía los datos operativos de las tablas del sistema.';

    /**
     * Lista de tablas con datos operativos a limpiar.
     */
    private const TABLAS = [
        // 'atenciones',
        // 'personas',
        // Agregar aquí las tablas transaccionales de tu nuevo sistema
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

        if (empty($tablas)) {
            $this->info('No hay tablas configuradas para limpiar en CleanOperationalData.');

            return self::SUCCESS;
        }

        $this->warn('Se vaciarán estas tablas transaccionales:');
        $total = 0;
        foreach ($tablas as $t) {
            $n = DB::table($t)->count();
            $total += $n;
            $this->line(sprintf('  - %-28s %s filas', $t, number_format($n, 0, ',', '.')));
        }

        if ($total === 0) {
            $this->info('No hay datos transaccionales que limpiar.');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tablas as $t) {
                DB::table($t)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info("Listo. Se vaciaron {$total} filas de datos operativos.");

        return self::SUCCESS;
    }
}
