<?php

namespace App\Notifications\Auth;

use App\Models\User;
use App\Notifications\SystemNotification;
use Filament\Facades\Filament;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo de restablecimiento de contraseña, con la identidad visual del sistema.
 *
 * Sale por dos caminos y los dos terminan acá: el panel (que la construye por
 * el contenedor, ver AppServiceProvider::register) y «Password::sendResetLink»
 * sin callback (que pasa por User::sendPasswordResetNotification).
 */
class RestablecerContrasenaNotification extends SystemNotification
{
    /**
     * La rellena el panel cuando es él quien pide el restablecimiento. Queda
     * declarada —y no como propiedad al vuelo— porque PHP dejó de aceptarlas.
     */
    public string $url = '';

    public function __construct(private readonly string $token) {}

    public function toMail(object $notifiable): MailMessage
    {
        \assert($notifiable instanceof User);

        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        // La dirección la arma el panel: su pantalla de restablecimiento exige
        // un enlace firmado, así que una URL construida a mano —como la de la
        // ruta propia que había antes— rebota con un 403 aunque el testigo sea
        // válido.
        $url = $this->url !== ''
            ? $this->url
            : Filament::getResetPasswordUrl($this->token, $notifiable);

        return $this->correo(
            asunto: 'Restablece tu contraseña · '.config('app.name'),
            vista: 'emails.auth.restablecer-contrasena',
            data: [
                'nombre' => $notifiable->name ?? null,
                'url' => $url,
                'minutos' => $minutos,
            ],
        );
    }
}
