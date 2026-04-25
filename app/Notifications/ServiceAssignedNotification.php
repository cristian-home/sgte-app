<?php

namespace App\Notifications;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Service $service,
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
        $vehicle = $this->service->vehicle;
        $date = $this->service->service_date;
        $time = $this->service->planned_start_local ?? '';

        return (new MailMessage)
            ->subject('Servicio Asignado - '.$date)
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Se le ha asignado un nuevo servicio.')
            ->line('**Fecha:** '.$date)
            ->line('**Hora planificada:** '.$time)
            ->line('**Vehículo:** '.($vehicle?->plate ?? 'N/A'))
            ->action('Ver Mis Servicios', url('/driver'))
            ->line('Por favor confirme el inicio y fin del servicio desde la aplicación.');
    }
}
