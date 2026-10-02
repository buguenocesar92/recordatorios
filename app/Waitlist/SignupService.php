<?php

declare(strict_types=1);

namespace App\Waitlist;

use App\Mail\WaitlistConfirmationMail;
use App\Models\Comuna;
use App\Models\WaitlistSignup;
use Illuminate\Support\Facades\Mail;
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
 * - si no → token nuevo de 32 bytes (solo se guarda su sha256), vence a las
 *   72 h, y el correo sale por la cola.
 */
final class SignupService
{
    /**
     * @param  list<string>  $interests
     */
    public function register(string $email, ?string $comunaCode, array $interests, ?string $ip): void
    {
        $hash = WaitlistSignup::hashEmail($email);
        $existente = WaitlistSignup::query()->where('email_hash', $hash)->first();

        if ($existente?->confirmed_at !== null) {
            return;
        }

        $clave = 'waitlist-email:'.$hash;
        $horas = (int) config('waitlist.resend_cooldown_hours', 24);

        if (RateLimiter::tooManyAttempts($clave, 1)) {
            return;
        }

        $token = ConfirmationToken::generate();

        $signup = $existente ?? new WaitlistSignup(['email' => $email]);
        $signup->fill([
            'comuna_id' => $comunaCode !== null ? Comuna::query()->where('code', $comunaCode)->value('id') : null,
            'interests' => array_values(array_unique($interests)),
            'consent_version' => (string) config('waitlist.consent_version'),
            'consented_at' => now(),
            'ip_hash' => WaitlistSignup::hashIp($ip),
            'confirm_token_hash' => ConfirmationToken::hash($token),
            'confirm_expires_at' => now()->addHours((int) config('waitlist.confirm_ttl_hours', 72)),
        ])->save();

        RateLimiter::hit($clave, $horas * 3600);

        Mail::to($signup->email)->queue(new WaitlistConfirmationMail($signup, $token));
    }
}
