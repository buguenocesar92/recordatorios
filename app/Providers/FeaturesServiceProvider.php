<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

/**
 * Declara los interruptores de config/features.php ante Pennant.
 *
 * Dos decisiones que no son las de fábrica:
 *
 * - **Scope global por defecto.** Pennant usa al usuario autenticado como scope y
 *   guarda una fila por (feature, usuario). Para un interruptor del sistema eso
 *   es una trampa: «apagar para todos» solo toca las filas ya resueltas y cada
 *   usuario nuevo nace con el valor de configuración, o sea, encendido. Con el
 *   scope nulo hay UNA fila por feature y el comando la cambia para todos.
 *   Quien necesite despliegue gradual usa Feature::for($usuario) a mano.
 *
 * - **La configuración se lee al resolver, no al arrancar.** Así `feature …
 *   por-defecto` (que borra la fila) vuelve al valor VIGENTE de
 *   config/features.php, no al que había cuando el worker de Octane despertó.
 *
 * Lo que Pennant ya hace solo y conviene saber: vacía su memoria en cada
 * RequestReceived de Octane, así que un cambio hecho desde otro proceso se ve
 * en la petición siguiente (InterruptoresDeFeaturesTest lo comprueba).
 */
class FeaturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Feature::resolveScopeUsing(static fn (): null => null);

        /** @var array<string, bool> $features */
        $features = config('features', []);

        foreach (array_keys($features) as $nombre) {
            Feature::define($nombre, static fn (): bool => (bool) config("features.{$nombre}", false));
        }
    }
}
