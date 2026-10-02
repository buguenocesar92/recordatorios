<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WaitlistSignup;
use App\Waitlist\EnlacesDeLaLista;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * El correo del doble opt-in. NO es `ShouldQueue` a propósito: encolarlo
 * serializaría el destinatario descifrado y el token en Redis y en
 * `failed_jobs`. Lo manda `EnviarConfirmacionDeLista` (ese sí va por la cola y
 * solo lleva el id) con un envío directo, ya dentro del trabajo.
 *
 * Lleva solo lo necesario: el enlace para confirmar y el de baja. Nada del
 * `email_hash`, la IP ni otros datos de la inscripción. El token existe en claro
 * SOLO aquí, en memoria, mientras se envía (en la base está su sha256).
 */
final class WaitlistConfirmationMail extends Mailable
{
    public function __construct(public WaitlistSignup $signup, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirma tu inscripción en la lista de espera');
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.EnlacesDeLaLista::baja($this->signup->id).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lista.confirmar',
            with: [
                'confirmar' => EnlacesDeLaLista::confirmar($this->token),
                'baja' => EnlacesDeLaLista::baja($this->signup->id),
                'horas' => (int) config('waitlist.confirm_ttl_hours', 72),
            ],
        );
    }
}
