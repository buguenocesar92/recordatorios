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
 * GENERA aquí, en el momento de enviar; en la base queda únicamente su sha256
 * y su vencimiento (72 h desde el envío). Un token se usa una sola vez
 * (`confirmed_at`), igual que antes.
 *
 * El error del transporte (SMTP, API) suele nombrar al destinatario, y su
 * traza puede traer argumentos: se descarta y se relanza uno genérico, para que
 * `failed_jobs.exception` tampoco guarde el correo.
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

        $token = ConfirmationToken::generate();

        $signup->forceFill([
            'confirm_token_hash' => ConfirmationToken::hash($token),
            'confirm_expires_at' => now()->addHours((int) config('waitlist.confirm_ttl_hours', 72)),
        ])->save();

        try {
            Mail::to($signup->email)->send(new WaitlistConfirmationMail($signup, $token));
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo enviar el correo de confirmación ('.$e::class.').');
        }
    }
}
