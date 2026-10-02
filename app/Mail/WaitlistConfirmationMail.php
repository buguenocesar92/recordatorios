<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WaitlistSignup;
use App\Waitlist\EnlacesDeLaLista;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * El correo del doble opt-in. Sale por la cola: el formulario responde igual de
 * rápido haya o no que mandarlo (no revela si el correo ya estaba inscrito).
 *
 * Lleva solo lo necesario: el enlace para confirmar y el de baja. Nada del
 * `email_hash`, la IP ni otros datos de la inscripción. El token viaja en claro
 * SOLO aquí (en la base está su sha256).
 */
final class WaitlistConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

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
