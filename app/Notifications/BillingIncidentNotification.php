<?php

namespace App\Notifications;

use App\Models\ServiceIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingIncidentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ServiceIncident $incident,
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
        $service = $this->incident->service;
        $type = $this->incident->incidentType;
        $value = number_format((float) $this->incident->additional_value, 0, ',', '.');

        return (new MailMessage)
            ->subject('Novedad con afectación a facturación')
            ->greeting('Alerta de facturación')
            ->line("Se registró una novedad que afecta la facturación del servicio del **{$service?->service_date}**.")
            ->line('**Tipo:** '.($type?->name ?? 'N/A'))
            ->line("**Descripción:** {$this->incident->description}")
            ->line("**Valor adicional:** $ {$value}")
            ->action('Ver Servicio', url("/services/{$service?->id}"))
            ->line('Revise el impacto en la facturación.');
    }
}
