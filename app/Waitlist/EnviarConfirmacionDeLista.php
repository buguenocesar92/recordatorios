<?php

declare(strict_types=1);

namespace App\Waitlist;

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistSignup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Manda el correo del doble opt-in desde la cola, sin secretos en la carga.
 *
 * Lo que viaja por la cola (Redis mientras espera, `failed_jobs` si falla, y de
 * ahí a los respaldos de la base) es solo el id de la inscripción: nunca el
 * correo descifrado ni el token. El correo se lee de la base y el token se
 * GENERA aquí, en el momento de enviar (el mismo en cada reintento del
 * trabajo, ver token()); en la base queda únicamente su sha256
 * y su vencimiento (72 h desde el envío). Un token se usa una sola vez
 * (`confirmed_at`), igual que antes.
 *
 * El error del transporte (SMTP, API) suele nombrar al destinatario, y su
 * traza puede traer argumentos: se descarta y se relanza uno genérico, para que
 * `failed_jobs.exception` tampoco guarde el correo.
 *
 * Si el dominio no tiene MX ni A (comprobado acá y no en la petición, que no
 * debe esperar al DNS), la inscripción se borra sin mandar nada.
 *
 * Si la persona se dio de baja antes de que corra el trabajo, la inscripción ya
 * no existe y el trabajo se descarta solo (`deleteWhenMissingModels`).
 */
final class EnviarConfirmacionDeLista implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public WaitlistSignup $signup) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $signup = $this->signup;

        if ($signup->confirmed_at !== null || $signup->unsubscribed_at !== null) {
            return;
        }

        // Un dominio que no recibe correo: no hay a quién escribirle ni nada
        // que conservar. Se borra en silencio (la respuesta al formulario ya
        // fue la genérica).
        if (config('waitlist.verify_mx') === true && ! app(DominioConCorreo::class)->acepta((string) $signup->email)) {
            $signup->delete();

            return;
        }

        $token = $this->token();

        $signup->forceFill([
            'confirm_token_hash' => ConfirmationToken::hash($token),
            'confirm_expires_at' => $signup->vencimientoDeConfirmacion(),
        ])->save();

        try {
            Mail::to($signup->email)->send(new WaitlistConfirmationMail($signup, $token));
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo enviar el correo de confirmación ('.$e::class.').');
        }
    }

    /**
     * El mismo token en todos los reintentos de ESTE trabajo: si el primer
     * intento llegó a entregar el correo y falló después (el transporte cortó
     * tras aceptar), regenerarlo dejaba muerto el enlace que la persona ya
     * tiene. Se deriva del uuid del trabajo (igual en cada reintento, distinto
     * en cada envío) con una clave sacada de APP_KEY, así que nunca viaja ni
     * se guarda en claro. Sin trabajo de cola (llamada directa), uno al azar.
     */
    private function token(): string
    {
        $uuid = $this->job?->uuid();

        if (! is_string($uuid) || $uuid === '') {
            return ConfirmationToken::generate();
        }

        $clave = hash('sha256', 'waitlist-confirmacion|'.(string) config('app.key'), true);
        $crudo = hash_hmac('sha256', $uuid.'|'.$this->signup->id, $clave, true);

        return rtrim(strtr(base64_encode($crudo), '+/', '-_'), '=');
    }
}
