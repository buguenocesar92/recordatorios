<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Deja constancia de quién entró al panel, y de quién lo intentó.
 *
 * El historial de cambios dice quién editó qué, pero no quién entró. Son
 * preguntas distintas: varios intentos fallidos seguidos sobre la misma cuenta
 * es lo que hay que poder ver después de un incidente, y sin registro no se ve.
 *
 * La dirección IP NO se guarda. La Ley 21.719 la trata como dato personal, y
 * para lo que sirve acá —saber si dos ingresos vienen del mismo lugar— basta su
 * huella, que no permite reconstruir de dónde venían. El navegador tampoco se
 * guarda entero: solo si era un teléfono o un computador.
 */
class RegistrarAcceso
{
    public function __construct(private readonly Request $peticion) {}

    public function alEntrar(Login $evento): void
    {
        activity()
            // El evento entrega un «Authenticatable», que no siempre es un
            // modelo; acá siempre lo es, y se dice para que quede comprobado.
            ->causedBy($evento->user instanceof Model ? $evento->user : null)
            ->withProperties($this->contexto())
            ->log('ingreso');
    }

    public function alFallar(Failed $evento): void
    {
        activity()
            ->withProperties($this->contexto() + [
                // El correo intentado sí se guarda: sin él, saber que hubo diez
                // intentos fallidos no dice contra qué cuenta.
                'cuenta' => $evento->credentials['email'] ?? null,
            ])
            ->log('ingreso fallido');
    }

    public function alSalir(Logout $evento): void
    {
        activity()
            // El evento entrega un «Authenticatable», que no siempre es un
            // modelo; acá siempre lo es, y se dice para que quede comprobado.
            ->causedBy($evento->user instanceof Model ? $evento->user : null)
            ->withProperties($this->contexto())
            ->log('salida');
    }

    /**
     * @return array<string, mixed>
     */
    private function contexto(): array
    {
        return [
            // La huella, no la dirección. Con la sal de la aplicación, para que
            // una tabla de huellas hecha de antemano no sirva para deshacerla.
            'ip_hash' => hash('sha256', config('app.key').'|'.(string) $this->peticion->ip()),
            'dispositivo' => $this->peticion->userAgent() !== null
                && str_contains((string) $this->peticion->userAgent(), 'Mobile')
                    ? 'teléfono'
                    : 'computador',
            'cuando' => now()->toDateTimeString(),
        ];
    }
}
