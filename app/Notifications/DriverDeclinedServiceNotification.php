<?php

namespace App\Notifications;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverDeclinedServiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Service $service,
        public string $reason,
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
        $driver = $this->service->driver;
        $driverName = trim(($driver?->first_name ?? '').' '.($driver?->first_lastname ?? '')) ?: 'N/A';
        $plate = $this->service->vehicle?->plate ?? 'N/A';

        return (new MailMessage)
            ->subject('Conductor declinó un servicio — requiere reasignación')
            ->greeting('Reasignación pendiente')
            ->line("El conductor **{$driverName}** declinó el servicio del **{$this->service->service_date}**.")
            ->line("**Vehículo:** {$plate}")
            ->line("**Motivo:** {$this->reason}")
            ->action('Ver Servicio', url("/services/{$this->service->id}"))
            ->line('Asigne otro conductor o cierre el servicio para continuar la operación del día.');
    }
}
