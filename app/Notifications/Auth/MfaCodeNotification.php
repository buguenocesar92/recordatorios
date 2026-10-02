<?php

namespace App\Notifications\Auth;

use App\Notifications\SystemNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Entrega el código de verificación en dos pasos (MFA) por correo.
 */
class MfaCodeNotification extends SystemNotification
{
    public function __construct(
        private readonly string $codigo,
        private readonly int $minutosVigencia = 10,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return $this->correo(
            asunto: 'Tu código de verificación · '.config('app.name'),
            vista: 'emails.auth.mfa',
            data: [
                'codigo' => $this->codigo,
                'minutos' => $this->minutosVigencia,
                'nombre' => $notifiable->name ?? null,
            ],
        );
    }
}
