<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

/**
 * Enciende, apaga o restablece un interruptor de features para TODO el sistema.
 *
 *   php artisan feature asistente-ia apagada
 *
 * Es la forma prevista de apagar algo en producción sin redeploy y sin tocar el
 * .env: escribe la fila global en la tabla `features` y Octane lo ve en la
 * petición siguiente. «por-defecto» borra la fila y la feature vuelve al valor
 * de config/features.php.
 *
 * Solo acepta features declaradas en config/features.php: un error de tipeo
 * crearía un interruptor fantasma que nadie lee, y quien lo apagó creería que
 * apagó algo.
 */
class FeatureCommand extends Command
{
    private const ESTADOS = ['encendida', 'apagada', 'por-defecto'];

    protected $signature = 'feature {nombre : La feature, tal como está en config/features.php} {estado : encendida | apagada | por-defecto}';

    protected $description = 'Enciende, apaga o restablece un interruptor de features para todo el sistema, sin redeploy.';

    public function handle(): int
    {
        // El tipo de «argument()» se amplió en Laravel 13.37 (puede devolver un
        // array). Estos dos son de texto, así que se estrechan antes de usarse.
        $nombre = $this->argument('nombre');
        $estado = $this->argument('estado');

        if (! is_string($nombre) || ! is_string($estado)) {
            $this->error('El nombre de la feature y su estado tienen que ser texto.');

            return self::FAILURE;
        }

        /** @var array<string, bool> $declaradas */
        $declaradas = config('features', []);

        if (! array_key_exists($nombre, $declaradas)) {
            $this->error("«{$nombre}» no está declarada en config/features.php.");
            $this->line('  Declaradas: '.implode(', ', array_keys($declaradas)));

            return self::FAILURE;
        }

        if (! in_array($estado, self::ESTADOS, true)) {
            $this->error("«{$estado}» no es un estado. Vale: ".implode(' | ', self::ESTADOS).'.');

            return self::FAILURE;
        }

        match ($estado) {
            'encendida' => Feature::activate($nombre),
            'apagada' => Feature::deactivate($nombre),
            'por-defecto' => Feature::purge($nombre),
        };

        // No se consulta Feature::active() después de borrar la fila: Pennant
        // guarda lo que resuelve, y la fila que se acaba de borrar volvería a
        // aparecer con el valor de configuración. Se informa desde la fuente.
        $vigente = match ($estado) {
            'por-defecto' => (bool) config("features.{$nombre}") ? 'encendida' : 'apagada',
            default => $estado,
        };

        $this->info("«{$nombre}» queda {$vigente} para todo el sistema".($estado === 'por-defecto' ? ' (valor de config/features.php)' : '').'.');
        $this->line('  Bajo Octane se aplica en la petición siguiente: no hace falta reiniciar.');

        return self::SUCCESS;
    }
}
