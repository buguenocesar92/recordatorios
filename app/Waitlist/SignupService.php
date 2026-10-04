<?php

declare(strict_types=1);

namespace App\Waitlist;

use App\Models\Comuna;
use App\Models\WaitlistSignup;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Alta en la lista de espera con doble opt-in (spec §4, plan 0.4–0.5).
 *
 * Desde afuera siempre se ve lo mismo: quien llama responde igual exista o no
 * el correo, y este servicio decide en silencio si corresponde mandar el correo
 * de confirmación:
 *
 * - ya confirmado → no se manda nada (no se revela que existe);
 * - ya se mandó uno a ese correo en las últimas 24 h → nada (anti mail bombing);
 * - si no → se guarda la inscripción (vence a las 72 h, para que la purga la
 *   alcance aunque el correo no salga) y se encola `EnviarConfirmacionDeLista`,
 *   que recibe solo el id: el token se genera dentro del trabajo y en la base
 *   queda únicamente su sha256.
 *
 * Altas simultáneas del mismo correo: un candado por `email_hash` serializa la
 * sección crítica (consulta, límite de 24 h, escritura, encolado). Quien no
 * obtiene el candado espera hasta 2 s y, si sigue tomado, sale con la misma
 * respuesta genérica —la otra petición manda el correo—, y si
 * aun así el índice único del hash salta (candado vencido, caché caída), se
 * trata igual: sale en silencio. Desde afuera, siempre la misma respuesta.
 */
final class SignupService
{
    /**
     * @param  list<string>  $interests
     */
    public function register(string $email, ?string $comunaCode, array $interests, ?string $ip): void
    {
        $hash = WaitlistSignup::hashEmail($email);
        $candado = Cache::lock('waitlist-signup:'.$hash, 15);

        // Se espera un momento al candado (la otra petición suele tardar
        // milisegundos) en vez de soltar el alta en silencio; si no se libera,
        // respuesta genérica igual.
        try {
            $candado->block((int) config('waitlist.lock_wait_seconds', 2));
        } catch (LockTimeoutException) {
            return;
        }

        try {
            $signup = $this->guardar($hash, $email, $comunaCode, $interests, $ip);

            if ($signup !== null) {
                $this->encolarYEnfriar($signup, $email);
            }
        } catch (UniqueConstraintViolationException) {
            return;
        } finally {
            $candado->release();
        }
    }

    /**
     * El enfriamiento de 24 h por buzón se aplica SOLO si el trabajo quedó en
     * la cola: con la cola caída la persona no recibió nada y tiene que poder
     * reintentar. Va dentro del candado, para que dos altas simultáneas no
     * pasen las dos la comprobación antes de que una aplique el enfriamiento.
     */
    private function encolarYEnfriar(WaitlistSignup $signup, string $email): void
    {
        try {
            app(Dispatcher::class)->dispatch(new EnviarConfirmacionDeLista($signup));
        } catch (Throwable $e) {
            // Solo la clase: el mensaje de un driver de cola puede traer datos.
            Log::error('lista de espera: no se pudo encolar la confirmación ('.$e::class.')');

            return;
        }

        RateLimiter::hit(self::claveDeEnfriamiento($email), (int) config('waitlist.resend_cooldown_hours', 24) * 3600);
    }

    private static function claveDeEnfriamiento(string $email): string
    {
        // Por BUZÓN (sin +etiqueta, Gmail sin puntos): si no, ana+1@, ana+2@…
        // mandaban un correo cada una al mismo buzón.
        return 'waitlist-email:'.WaitlistSignup::hashEmail(ClavesDeLimite::buzon($email));
    }

    /**
     * @param  list<string>  $interests
     */
    private function guardar(string $hash, string $email, ?string $comunaCode, array $interests, ?string $ip): ?WaitlistSignup
    {
        $existente = WaitlistSignup::query()->where('email_hash', $hash)->first();

        if ($existente?->confirmed_at !== null) {
            return null;
        }

        if (RateLimiter::tooManyAttempts(self::claveDeEnfriamiento($email), 1)) {
            return null;
        }

        // Pasada la vida máxima desde created_at, se empieza de cero: la vieja
        // no se «refresca» (cualquiera puede escribir el correo de otro).
        if ($existente !== null && $existente->superoLaVidaMaxima()) {
            $existente->delete();
            $existente = null;
        }

        $signup = $existente ?? new WaitlistSignup(['email' => $email]);

        // Con una inscripción vigente, una reinscripción (quizás de un tercero)
        // NO pisa la comuna, los intereses ni el consentimiento de su dueño:
        // solo se le vuelve a mandar el enlace.
        $vigente = $existente !== null && $existente->confirm_expires_at !== null && $existente->confirm_expires_at->isFuture();

        if (! $vigente) {
            $signup->fill([
                'comuna_id' => $comunaCode !== null ? Comuna::query()->where('code', $comunaCode)->value('id') : null,
                'interests' => array_values(array_unique($interests)),
                'consent_version' => VersionDelConsentimiento::actual(),
                'consented_at' => now(),
                'ip_hash' => WaitlistSignup::hashIp($ip),
            ]);
        }

        if (! $signup->exists) {
            $signup->save();
        }

        $signup->forceFill(['confirm_expires_at' => $signup->vencimientoDeConfirmacion()])->save();

        return $signup;
    }
}
