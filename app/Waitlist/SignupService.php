<?php

declare(strict_types=1);

namespace App\Waitlist;

use App\Models\Comuna;
use App\Models\WaitlistSignup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

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
 * obtiene el candado sale en silencio —la otra petición manda el correo—, y si
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

        if (! $candado->get()) {
            return;
        }

        try {
            $signup = $this->guardar($hash, $email, $comunaCode, $interests, $ip);
        } catch (UniqueConstraintViolationException) {
            return;
        } finally {
            $candado->release();
        }

        if ($signup !== null) {
            EnviarConfirmacionDeLista::dispatch($signup);
        }
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

        // El enfriamiento cuenta por BUZÓN (sin +etiqueta, Gmail sin puntos):
        // si no, ana+1@, ana+2@… mandaban un correo cada una al mismo buzón.
        $clave = 'waitlist-email:'.WaitlistSignup::hashEmail(ClavesDeLimite::buzon($email));
        $horas = (int) config('waitlist.resend_cooldown_hours', 24);

        if (RateLimiter::tooManyAttempts($clave, 1)) {
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

        RateLimiter::hit($clave, $horas * 3600);

        return $signup;
    }
}
