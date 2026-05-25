<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpirationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Vehicle $vehicle,
        public string $documentType,
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
        return (new MailMessage)
            ->subject("Documento por vencer - {$this->vehicle->plate}")
            ->greeting('Alerta de vencimiento')
            ->line("El documento **{$this->documentType}** del vehículo **{$this->vehicle->plate}** vence en **{$this->daysUntilExpiry} días**.")
            ->action('Ver Vehículos', url('/vehicles'))
            ->line('Por favor gestione la renovación del documento.');
    }
}
