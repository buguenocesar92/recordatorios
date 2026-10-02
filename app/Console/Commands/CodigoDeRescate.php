<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Muestra por pantalla el código del segundo factor de una persona.
 *
 * Es la salida de emergencia para cuando el correo no sale y nadie puede entrar
 * al panel: el secreto de la aplicación venció, Microsoft está caído, alguien se
 * equivocó en una variable. Sin esto, un problema de correo deja el sistema
 * cerrado y no hay forma de abrirlo desde adentro.
 *
 *   php artisan panel:codigo-de-rescate persona@municipalidadgraneros.cl
 *
 * Las alternativas que existían eran peores. Apagar el segundo factor deja a
 * TODO el mundo entrando solo con contraseña, y mostrar el código en pantalla lo
 * muestra para cualquiera que llegue al formulario, además de escribirlo en el
 * log. Esto es para una persona, por una vez, y no queda escrito en ninguna
 * parte.
 *
 * No es un agujero: para llegar acá hay que tener acceso al servidor, y quien lo
 * tiene ya puede leer la base de datos donde el código está guardado. Lo que
 * cambia es que ahora hay una forma prevista de hacerlo, en vez de que alguien
 * improvise con la configuración a las tres de la mañana.
 */
class CodigoDeRescate extends Command
{
    protected $signature = 'panel:codigo-de-rescate {correo : La dirección de quien está intentando entrar}';

    protected $description = 'Muestra el código del segundo factor de una persona, para cuando el correo no está saliendo.';

    public function handle(): int
    {
        $correo = (string) $this->argument('correo');
        $usuario = User::where('email', $correo)->first();

        if (! $usuario) {
            $this->line('');
            $this->line("  <fg=red>No hay ninguna cuenta con la dirección {$correo}.</>");
            $this->line('');

            return self::FAILURE;
        }

        // Se reutiliza el código vigente en vez de generar otro. Si la persona
        // ya tiene uno esperando, cambiárselo desde acá haría que el que está
        // mirando en su pantalla deje de servir justo cuando lo necesita.
        $vigente = $usuario->two_factor_code !== null
            && $usuario->two_factor_expires_at !== null
            && now()->lt($usuario->two_factor_expires_at);

        if (! $vigente) {
            $usuario->two_factor_code = (string) random_int(100000, 999999);
            // Como cadena y no como objeto de fecha: la columna tiene un cast
            // que convierte al leerla, pero el tipo declarado de la propiedad
            // es de texto y el analizador tiene razón en reclamarlo.
            $usuario->two_factor_expires_at = now()->addMinutes(10)->toDateTimeString();
            $usuario->save();
        }

        $minutos = (int) ceil(now()->diffInSeconds($usuario->two_factor_expires_at, false) / 60);

        $this->line('');
        $this->line("  Código de <options=bold>{$usuario->email}</>");
        $this->line('');
        $this->line("      <fg=green;options=bold>{$usuario->two_factor_code}</>");
        $this->line('');
        $this->line("  Vence en {$minutos} minuto".($minutos === 1 ? '' : 's').'.');
        $this->line('');
        $this->line('  <options=bold>Dáselo solo a esa persona, y por un medio que no quede escrito.</>');
        $this->line('  Si la que lo pide es otra, o te lo piden por teléfono sin que');
        $this->line('  nadie estuviera intentando entrar, no lo entregues: así es como');
        $this->line('  se saltan los segundos factores.');
        $this->line('');
        $this->line('  Si esto hizo falta porque el correo no sale: php artisan correo:probar');
        $this->line('');

        return self::SUCCESS;
    }
}
