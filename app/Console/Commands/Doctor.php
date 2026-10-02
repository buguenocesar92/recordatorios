<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Diagnóstico del entorno: verifica que todo lo necesario para correr el
 * scaffold esté en su lugar (extensiones PHP, .env, BD, Redis, storage…).
 *
 *   php artisan app:doctor
 *
 * Devuelve código de salida 1 si hay alguna verificación crítica fallida,
 * útil para usarlo en pipelines o antes de arrancar.
 */
class Doctor extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Diagnostica el entorno: extensiones, configuración, BD, Redis y storage.';

    /** @var array<int, array{0:string,1:string,2:string}> filas: [estado, check, detalle] */
    private array $rows = [];

    private bool $failed = false;

    public function handle(): int
    {
        $this->info('🩺 Diagnóstico del entorno');
        $this->newLine();

        $this->checkPhp();
        $this->checkExtensions();
        $this->checkEnv();
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkStorage();

        $this->table(['', 'Verificación', 'Detalle'], $this->rows);
        $this->newLine();

        if ($this->failed) {
            $this->error('Hay verificaciones críticas fallidas. Revisá los ✘ de arriba.');

            return self::FAILURE;
        }

        $this->info('✅ Todo en orden.');

        return self::SUCCESS;
    }

    private function markPass(string $check, string $detail = ''): void
    {
        $this->rows[] = ['<fg=green>✔</>', $check, $detail];
    }

    private function markFail(string $check, string $detail = ''): void
    {
        $this->failed = true;
        $this->rows[] = ['<fg=red>✘</>', $check, $detail];
    }

    private function markWarn(string $check, string $detail = ''): void
    {
        $this->rows[] = ['<fg=yellow>!</>', $check, $detail];
    }

    private function checkPhp(): void
    {
        // phpversion() (no la constante PHP_VERSION) para que sea un chequeo de
        // runtime real: PHPStan constant-foldea PHP_VERSION y lo daría por tautológico.
        $version = (string) phpversion();

        version_compare($version, '8.4.0', '>=')
            ? $this->markPass('PHP', $version)
            : $this->markFail('PHP', $version.' (se requiere >= 8.4)');
    }

    private function checkExtensions(): void
    {
        foreach (['pdo_mysql', 'redis', 'gd', 'intl', 'zip', 'pcntl', 'mbstring'] as $ext) {
            extension_loaded($ext)
                ? $this->markPass("ext: {$ext}")
                : $this->markFail("ext: {$ext}", 'no cargada');
        }

        // opcache es de rendimiento; suele estar deshabilitado en CLI.
        extension_loaded('opcache') || extension_loaded('Zend OPcache')
            ? $this->markPass('ext: opcache')
            : $this->markWarn('ext: opcache', 'no cargada en CLI (recomendada en producción)');
    }

    private function checkEnv(): void
    {
        file_exists(base_path('.env'))
            ? $this->markPass('.env', 'presente')
            : $this->markFail('.env', 'ausente — copiá .env.example');

        config('app.key')
            ? $this->markPass('APP_KEY', 'definida')
            : $this->markFail('APP_KEY', 'vacía — corré php artisan key:generate');
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $name = DB::connection()->getDatabaseName();

            try {
                $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
                    ->diff(app('migrator')->getRepository()->getRan())->count();
                $pending === 0
                    ? $this->markPass('Base de datos', "conectada ({$name}), sin migraciones pendientes")
                    : $this->markWarn('Base de datos', "conectada ({$name}), {$pending} migración(es) pendiente(s)");
            } catch (Throwable) {
                $this->markPass('Base de datos', "conectada ({$name})");
            }
        } catch (Throwable $e) {
            $this->markFail('Base de datos', $e->getMessage());
        }
    }

    private function checkRedis(): void
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            $this->markWarn('Redis', 'no configurado como cache/queue (omitido)');

            return;
        }

        try {
            Cache::store('redis')->put('doctor:ping', '1', 5);
            Cache::store('redis')->get('doctor:ping') === '1'
                ? $this->markPass('Redis', 'conectado')
                : $this->markFail('Redis', 'respuesta inesperada');
        } catch (Throwable $e) {
            $this->markFail('Redis', $e->getMessage());
        }
    }

    private function checkStorage(): void
    {
        is_writable(storage_path())
            ? $this->markPass('storage/', 'escribible')
            : $this->markFail('storage/', 'sin permisos de escritura');

        file_exists(public_path('storage'))
            ? $this->markPass('storage:link', 'presente')
            : $this->markWarn('storage:link', 'ausente — corré php artisan storage:link');
    }
}
