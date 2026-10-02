<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Clase base de todas las notificaciones del sistema.
 */
abstract class SystemNotification extends Notification
{
    use Queueable;

    /**
     * Canales de entrega.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye un MailMessage con la vista Markdown indicada.
     *
     * @param  array<string, mixed>  $data
     */
    protected function correo(string $asunto, string $vista, array $data = []): MailMessage
    {
        return (new MailMessage)
            ->subject($asunto)
            ->markdown($vista, $data);
    }
}
