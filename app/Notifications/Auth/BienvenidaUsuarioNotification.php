<?php

namespace App\Notifications\Auth;

use App\Notifications\SystemNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo de bienvenida al crear una cuenta desde el panel.
 */
class BienvenidaUsuarioNotification extends SystemNotification implements ShouldQueue
{
    public function toMail(object $notifiable): MailMessage
    {
        return $this->correo(
            asunto: 'Tu cuenta en '.config('app.name'),
            vista: 'emails.auth.bienvenida',
            data: [
                'nombre' => $notifiable->name ?? null,
                'correo' => $notifiable->email ?? null,
                'urlIngreso' => route('filament.admin.auth.login'),
            ],
        );
    }
}
