<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Invita a un conductor a configurar su contraseña tras la creación de
 * su cuenta desde el módulo Conductores. Reutiliza el broker de Fortify
 * para emitir un token de reset firmado y construir la URL al formulario
 * de reset estándar (`auth/reset-password`).
 */
class DriverAccountInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct() {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = Password::broker(config('fortify.passwords'))->createToken($notifiable);
        $url = route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expireMinutes = (int) config('auth.passwords.'.config('fortify.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Te invitamos a SGTE — Configura tu acceso')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Un administrador creó tu cuenta de conductor en SGTE.')
            ->line('Configura tu contraseña usando el siguiente enlace para empezar a usar el sistema:')
            ->action('Configurar contraseña', $url)
            ->line('Este enlace expira en '.$expireMinutes.' minutos.')
            ->line('Si no esperabas este correo, ignóralo.');
    }
}
