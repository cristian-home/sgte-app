<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('Bienvenido a SGTE')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Tu cuenta de SGTE ha sido creada por un administrador.')
            ->line('Para acceder por primera vez, configura tu contraseña usando el siguiente enlace:')
            ->action('Configurar contraseña', url(route('password.request')))
            ->line('Tras iniciar sesión por primera vez, el sistema te pedirá confirmar tu contraseña.')
            ->line('Si no esperabas este correo, ignóralo.');
    }
}
