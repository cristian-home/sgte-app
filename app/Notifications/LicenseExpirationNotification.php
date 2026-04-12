<?php

namespace App\Notifications;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseExpirationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Driver $driver,
        public int $daysUntilExpiry,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $driverName = "{$this->driver->first_name} {$this->driver->first_lastname}";

        return (new MailMessage)
            ->subject("Licencia por vencer - {$driverName}")
            ->greeting('Alerta de vencimiento')
            ->line("La licencia del conductor **{$driverName}** vence en **{$this->daysUntilExpiry} días**.")
            ->action('Ver Conductores', url('/drivers'))
            ->line('Por favor gestione la renovación de la licencia.');
    }
}
