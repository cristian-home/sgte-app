<?php

namespace App\Notifications;

use App\Models\DayStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DayExecutedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DayStatus $dayStatus,
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
            ->subject("Día Ejecutado - {$this->dayStatus->date}")
            ->greeting('Notificación de ejecución')
            ->line("El día **{$this->dayStatus->date}** ha sido marcado como **EJECUTADO**.")
            ->line('Todos los servicios del día han sido cerrados y el día está listo para facturación.')
            ->action('Ver Resumen del Día', url('/day-summary?date='.$this->dayStatus->date))
            ->line('Puede proceder con la facturación de los servicios.');
    }
}
