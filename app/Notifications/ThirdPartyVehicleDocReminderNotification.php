<?php

namespace App\Notifications;

use App\Models\ThirdParty;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * REQ-004 third-party fleet document reminder (REQ: third-party-vehicle-doc-reminders).
 *
 * One digest per day per third-party provider that owns one or more
 * vehicles with SOAT / RTM / Tarjeta de Operación expiring exactly at
 * the 30-, 7-, or 1-day threshold. Ops is cc'd via
 * `config('sgte.ops_alert_email')` so compliance has visibility.
 */
class ThirdPartyVehicleDocReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  ThirdParty  $provider  Owner of the listed vehicles.
     * @param  list<array{plate: string, document_label: string, due_date: string, days_until_expiry: int}>  $entries
     */
    public function __construct(
        public ThirdParty $provider,
        public array $entries,
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
        $providerLabel = $this->providerLabel();

        $message = (new MailMessage)
            ->subject('Documentos de sus vehículos próximos a vencer')
            ->view('emails.third-party-doc-reminders', [
                'providerLabel' => $providerLabel,
                'entries' => $this->entries,
            ]);

        $cc = config('sgte.ops_alert_email');
        if (is_string($cc) && $cc !== '') {
            $message->cc($cc);
        }

        return $message;
    }

    protected function providerLabel(): string
    {
        if ($this->provider->is_natural_person) {
            $name = trim(($this->provider->first_name ?? '').' '.($this->provider->first_lastname ?? ''));

            return $name !== '' ? $name : 'Proveedor';
        }

        return $this->provider->company_name ?? 'Proveedor';
    }
}
